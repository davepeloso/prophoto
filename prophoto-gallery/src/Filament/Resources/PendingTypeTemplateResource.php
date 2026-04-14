<?php

namespace ProPhoto\Gallery\Filament\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource\Pages;
use ProPhoto\Gallery\Models\StudioPendingTypeTemplate;

/**
 * Filament resource for managing studio-level pending type templates.
 *
 * The photographer can:
 *   - View the four system defaults (read-only name, can toggle active)
 *   - Add custom pending types for their studio
 *   - Reorder via sort_order
 *   - Hide system defaults without deleting them
 *
 * System defaults (is_system_default = true) cannot be deleted.
 */
class PendingTypeTemplateResource extends Resource
{
    protected static ?string $model = StudioPendingTypeTemplate::class;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-queue-list';

    protected static string|null|\UnitEnum $navigationGroup = 'Gallery Settings';

    protected static ?string $navigationLabel = 'Pending Type Templates';

    protected static ?string $modelLabel = 'Pending Type';

    protected static ?string $pluralModelLabel = 'Pending Type Templates';

    protected static ?int $navigationSort = 10;

    // ── Scoping — only show templates for the current studio ─────────────────

    public static function getEloquentQuery(): Builder
    {
        $studioId = auth()->user()?->studio_id;

        return parent::getEloquentQuery()
            ->where(function (Builder $q) use ($studioId) {
                $q->whereNull('studio_id')          // system defaults
                  ->orWhere('studio_id', $studioId); // studio's own
            })
            ->orderBy('sort_order');
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form|\Filament\Schemas\Schema $form): \Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Section::make('Pending Type Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('e.g. Retouch, Background Swap')
                            ->helperText('Shown to clients in the proofing modal.'),

                        Forms\Components\TextInput::make('icon')
                            ->maxLength(50)
                            ->placeholder('heroicon slug, e.g. pencil-square')
                            ->helperText('Optional Heroicon name for visual flair.'),

                        Forms\Components\Textarea::make('description')
                            ->rows(2)
                            ->maxLength(500)
                            ->placeholder('Explain when clients should use this type (tooltip).')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->label('Sort Order')
                            ->helperText('Lower numbers appear first in the proofing modal.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive types are hidden from the proofing modal. System defaults can be hidden but not deleted.'),
                    ])
                    ->columns(2),

                Section::make('System Info')
                    ->schema([
                        Forms\Components\Placeholder::make('is_system_default')
                            ->label('System Default?')
                            ->content(fn ($record) => $record?->is_system_default ? 'Yes — managed by ProPhoto' : 'No — your custom type'),
                    ])
                    ->visible(fn ($record) => $record !== null)
                    ->collapsed(),
            ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->width(50),

                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->limit(60)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Icon')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('is_system_default')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-user')
                    ->trueColor('gray')
                    ->falseColor('primary'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn ($record) => false), // allow toggling even system defaults
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active only'),
                Tables\Filters\TernaryFilter::make('is_system_default')
                    ->label('System defaults'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->is_system_default)
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->hidden(), // disable bulk delete for safety
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Add Pending Type'),
            ]);
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPendingTypeTemplates::route('/'),
            'create' => Pages\CreatePendingTypeTemplate::route('/create'),
            'edit'   => Pages\EditPendingTypeTemplate::route('/{record}/edit'),
        ];
    }
}
