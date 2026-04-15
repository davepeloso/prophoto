<?php

namespace ProPhoto\Notifications\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\GalleryViewed;
use ProPhoto\Notifications\Listeners\HandleGalleryViewed;
use ProPhoto\Notifications\Mail\GalleryViewedMail;
use ProPhoto\Notifications\Models\Message;
use ProPhoto\Notifications\Tests\TestCase;

/**
 * Story 6.3 — Gallery Viewed Notification tests.
 *
 * Verifies:
 *  1. Email sent on first view
 *  2. Email sent on milestone view
 *  3. First view has correct subject
 *  4. Milestone has correct subject
 *  5. No email when no recipient
 *  6. Message record created
 *  7. Email carries correct data
 *  8. Filament notification graceful degradation
 */
class GalleryViewedNotificationTest extends TestCase
{
    private function makeEvent(array $overrides = []): GalleryViewed
    {
        return new GalleryViewed(...array_merge([
            'galleryId'      => 1,
            'galleryShareId' => 1,
            'studioId'       => 1,
            'galleryName'    => 'Smith Wedding',
            'viewedByEmail'  => 'bride@example.com',
            'viewCount'      => 1,
            'viewedAt'       => now()->toIso8601String(),
            'sharedByUserId' => null,
        ], $overrides));
    }

    // ── Email Delivery ───────────────────────────────────────────────────

    public function test_email_sent_on_first_view(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'viewCount'      => 1,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_email_sent_on_milestone_view(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'viewCount'      => 10,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class);
    }

    // ── Email Subject ────────────────────────────────────────────────────

    public function test_first_view_has_correct_subject(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Johnson Portraits',
            'viewCount'      => 1,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class, function ($mail) {
            return $mail->envelope()->subject === 'Gallery Viewed: Johnson Portraits';
        });
    }

    public function test_milestone_has_correct_subject(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Johnson Portraits',
            'viewCount'      => 25,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class, function ($mail) {
            return $mail->envelope()->subject === 'Gallery Milestone: Johnson Portraits — 25 views';
        });
    }

    // ── No Recipient ─────────────────────────────────────────────────────

    public function test_no_email_sent_when_no_recipient(): void
    {
        Mail::fake();
        Log::spy();

        $studioId = $this->makeStudio();

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => null,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    // ── Message Audit Trail ──────────────────────────────────────────────

    public function test_message_record_created_for_first_view(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

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
            'viewCount'      => 1,
        ]);

        (new HandleGalleryViewed())->handle($event);

        $this->assertDatabaseHas('messages', [
            'studio_id'         => $studioId,
            'recipient_user_id' => $user->id,
            'gallery_id'        => $galleryId,
            'subject'           => 'Gallery Viewed: Test Gallery',
        ]);
    }

    public function test_message_record_created_for_milestone(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

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
            'viewCount'      => 50,
        ]);

        (new HandleGalleryViewed())->handle($event);

        $this->assertDatabaseHas('messages', [
            'subject' => 'Gallery Milestone: Test Gallery — 50 views',
        ]);
    }

    // ── Email Data ───────────────────────────────────────────────────────

    public function test_email_carries_correct_data(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'viewedByEmail'  => 'jane@example.com',
            'viewCount'      => 5,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class, function ($mail) {
            return $mail->viewedByEmail === 'jane@example.com'
                && $mail->viewCount === 5
                && $mail->isFirstView === false;
        });
    }

    // ── Filament Notification ────────────────────────────────────────────

    public function test_filament_notification_skipped_gracefully(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        (new HandleGalleryViewed())->handle($event);

        Mail::assertSent(GalleryViewedMail::class);
    }
}
