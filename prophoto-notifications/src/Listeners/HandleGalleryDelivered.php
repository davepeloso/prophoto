<?php

namespace ProPhoto\Notifications\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\GalleryDelivered;
use ProPhoto\Notifications\Mail\GalleryDeliveredMail;
use ProPhoto\Notifications\Models\Message;

/**
 * Story 7.5 — React to a gallery being marked as delivered.
 *
 * Sends a "Your Gallery is Ready" email to EVERY active share — not just one.
 * Each client who has access gets notified.
 *
 * Flow per active share:
 *   1. Build viewer URL from share token
 *   2. Send email to the share's original recipient (shared_with_email)
 *   3. Create Message record for audit trail
 *
 * Also sends a Filament bell notification to the photographer (confirmation).
 */
class HandleGalleryDelivered
{
    public function handle(GalleryDelivered $event): void
    {
        if (empty($event->activeShares)) {
            Log::info('Gallery delivered with no active shares — no notifications sent', [
                'gallery_id' => $event->galleryId,
            ]);
            return;
        }

        $recipientCount = 0;

        foreach ($event->activeShares as $share) {
            $email      = $share['email'] ?? null;
            $shareToken = $share['share_token'] ?? null;
            $shareId    = $share['share_id'] ?? null;

            if (! $email || ! $shareToken) {
                Log::warning('Skipping delivery notification — missing email or token', [
                    'gallery_id' => $event->galleryId,
                    'share_id'   => $shareId,
                ]);
                continue;
            }

            $viewerUrl = $this->buildViewerUrl($shareToken);

            // Send email to the client
            Mail::to($email)->send(new GalleryDeliveredMail(
                galleryName:     $event->galleryName,
                deliveryMessage: $event->deliveryMessage,
                viewerUrl:       $viewerUrl,
                deliveredAt:     $event->deliveredAt,
            ));

            // Create Message record for audit trail
            Message::create([
                'studio_id'         => $event->studioId,
                'recipient_user_id' => null, // Client, not a studio user
                'gallery_id'        => $event->galleryId,
                'subject'           => "Your Gallery is Ready: {$event->galleryName}",
                'body'              => "Gallery delivered notification sent to {$email}."
                                     . ($event->deliveryMessage ? " Message: {$event->deliveryMessage}" : ''),
            ]);

            $recipientCount++;
        }

        Log::info('Gallery delivery notifications sent', [
            'gallery_id'      => $event->galleryId,
            'recipients_count' => $recipientCount,
        ]);

        // Send Filament bell notification to the photographer (confirmation)
        $this->sendFilamentNotification($event, $recipientCount);
    }

    /**
     * Send a Filament database notification to the photographer confirming delivery.
     */
    private function sendFilamentNotification(GalleryDelivered $event, int $recipientCount): void
    {
        if (! class_exists(\Filament\Notifications\Notification::class)) {
            return;
        }

        if (! $event->deliveredByUserId) {
            return;
        }

        $userModel = config('auth.providers.users.model');
        $user = $userModel::find($event->deliveredByUserId);

        if (! $user || ! method_exists($user, 'notify')) {
            return;
        }

        try {
            $dashboardUrl = $this->buildDashboardUrl($event->galleryId);

            \Filament\Notifications\Notification::make()
                ->title('Gallery Delivered')
                ->icon('heroicon-o-paper-airplane')
                ->iconColor('success')
                ->body("{$event->galleryName} delivered — {$recipientCount} " . ($recipientCount === 1 ? 'client' : 'clients') . ' notified')
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View Gallery')
                        ->url($dashboardUrl)
                        ->markAsRead(),
                ])
                ->sendToDatabase($user);
        } catch (\Throwable $e) {
            Log::warning('Filament database notification failed for gallery delivery', [
                'gallery_id' => $event->galleryId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the public viewer URL for a share token.
     */
    private function buildViewerUrl(string $shareToken): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');

        return "{$baseUrl}/g/{$shareToken}";
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
