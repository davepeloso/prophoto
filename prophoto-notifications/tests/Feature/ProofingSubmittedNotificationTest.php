<?php

namespace ProPhoto\Notifications\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\GallerySubmitted;
use ProPhoto\Notifications\Listeners\HandleGallerySubmitted;
use ProPhoto\Notifications\Mail\ProofingSubmittedMail;
use ProPhoto\Notifications\Models\Message;
use ProPhoto\Notifications\Tests\TestCase;

class ProofingSubmittedNotificationTest extends TestCase
{
    private function makeEvent(array $overrides = []): GallerySubmitted
    {
        return new GallerySubmitted(...array_merge([
            'galleryId'        => 1,
            'galleryShareId'   => 1,
            'studioId'         => 1,
            'galleryName'      => 'April 2026 Shoot',
            'submittedByEmail' => 'client@example.com',
            'approvedCount'    => 8,
            'pendingCount'     => 2,
            'totalImages'      => 15,
            'submittedAt'      => now()->toIso8601String(),
            'sharedByUserId'   => null,
        ], $overrides));
    }

    // ── Email Delivery ───────────────────────────────────────────────────

    public function test_email_sent_to_share_creator(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertSent(ProofingSubmittedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_email_falls_back_to_studio_user_when_no_share_creator(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => null,
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertSent(ProofingSubmittedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_no_email_sent_when_no_recipient_found(): void
    {
        Mail::fake();
        Log::spy();

        $studioId = $this->makeStudio();
        // No users created for this studio

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => null,
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_email_contains_correct_subject(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Smith Wedding',
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertSent(ProofingSubmittedMail::class, function ($mail) {
            return $mail->envelope()->subject === 'Proofing Submitted: Smith Wedding';
        });
    }

    public function test_email_has_correct_data(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'         => $studioId,
            'sharedByUserId'   => $user->id,
            'approvedCount'    => 12,
            'pendingCount'     => 3,
            'totalImages'      => 20,
            'submittedByEmail' => 'jane@example.com',
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertSent(ProofingSubmittedMail::class, function ($mail) {
            return $mail->approvedCount === 12
                && $mail->pendingCount === 3
                && $mail->totalImages === 20
                && $mail->submittedByEmail === 'jane@example.com';
        });
    }

    // ── Message Audit Trail ──────────────────────────────────────────────

    public function test_message_record_created_on_send(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        // Create a gallery row so the FK is satisfied
        $galleryId = (int) $this->app['db']->connection()->table('galleries')->insertGetId([
            'studio_id'    => $studioId,
            'subject_name' => 'Test Gallery',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $event = $this->makeEvent([
            'galleryId'      => $galleryId,
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Test Gallery',
        ]);

        (new HandleGallerySubmitted())->handle($event);

        $this->assertDatabaseHas('messages', [
            'studio_id'         => $studioId,
            'recipient_user_id' => $user->id,
            'gallery_id'        => $galleryId,
            'subject'           => 'Proofing Submitted: Test Gallery',
        ]);
    }

    public function test_no_message_created_when_no_recipient(): void
    {
        Mail::fake();
        Log::spy();

        $studioId = $this->makeStudio();

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => null,
        ]);

        (new HandleGallerySubmitted())->handle($event);

        $this->assertDatabaseCount('messages', 0);
    }

    // ── Dashboard URL ────────────────────────────────────────────────────

    public function test_email_contains_dashboard_url(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'galleryId'      => 42,
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        (new HandleGallerySubmitted())->handle($event);

        Mail::assertSent(ProofingSubmittedMail::class, function ($mail) {
            return $mail->dashboardUrl === 'http://prophoto-app.test/admin/galleries/42/edit';
        });
    }

    // ── Filament Notification (5.4) ──────────────────────────────────────

    public function test_filament_notification_skipped_gracefully_when_not_installed(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        // Should not throw even if Filament isn't installed.
        // The listener guards with class_exists() before attempting.
        (new HandleGallerySubmitted())->handle($event);

        // Email still sent regardless of Filament availability
        Mail::assertSent(ProofingSubmittedMail::class);
    }
}
