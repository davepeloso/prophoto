<?php

namespace ProPhoto\Gallery\Filament\Resources;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Hidden;
use Filament\Schemas\Components\Placeholder;
use Filament\Schemas\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use ProPhoto\Gallery\Enums\GalleryTemplateDefinition;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\Actions\AddImagesFromSessionAction;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\Actions\GenerateShareLinkAction;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\Pages;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers\GalleryActivityRelationManager;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers\GalleryImagesRelationManager;
use ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers\GalleryShareRelationManager;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Events\GalleryDelivered;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\StudioPendingTypeTemplate;
use ProPhoto\Gallery\Services\GalleryActivityLogger;
use ProPhoto\Gallery\Services\ViewerTemplateRegistry;

/**
 * GalleryResource
 *
 * Two-step Filament wizard for creating a Gallery:
 *   Step 1 — Template picker (visual card grid)
 *   Step 2 — Gallery configuration (pre-filled from template, fully overridable)
 *
 * Architecture:
 *   - Writes only to galleries and gallery_pending_types (both prophoto-gallery)
 *   - No file handling — session_id is a reference FK only
 *   - prophoto-assets is not touched here (that's Story 2.3 / 2.4)
 */
class GalleryResource extends Resource
{
    protected static ?string $model = Gallery::class;

    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-photo';
    protected static \UnitEnum|string|null $navigationGroup = 'Galleries';
    protected static ?string $navigationLabel = 'Galleries';
    protected static ?int    $navigationSort  = 1;

    // ── Form ─────────────────────────────────────────────────────────────

