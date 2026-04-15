<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 7.1 — Share Management relation manager.
 *
 * Displayed on the EditGallery page. Provides:
 *  - Share link table with permissions, status, download/view stats
 *  - Revoke action (sets revoked_at, logs to activity ledger)
 *  - Extend action (updates expires_at via modal)
 *  - Toggle downloads action (flips can_download, logs)
 *  - Copy link action (provides URL in notification)
 *
 * All writes to gallery_shares (prophoto-gallery owned table).
 * All mutations logged to activity ledger via GalleryActivityLogger.
 */
class GalleryShareRelationManager extends RelationManager
{
    protected static string $relationship = 'shares';

    protected static ?string $title = 'Share Links';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-share';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('recipient')
                    ->label('Client')
                    ->getStateUsing(fn (GalleryShare $record): string =>
                        $record->confirmed_email ?? $record->shared_with_email ?? '—'
                    )
                    ->icon('heroicon-o-user')
                    ->searchable(['confirmed_email', 'shared_with_email']),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (GalleryShare $record): string => $this->resolveStatus($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Revoked'   => 'danger',
                        'Expired'   => 'warning',
                        'Submitted' => 'success',
                        'Active'    => 'info',
                        default     => 'gray',
                    }),

                Tables\Columns\IconColumn::make('can_download')
                    ->label('DL')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-down-tray')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\IconColumn::make('can_approve')
                    ->label('Approve')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-mark')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('download_count')
                    ->label('Downloads')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->description(fn (GalleryShare $record): ?string =>
                        $record->max_downloads
                            ? "of {$record->max_downloads}"
                            : null
                    ),

                Tables\Columns\TextColumn::make('access_count')
                    ->label('Views')
                    ->sortable()
                    ->alignCenter()
                    ->icon('heroicon-o-eye')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('last_accessed_at')
                    ->label('Last Active')
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->placeholder('Never')
                    ->color(fn (GalleryShare $record): string =>
                        ($record->expires_at && $record->expires_at->isPast()) ? 'danger' : 'gray'
                    ),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                $this->makeCopyLinkAction(),
                $this->makeToggleDownloadAction(),
                $this->makeExtendAction(),
                $this->makeRevokeAction(),
            ])
            ->emptyStateHeading('No shares yet')
            ->emptyStateDescription('Generate a share link to give clients access to this gallery.')
            ->emptyStateIcon('heroicon-o-share')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    // ── Row Actions ──────────────────────────────────────────────────────

    /**
     * Copy the share link URL to the user via notification.
     */
    protected function makeCopyLinkAction(): Action
    {
        return Action::make('copy_link')
            ->label('Copy Link')
            ->icon('heroicon-o-clipboard-document')
            ->color('gray')
            ->action(function (GalleryShare $record): void {
                $url = url("/g/{$record->share_token}");

                Notification::make()
                    ->title('Share link')
                    ->body($url)
                    ->success()
                    ->persistent()
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('open')
                            ->label('Open')
                            ->url($url)
                            ->openUrlInNewTab(),
                    ])
                    ->send();
            });
    }

    /**
     * Toggle the can_download permission on a share.
     */
    protected function makeToggleDownloadAction(): Action
    {
        return Action::make('toggle_download')
            ->label(fn (GalleryShare $record): string =>
                $record->can_download ? 'Disable Downloads' : 'Enable Downloads'
            )
            ->icon(fn (GalleryShare $record): string =>
                $record->can_download ? 'heroicon-o-x-mark' : 'heroicon-o-arrow-down-tray'
            )
            ->color(fn (GalleryShare $record): string =>
                $record->can_download ? 'warning' : 'success'
            )
            ->visible(fn (GalleryShare $record): bool => $record->revoked_at === null)
            ->requiresConfirmation()
            ->modalHeading(fn (GalleryShare $record): string =>
                $record->can_download ? 'Disable Downloads?' : 'Enable Downloads?'
            )
            ->modalDescription(fn (GalleryShare $record): string =>
                $record->can_download
                    ? 'The client will no longer be able to download images from this share link.'
                    : 'The client will be able to download full-resolution images from this share link.'
            )
            ->action(function (GalleryShare $record): void {
                $newValue = ! $record->can_download;
                $record->update(['can_download' => $newValue]);

                GalleryActivityLogger::log(
                    gallery: $record->gallery,
                    actionType: 'share_permission_changed',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                    galleryShareId: $record->id,
                    metadata: [
                        'permission'  => 'can_download',
                        'old_value'   => ! $newValue,
                        'new_value'   => $newValue,
                        'recipient'   => $record->shared_with_email,
                    ],
                );

                Notification::make()
                    ->title($newValue ? 'Downloads enabled' : 'Downloads disabled')
                    ->success()
                    ->send();
            });
    }

    /**
     * Extend the expiration date on a share.
     */
    protected function makeExtendAction(): Action
    {
        return Action::make('extend')
            ->label('Extend')
            ->icon('heroicon-o-calendar')
            ->color('primary')
            ->visible(fn (GalleryShare $record): bool => $record->revoked_at === null)
            ->modalHeading('Extend Share Expiration')
            ->modalDescription(fn (GalleryShare $record): string =>
                $record->expires_at
                    ? "Currently expires: {$record->expires_at->format('M j, Y g:i A')}"
                    : 'This share currently has no expiration.'
            )
            ->form([
                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('New Expiration Date')
                    ->required()
                    ->minDate(now())
                    ->helperText('Set a new expiration date for this share link.'),
            ])
            ->action(function (GalleryShare $record, array $data): void {
                $oldExpiry = $record->expires_at?->toIso8601String();
                $record->update(['expires_at' => $data['expires_at']]);

                GalleryActivityLogger::log(
                    gallery: $record->gallery,
                    actionType: 'share_extended',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                    galleryShareId: $record->id,
                    metadata: [
                        'old_expires_at' => $oldExpiry,
                        'new_expires_at' => $record->fresh()->expires_at?->toIso8601String(),
                        'recipient'      => $record->shared_with_email,
                    ],
                );

                Notification::make()
                    ->title('Share link extended')
                    ->success()
                    ->send();
            });
    }

    /**
     * Revoke a share link (soft disable, data preserved).
     */
    protected function makeRevokeAction(): Action
    {
        return Action::make('revoke')
            ->label('Revoke')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (GalleryShare $record): bool => $record->revoked_at === null)
            ->requiresConfirmation()
            ->modalHeading('Revoke Share Link')
            ->modalDescription('The client will no longer be able to access the gallery via this share link. This cannot be undone. Download and view history is preserved.')
            ->action(function (GalleryShare $record): void {
                $record->update([
                    'revoked_at'         => now(),
                    'revoked_by_user_id' => auth()->id(),
                ]);

                GalleryActivityLogger::log(
                    gallery: $record->gallery,
                    actionType: 'share_revoked',
                    actorType: 'studio_user',
                    actorEmail: auth()->user()?->email,
                    galleryShareId: $record->id,
                    metadata: [
                        'recipient' => $record->shared_with_email,
                    ],
                );

                Notification::make()
                    ->title('Share link revoked')
                    ->body("Access for {$record->shared_with_email} has been revoked.")
                    ->success()
                    ->send();
            });
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /**
     * Resolve the display status for a share.
     */
    protected function resolveStatus(GalleryShare $record): string
    {
        if ($record->revoked_at !== null) {
            return 'Revoked';
        }

        if ($record->expires_at && $record->expires_at->isPast()) {
            return 'Expired';
        }

        if ($record->submitted_at !== null) {
            return 'Submitted';
        }

        return 'Active';
    }
}
