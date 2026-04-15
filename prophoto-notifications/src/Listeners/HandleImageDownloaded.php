<?php

namespace ProPhoto\Notifications\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\ImageDownloaded;
use ProPhoto\Notifications\Mail\DownloadNotificationMail;
use ProPhoto\Notifications\Models\Message;

/**
 * Story 6.2 — React to a client downloading an image via a share link.
 *
 * Follows the HandleGallerySubmitted template exactly:
 *   1. Resolves the recipient (share creator → studio admin fallback)
 *   2. Sends the download notification email
 *   3. Creates a Message record for audit trail
 *   4. Sends a Filament database notification for the bell icon
 */
class HandleImageDownloaded
{
    public function handle(ImageDownloaded $event): void
    {
        $recipient = $this->resolveRecipient($event);

        if (! $recipient) {
            Log::warning('No notification recipient found for image download', [
                'gallery_id' => $event->galleryId,
                'image_id'   => $event->imageId,
                'studio_id'  => $event->studioId,
            ]);
            return;
        }

        $dashboardUrl = $this->buildDashboardUrl($event->galleryId);

        // Send the email
        Mail::to($recipient->email)
            ->send(new DownloadNotificationMail($event, $dashboardUrl));

        // Create audit trail Message record
        $downloadStats = $event->shareMaxDownloads
            ? "{$event->shareDownloadCount} of {$event->shareMaxDownloads} downloads used"
            : "{$event->shareDownloadCount} downloads";

        Message::create([
            'studio_id'         => $event->studioId,
            'recipient_user_id' => $recipient->id,
            'gallery_id'        => $event->galleryId,
            'image_id'          => $event->imageId,
            'subject'           => "Image Downloaded: {$event->galleryName}",
            'body'              => "{$event->downloadedByEmail} downloaded {$event->imageFilename} "
                                 . "from {$event->galleryName}. {$downloadStats}.",
        ]);

        // Send Filament database notification (bell icon)
        $this->sendFilamentNotification($event, $recipient, $dashboardUrl);

        Log::info('Image download notification sent', [
            'gallery_id'    => $event->galleryId,
            'image_id'      => $event->imageId,
            'recipient'     => $recipient->email,
            'downloaded_by' => $event->downloadedByEmail,
        ]);
    }

    /**
     * Send a Filament database notification for the in-app bell icon.
     *
     * Gracefully skips if Filament notifications aren't installed.
     */
    private function sendFilamentNotification(
        ImageDownloaded $event,
        object $recipient,
        string $dashboardUrl,
    ): void {
        if (! class_exists(\Filament\Notifications\Notification::class)) {
            return;
        }

        if (! method_exists($recipient, 'notify')) {
            return;
        }

        try {
            $downloadStats = $event->shareMaxDownloads
                ? "{$event->shareDownloadCount}/{$event->shareMaxDownloads}"
                : "{$event->shareDownloadCount} total";

            \Filament\Notifications\Notification::make()
                ->title('Image Downloaded')
                ->icon('heroicon-o-arrow-down-tray')
                ->iconColor('info')
                ->body("{$event->downloadedByEmail} downloaded {$event->imageFilename} from {$event->galleryName} ({$downloadStats})")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View Gallery')
                        ->url($dashboardUrl)
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipient);
        } catch (\Throwable $e) {
            Log::warning('Filament database notification failed for download', [
                'gallery_id' => $event->galleryId,
                'image_id'   => $event->imageId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve who should receive the notification.
     *
     * Priority:
     *   1. The user who created the share link (shared_by_user_id)
     *   2. The first user of the studio
     */
    private function resolveRecipient(ImageDownloaded $event): ?object
    {
        $userModel = config('auth.providers.users.model');

        if ($event->sharedByUserId) {
            $user = $userModel::find($event->sharedByUserId);
            if ($user) {
                return $user;
            }
        }

        return $userModel::where('studio_id', $event->studioId)->first();
    }

    /**
     * Build the Filament admin URL for the gallery edit page.
     */
    private function buildDashboardUrl(int $galleryId): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');

        return "{$baseUrl}/admin/galleries/{$galleryId}/edit";
    }
}