    public static function form(Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form->schema([
            Wizard::make([
                // ── Step 1: Template Picker ───────────────────────────────
                Step::make('Template')
                    ->description('Choose a starting point for your gallery.')
                    ->icon('heroicon-o-squares-2x2')
                    ->schema([
                        Radio::make('template_key')
                            ->label('Gallery Template')
                            ->options(GalleryTemplateDefinition::filamentOptions())
                            ->descriptions(GalleryTemplateDefinition::filamentDescriptions())
                            ->default(GalleryTemplateDefinition::Portrait->value)
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (!$state) {
                                    return;
                                }

                                $template = GalleryTemplateDefinition::from($state);
                                $config   = $template->modeConfig() ?? Gallery::DEFAULT_MODE_CONFIG;

                                $set('type', $template->galleryType());
                                $set('mode_config.min_approvals',      $config['min_approvals'] ?? null);
                                $set('mode_config.max_approvals',      $config['max_approvals'] ?? null);
                                $set('mode_config.max_pending',        $config['max_pending'] ?? null);
                                $set('mode_config.ratings_enabled',    $config['ratings_enabled'] ?? true);
                                $set('mode_config.pipeline_sequential', $config['pipeline_sequential'] ?? true);
                            })
                            ->required()
                            ->columnSpanFull(),
                    ]),

                // ── Step 2: Configuration ─────────────────────────────────
                Step::make('Configuration')
                    ->description('Name and configure your gallery.')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->schema([

                        // ── Basic details ─────────────────────────────────
                        Section::make('Gallery Details')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Gallery Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->placeholder('e.g. Smith Family — Spring 2026'),

                                    TextInput::make('subject_name')
                                        ->label('Subject / Client Name')
                                        ->maxLength(255)
                                        ->placeholder('e.g. The Smith Family'),
                                ]),

                                Select::make('type')
                                    ->label('Gallery Type')
                                    ->options([
                                        Gallery::TYPE_PROOFING      => 'Proofing — sequential approval pipeline',
                                        Gallery::TYPE_PRESENTATION  => 'Presentation — view only, no pipeline',
                                    ])
                                    ->required()
                                    ->live()
                                    ->default(Gallery::TYPE_PROOFING)
                                    ->helperText('You can change this at any time from gallery settings.'),
                            ]),

                        // ── Viewer template picker (Story 7.4) ───────────
                        Section::make('Viewer Template')
                            ->description('Choose how the gallery looks to your clients.')
                            ->schema([
                                Radio::make('viewer_template')
                                    ->label('Gallery Style')
                                    ->options(function (Get $get): array {
                                        $type = $get('type') ?? Gallery::TYPE_PROOFING;
                                        return app(ViewerTemplateRegistry::class)->filamentOptions($type);
                                    })
                                    ->default('default')
                                    ->columnSpanFull(),
                            ]),

                        // ── Proofing pipeline config ──────────────────────
                        // Shown only when type = proofing
                        Section::make('Proofing Pipeline')
                            ->description('Configure how clients interact with images in this gallery.')
                            ->hidden(fn (Get $get): bool => $get('type') !== Gallery::TYPE_PROOFING)
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('mode_config.min_approvals')
                                        ->label('Minimum Approvals')
                                        ->numeric()
                                        ->minValue(1)
                                        ->nullable()
                                        ->placeholder('None')
                                        ->helperText('Minimum images a client must approve before submitting.'),

                                    TextInput::make('mode_config.max_approvals')
                                        ->label('Maximum Approvals')
                                        ->numeric()
                                        ->minValue(1)
                                        ->nullable()
                                        ->placeholder('None')
                                        ->helperText('Cap on total approvals. Leave blank for unlimited.'),

                                    TextInput::make('mode_config.max_pending')
                                        ->label('Maximum Pending')
                                        ->numeric()
                                        ->minValue(1)
                                        ->nullable()
                                        ->placeholder('None')
                                        ->helperText('Cap on pending retouch requests. Leave blank for unlimited.'),
                                ]),

                                Grid::make(2)->schema([
                                    Toggle::make('mode_config.ratings_enabled')
                                        ->label('Enable Star Ratings')
                                        ->default(true)
                                        ->helperText('Allow clients to rate images 1–5 stars.'),

                                    Toggle::make('mode_config.pipeline_sequential')
                                        ->label('Sequential Approval')
                                        ->default(true)
                                        ->helperText('Pending retouch options are locked until image is Approved.'),
                                ]),
                            ]),

                        // ── Pending types checklist ───────────────────────
                        // Shown only when type = proofing
                        Section::make('Pending Types')
                            ->description('Choose which pending types are available in this gallery. Uncheck to exclude.')
                            ->hidden(fn (Get $get): bool => $get('type') !== Gallery::TYPE_PROOFING)
                            ->schema([
                                static::buildPendingTypesChecklist(),
                            ]),
                    ]),
            ])
            ->skippable(false)
            ->columnSpanFull(),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Gallery::TYPE_PROOFING     => 'warning',
                        Gallery::TYPE_PRESENTATION => 'success',
                        default                    => 'gray',
                    }),

                TextColumn::make('subject_name')
                    ->label('Subject')
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('image_count')
                    ->label('Images')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        Gallery::STATUS_ACTIVE    => 'success',
                        Gallery::STATUS_COMPLETED => 'info',
                        Gallery::STATUS_ARCHIVED  => 'gray',
                        default                   => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        Gallery::TYPE_PROOFING      => 'Proofing',
                        Gallery::TYPE_PRESENTATION  => 'Presentation',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        Gallery::STATUS_ACTIVE    => 'Active',
                        Gallery::STATUS_COMPLETED => 'Completed',
                        Gallery::STATUS_ARCHIVED  => 'Archived',
                    ])
                    ->default(Gallery::STATUS_ACTIVE),
            ])
            ->actions([
                GenerateShareLinkAction::make(),
                AddImagesFromSessionAction::make(),
                EditAction::make(),
                ActionGroup::make([
                    static::makeDeliverAction(),
                    static::makeCompleteAction(),
                    static::makeArchiveAction(),
                    static::makeUnarchiveAction(),
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->tooltip('Lifecycle'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    // ── Relations ─────────────────────────────────────────────────────────

    public static function getRelations(): array
    {
        return [
            GalleryImagesRelationManager::class,
            GalleryShareRelationManager::class,
            GalleryActivityRelationManager::class,
        ];
    }

    // ── Pages ─────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGalleries::route('/'),
            'create' => Pages\CreateGallery::route('/create'),
            'edit'   => Pages\EditGallery::route('/{record}/edit'),
        ];
    }

    // ── Lifecycle Actions ─────────────────────────────────────────────────

    /**
     * Mark a gallery as completed.
     */
    public static function makeCompleteAction(): Action
    {
        return Action::make('complete')
            ->label('Mark Completed')
            ->icon('heroicon-o-check-badge')
            ->color('info')
            ->visible(fn (Gallery $record): bool => $record->status === Gallery::STATUS_ACTIVE)
            ->requiresConfirmation()
            ->modalHeading('Mark Gallery as Completed')
            ->modalDescription('This marks the gallery as completed. Clients can still access active share links.')
            ->action(function (Gallery $record): void {
                $record->update([
                    'status'       => Gallery::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                GalleryActivityLogger::log(
                    gallery: $record,
                    actionType: 'gallery_completed',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                );

                Notification::make()
                    ->title('Gallery marked as completed')
                    ->success()
                    ->send();
            });
    }

    /**
     * Archive a gallery (hides from default list view).
     */
    public static function makeArchiveAction(): Action
    {
        return Action::make('archive')
            ->label('Archive')
            ->icon('heroicon-o-archive-box')
            ->color('warning')
            ->visible(fn (Gallery $record): bool =>
                in_array($record->status, [Gallery::STATUS_ACTIVE, Gallery::STATUS_COMPLETED], true)
            )
            ->requiresConfirmation()
            ->modalHeading('Archive Gallery')
            ->modalDescription('Archived galleries are hidden from the default list. All data and share links are preserved. You can unarchive at any time.')
            ->action(function (Gallery $record): void {
                $record->update([
                    'status'      => Gallery::STATUS_ARCHIVED,
                    'archived_at' => now(),
                ]);

                GalleryActivityLogger::log(
                    gallery: $record,
                    actionType: 'gallery_archived',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                );

                Notification::make()
                    ->title('Gallery archived')
                    ->success()
                    ->send();
            });
    }

    /**
     * Unarchive a gallery back to active.
     */
    public static function makeUnarchiveAction(): Action
    {
        return Action::make('unarchive')
            ->label('Unarchive')
            ->icon('heroicon-o-archive-box-arrow-down')
            ->color('success')
            ->visible(fn (Gallery $record): bool => $record->status === Gallery::STATUS_ARCHIVED)
            ->requiresConfirmation()
            ->modalHeading('Unarchive Gallery')
            ->modalDescription('This will restore the gallery to active status.')
            ->action(function (Gallery $record): void {
                $record->update([
                    'status'      => Gallery::STATUS_ACTIVE,
                    'archived_at' => null,
                ]);

                GalleryActivityLogger::log(
                    gallery: $record,
                    actionType: 'gallery_unarchived',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                );

                Notification::make()
                    ->title('Gallery restored to active')
                    ->success()
                    ->send();
            });
    }

    /**
     * Story 7.5 — Mark a gallery as delivered and notify all active shares.
     */
    public static function makeDeliverAction(): Action
    {
        return Action::make('deliver')
            ->label('Deliver to Clients')
            ->icon('heroicon-o-paper-airplane')
            ->color('success')
            ->visible(fn (Gallery $record): bool =>
                in_array($record->status, [Gallery::STATUS_ACTIVE, Gallery::STATUS_COMPLETED], true)
                && $record->delivered_at === null
            )
            ->form([
                Textarea::make('delivery_message')
                    ->label('Message to Clients (optional)')
                    ->placeholder('Your images are ready for download!')
                    ->helperText('This message will be included in the notification email sent to all clients with active gallery access.')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->modalHeading('Deliver Gallery')
            ->modalDescription('This will send a "Your Gallery is Ready" email to every client with an active share link. The gallery will also be marked as completed.')
            ->modalSubmitActionLabel('Deliver Now')
            ->action(function (Gallery $record, array $data): void {
                $deliveryMessage = $data['delivery_message'] ?? null;

                // Mark as completed + delivered
                $record->update([
                    'status'       => Gallery::STATUS_COMPLETED,
                    'completed_at' => $record->completed_at ?? now(),
                    'delivered_at' => now(),
                ]);

                // Log to activity ledger
                GalleryActivityLogger::log(
                    gallery: $record,
                    actionType: 'gallery_delivered',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                    metadata: [
                        'delivery_message' => $deliveryMessage,
                    ],
                );

                // Query active shares (not revoked, not expired)
                $activeShares = GalleryShare::where('gallery_id', $record->id)
                    ->whereNull('revoked_at')
                    ->where(function ($q) {
                        $q->whereNull('expires_at')
                          ->orWhere('expires_at', '>', now());
                    })
                    ->get()
                    ->map(fn (GalleryShare $share) => [
                        'share_id'    => $share->id,
                        'email'       => $share->shared_with_email,
                        'share_token' => $share->share_token,
                    ])
                    ->values()
                    ->all();

                // Dispatch event for notification system
                GalleryDelivered::dispatch(
                    galleryId:        $record->id,
                    studioId:         $record->studio_id,
                    galleryName:      $record->subject_name ?? 'Untitled Gallery',
                    deliveryMessage:  $deliveryMessage,
                    deliveredAt:      now()->toIso8601String(),
                    deliveredByUserId: auth()->id(),
                    activeShares:     $activeShares,
                );

                $shareCount = count($activeShares);
                $message = $shareCount > 0
                    ? "Gallery delivered — {$shareCount} " . ($shareCount === 1 ? 'client' : 'clients') . ' will be notified'
                    : 'Gallery marked as delivered (no active shares to notify)';

                Notification::make()
                    ->title($message)
                    ->success()
                    ->send();
            });
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Build a checklist of studio pending type templates.
     * Each item renders as a checkbox — checked by default.
     * Stores checked template IDs in pending_type_ids[] for use in CreateGallery.
     */
    protected static function buildPendingTypesChecklist(): \Filament\Schemas\Components\Component
    {
        $studioId = auth()->user()?->studio_id;

        $templates = StudioPendingTypeTemplate::activeForStudio($studioId)
            ->get()
            ->map(fn ($t) => [
                'id'          => $t->id,
                'name'        => $t->name,
                'description' => $t->description,
                'is_system'   => $t->is_system_default,
            ])
            ->all();

        $schema = [];

        foreach ($templates as $template) {
            $schema[] = Toggle::make("pending_type_ids.{$template['id']}")
                ->label($template['name'])
                ->helperText($template['description'] ?? null)
                ->default(true)
                ->columnSpan(1);
        }

        if (empty($schema)) {
            $schema[] = Placeholder::make('no_pending_types')
                ->label('')
                ->content('No pending types configured. Add some in Gallery Settings → Pending Type Templates.');
        }

        return Grid::make(2)->schema($schema);
    }
}
