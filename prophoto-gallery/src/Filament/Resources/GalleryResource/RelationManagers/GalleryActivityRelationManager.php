<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Story 3.5 + 4.3 — Gallery activity ledger relation manager.
 *
 * Read-only table showing all activity log entries for a gallery.
 * Displayed on the EditGallery page alongside the images relation manager.
 *
 * Story 4.3 additions:
 *   - Distinct icon per action type
 *   - Image filename resolved from relationship (instead of raw ID)
 *   - Empty state message
 */
class GalleryActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'activityLogs';

    protected static ?string $title = 'Activity Log';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-clock';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('action_icon')
                    ->label('')
                    ->state(fn ($record) => $record->action_type)
                    ->icon(fn (string $state): string => match ($state) {
                        'approved'           => 'heroicon-o-check-circle',
                        'approved_pending'   => 'heroicon-o-wrench-screwdriver',
                        'cleared'            => 'heroicon-o-x-circle',
                        'rated'              => 'heroicon-o-star',
                        'gallery_submitted'  => 'heroicon-o-lock-closed',
                        'gallery_locked'     => 'heroicon-o-lock-closed',
                        'identity_confirmed' => 'heroicon-o-finger-print',
                        'share_created'      => 'heroicon-o-share',
                        'gallery_viewed'     => 'heroicon-o-eye',
                        'gallery_created'    => 'heroicon-o-plus-circle',
                        'image_approved'     => 'heroicon-o-check-circle',
                        'image_rated'        => 'heroicon-o-star',
                        default              => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'image_approved', 'gallery_submitted' => 'success',
                        'approved_pending'                                => 'warning',
                        'rated', 'image_rated'                            => 'warning',
                        'gallery_locked'                                  => 'danger',
                        'identity_confirmed', 'share_created',
                        'gallery_created'                                 => 'info',
                        default                                           => 'gray',
                    })
                    ->size('md'),

                Tables\Columns\TextColumn::make('occurred_at')
                    ->label('When')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'approved', 'image_approved'   => 'success',
                        'approved_pending'             => 'warning',
                        'cleared'                      => 'gray',
                        'rated', 'image_rated'         => 'info',
                        'gallery_submitted'            => 'success',
                        'gallery_locked'               => 'danger',
                        'identity_confirmed'           => 'info',
                        'share_created'                => 'primary',
                        'gallery_viewed'               => 'gray',
                        'gallery_created'              => 'primary',
                        default                        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('actor_type')
                    ->label('Actor')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'studio_user'    => 'primary',
                        'share_identity' => 'warning',
                        default          => 'gray',
                    }),

                Tables\Columns\TextColumn::make('actor_email')
                    ->label('Email')
                    ->searchable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('image.original_filename')
                    ->label('Image')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action_type')
                    ->label('Action')
                    ->options([
                        'gallery_created'    => 'Gallery Created',
                        'share_created'      => 'Share Created',
                        'identity_confirmed' => 'Identity Confirmed',
                        'gallery_viewed'     => 'Gallery Viewed',
                        'approved'           => 'Approved',
                        'approved_pending'   => 'Approved + Pending',
                        'cleared'            => 'Cleared',
                        'rated'              => 'Rated',
                        'gallery_submitted'  => 'Submitted',
                        'gallery_locked'     => 'Locked',
                    ]),

                Tables\Filters\SelectFilter::make('actor_type')
                    ->options([
                        'studio_user'    => 'Studio User',
                        'share_identity' => 'Client',
                    ]),
            ])
            ->defaultSort('occurred_at', 'desc')
            ->paginated([10, 25, 50])
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Share the gallery to get started — activity will appear here as clients interact with your images.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
