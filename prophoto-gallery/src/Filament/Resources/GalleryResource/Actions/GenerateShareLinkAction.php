<?php

namespace ProPhoto\Gallery\Filament\Resources\GalleryResource\Actions;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Services\GalleryActivityLogger;

/**
 * Story 3.1 — "Generate Share Link" table action.
 *
 * Opens a modal to create a GalleryShare with a unique token.
 * Permissions are auto-configured based on gallery type:
 *   - Proofing: can_view, can_approve, can_comment = true
 *   - Presentation: can_view = true, everything else false
 *
 * Logs the share creation to the activity ledger.
 * Returns a copyable URL in the success notification.
 */
class GenerateShareLinkAction extends Action
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->name('generate_share_link')
            ->label('Share')
            ->icon('heroicon-o-share')
            ->color('success')
            ->modalHeading('Generate Share Link')
            ->modalDescription('Create a share link for this gallery. The recipient will receive the URL to access it.')
            ->modalSubmitActionLabel('Generate Link')
            ->form([
                TextInput::make('shared_with_email')
                    ->label('Recipient Email')
                    ->email()
                    ->required()
                    ->placeholder('client@example.com'),

                Textarea::make('message')
                    ->label('Message (optional)')
                    ->placeholder('Please review and approve your favorites!')
                    ->rows(3),

                Toggle::make('can_download')
                    ->label('Allow Downloads')
                    ->default(false)
                    ->helperText('Let the recipient download full-resolution images.'),

                DateTimePicker::make('expires_at')
                    ->label('Expires At (optional)')
                    ->placeholder('Never')
                    ->helperText('Leave blank for no expiration.')
                    ->minDate(now()),
            ])
            ->action(function (array $data, Gallery $record): void {
                $this->createShareLink($record, $data);
            });
    }

    protected function createShareLink(Gallery $record, array $data): void
    {
        $isProofing = $record->isProofing();

        $share = GalleryShare::create([
            'gallery_id'        => $record->id,
            'shared_by_user_id' => auth()->id(),
            'shared_with_email' => $data['shared_with_email'],
            'can_view'          => true,
            'can_download'      => (bool) ($data['can_download'] ?? false),
            'can_approve'       => $isProofing,
            'can_comment'       => $isProofing,
            'can_share'         => false,
            'message'           => $data['message'] ?? null,
            'expires_at'        => $data['expires_at'] ?? null,
        ]);

        // Log to activity ledger
        GalleryActivityLogger::log(
            gallery: $record,
            actionType: 'share_created',
            actorType: 'studio_user',
            actorEmail: auth()->user()?->email,
            galleryShareId: $share->id,
            metadata: [
                'recipient'    => $data['shared_with_email'],
                'gallery_type' => $record->type,
                'can_download' => $share->can_download,
                'expires_at'   => $share->expires_at?->toIso8601String(),
            ],
        );

        $url = url("/g/{$share->share_token}");

        Notification::make()
            ->title('Share link created')
            ->body("Link: {$url}")
            ->success()
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('copy')
                    ->label('Copy URL')
                    ->url($url)
                    ->openUrlInNewTab(),
            ])
            ->send();
    }
}
