<?php

namespace ProPhoto\Notifications\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\GalleryViewed;
use ProPhoto\Notifications\Mail\GalleryViewedMail;
use ProPhoto\Notifications\Models\Message;

/**
 * Story 6.3 — React to a client viewing a gallery at a notification threshold.
 *
 * Only fires on milestone views (1st, 5th, 10th, 25th, 50th) — not every load.
 * Follows the HandleGallerySubmitted template:
 *   1. Resolve recipient (share creator → studio admin fallback)
 *   2. Send email
 *   3. Create Message record for audit trail
 *   4. Send Filament database notification (bell icon)
 */
class HandleGalleryViewed
{
    public function handle(GalleryViewed $event): void
    {
        $recipient = $this->resolveRecipient($event);

        if (! $recipient) {
            Log::warning('No notification recipient found for gallery view', [
                'gallery_id' => $event->galleryId,
                'studio_id'  => $event->studioId,
            ]);
            return;
        }

        $dashboardUrl = $this->buildDashboardUrl($event->galleryId);
        $isFirstView  = $event->viewCount === 1;

        // Send the email
        Mail::to($recipient->email)
            ->send(new GalleryViewedMail($event, $dashboardUrl));

        // Create audit trail Message record
        $subject = $isFirstView
            ? "Gallery Viewed: {$event->galleryName}"
            : "Gallery Milestone: {$event->galleryName} — {$event->viewCount} views";

        Message::create([
            'studio_id'         => $event->studioId,
            'recipient_user_id' => $recipient->id,
            'gallery_id'        => $event->galleryId,
            'subject'           => $subject,
            'body'              => "{$event->viewedByEmail} viewed {$event->galleryName}. "
                                 . "Total views: {$event->viewCount}.",
        ]);

        // Send Filament database notification (bell icon)
        $this->sendFilamentNotification($event, $recipient, $dashboardUrl, $isFirstView);

        Log::info('Gallery view notification sent', [
            'gallery_id' => $event->galleryId,
            'recipient'  => $recipient->email,
            'viewed_by'  => $event->viewedByEmail,
            'view_count' => $event->viewCount,
        ]);
    }

    /**
     * Send a Filament database notification for the in-app bell icon.
     */
    private function sendFilamentNotification(
        GalleryViewed $event,
        object $recipient,
        string $dashboardUrl,
        bool $isFirstView,
    ): void {
        if (! class_exists(\Filament\Notifications\Notification::class)) {
            return;
        }

        if (! method_exists($recipient, 'notify')) {
            return;
        }

        try {
            $title = $isFirstView ? 'Gallery Viewed' : 'Gallery Milestone';
            $body  = $isFirstView
                ? "{$event->viewedByEmail} just viewed {$event->galleryName} for the first time"
                : "{$event->galleryName} has been viewed {$event->viewCount} times by {$event->viewedByEmail}";

            \Filament\Notifications\Notification::make()
                ->title($title)
                ->icon($isFirstView ? 'heroicon-o-eye' : 'heroicon-o-chart-bar')
                ->iconColor($isFirstView ? 'success' : 'info')
                ->body($body)
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View Gallery')
                        ->url($dashboardUrl)
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipient);
        } catch (\Throwable $e) {
            Log::warning('Filament database notification failed for gallery view', [
                'gallery_id' => $event->galleryId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve who should receive the notification.
     */
    private function resolveRecipient(GalleryViewed $event): ?object
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
