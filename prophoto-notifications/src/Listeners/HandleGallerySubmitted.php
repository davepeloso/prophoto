<?php

namespace ProPhoto\Notifications\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\GallerySubmitted;
use ProPhoto\Notifications\Mail\ProofingSubmittedMail;
use ProPhoto\Notifications\Models\Message;

/**
 * Story 5.1 + 5.2 + 5.4 — React to a client submitting proofing selections.
 *
 * Resolves the recipient (the studio user who created the share link,
 * or a fallback studio admin), then:
 *   1. Sends the submission email (5.2)
 *   2. Creates a Message record for audit trail (5.2)
 *   3. Sends a Filament database notification for the bell icon (5.4)
 */
class HandleGallerySubmitted
{
    public function handle(GallerySubmitted $event): void
    {
        $recipient = $this->resolveRecipient($event);

        if (! $recipient) {
            Log::warning('No notification recipient found for gallery submission', [
                'gallery_id' => $event->galleryId,
                'studio_id'  => $event->studioId,
            ]);
            return;
        }

        $dashboardUrl = $this->buildDashboardUrl($event->galleryId);

        // 5.2 — Send the email
        Mail::to($recipient->email)
            ->send(new ProofingSubmittedMail($event, $dashboardUrl));

        // 5.2 — Create audit trail Message record
        Message::create([
            'studio_id'        => $event->studioId,
            'recipient_user_id' => $recipient->id,
            'gallery_id'       => $event->galleryId,
            'subject'          => "Proofing Submitted: {$event->galleryName}",
            'body'             => "{$event->submittedByEmail} submitted selections for {$event->galleryName}. "
                                . "{$event->approvedCount} of {$event->totalImages} images approved.",
        ]);

        // 5.4 — Send Filament database notification (bell icon)
        $this->sendFilamentNotification($event, $recipient, $dashboardUrl);

        Log::info('Proofing submission notification sent', [
            'gallery_id'   => $event->galleryId,
            'recipient'    => $recipient->email,
            'submitted_by' => $event->submittedByEmail,
        ]);
    }

    /**
     * Send a Filament database notification for the in-app bell icon.
     *
     * Gracefully skips if Filament notifications aren't installed.
     * This keeps the notifications package decoupled from Filament —
     * the email and Message record are always sent regardless.
     */
    private function sendFilamentNotification(
        GallerySubmitted $event,
        object $recipient,
        string $dashboardUrl,
    ): void {
        // Guard: skip if Filament's Notification class isn't available
        if (! class_exists(\Filament\Notifications\Notification::class)) {
            return;
        }

        // Guard: skip if user model doesn't support database notifications
        if (! method_exists($recipient, 'notify')) {
            return;
        }

        try {
            $summary = "{$event->approvedCount} of {$event->totalImages} approved";

            \Filament\Notifications\Notification::make()
                ->title('Proofing Submitted')
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->body("{$event->submittedByEmail} submitted selections for {$event->galleryName} ({$summary})")
                ->actions([
                    \Filament\Notifications\Actions\Action::make('view')
                        ->label('View Gallery')
                        ->url($dashboardUrl)
                        ->markAsRead(),
                ])
                ->sendToDatabase($recipient);
        } catch (\Throwable $e) {
            // Don't let Filament notification failure break the email flow
            Log::warning('Filament database notification failed', [
                'gallery_id' => $event->galleryId,
                'error'      => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve who should receive the notification.
     *
     * Priority:
     *   1. The user who created the share link (shared_by_user_id)
     *   2. The first admin user of the studio
     *
     * Returns null if no valid recipient can be found.
     */
    private function resolveRecipient(GallerySubmitted $event): ?object
    {
        $userModel = config('auth.providers.users.model');

        // First: the user who created the share link
        if ($event->sharedByUserId) {
            $user = $userModel::find($event->sharedByUserId);
            if ($user) {
                return $user;
            }
        }

        // Fallback: first user with studio access
        // Uses the studio_id to find associated users.
        // This is a simple fallback — future sprints can add
        // notification preferences and role-based routing.
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
