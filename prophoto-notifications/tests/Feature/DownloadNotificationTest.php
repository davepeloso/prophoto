<?php

namespace ProPhoto\Notifications\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use ProPhoto\Gallery\Events\ImageDownloaded;
use ProPhoto\Notifications\Listeners\HandleImageDownloaded;
use ProPhoto\Notifications\Mail\DownloadNotificationMail;
use ProPhoto\Notifications\Models\Message;
use ProPhoto\Notifications\Tests\TestCase;

/**
 * Story 6.2 — Download Notification tests.
 *
 * Verifies:
 *  1. Email sent to share creator on download
 *  2. Email falls back to studio user when no share creator
 *  3. No email sent when no recipient found
 *  4. Email has correct subject line
 *  5. Email has correct data (filename, counts, etc.)
 *  6. Message record created for audit trail
 *  7. No message created when no recipient
 *  8. Email contains dashboard URL
 *  9. Download stats formatted correctly (with and without limit)
 * 10. Filament notification skipped gracefully when not installed
 */
class DownloadNotificationTest extends TestCase
{
    private function makeEvent(array $overrides = []): ImageDownloaded
    {
        return new ImageDownloaded(...array_merge([
            'galleryId'            => 1,
            'galleryShareId'       => 1,
            'studioId'             => 1,
            'galleryName'          => 'Smith Wedding',
            'imageId'              => 10,
            'imageFilename'        => 'IMG_4521.jpg',
            'downloadedByEmail'    => 'bride@example.com',
            'shareDownloadCount'   => 3,
            'shareMaxDownloads'    => 10,
            'galleryDownloadCount' => 7,
            'downloadedAt'         => now()->toIso8601String(),
            'sharedByUserId'       => null,
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

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) use ($user) {
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

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_no_email_sent_when_no_recipient_found(): void
    {
        Mail::fake();
        Log::spy();

        $studioId = $this->makeStudio();

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => null,
        ]);

        (new HandleImageDownloaded())->handle($event);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    // ── Email Content ────────────────────────────────────────────────────

    public function test_email_contains_correct_subject(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Johnson Portraits',
        ]);

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) {
            return $mail->envelope()->subject === 'Image Downloaded: Johnson Portraits';
        });
    }

    public function test_email_has_correct_data(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'             => $studioId,
            'sharedByUserId'       => $user->id,
            'imageFilename'        => 'DSC_9876.jpg',
            'downloadedByEmail'    => 'jane@example.com',
            'shareDownloadCount'   => 5,
            'shareMaxDownloads'    => 20,
            'galleryDownloadCount' => 12,
        ]);

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) {
            return $mail->imageFilename === 'DSC_9876.jpg'
                && $mail->downloadedByEmail === 'jane@example.com'
                && $mail->shareDownloadCount === 5
                && $mail->shareMaxDownloads === 20
                && $mail->galleryDownloadCount === 12;
        });
    }

    public function test_email_data_with_no_download_limit(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'           => $studioId,
            'sharedByUserId'     => $user->id,
            'shareDownloadCount' => 3,
            'shareMaxDownloads'  => null,
        ]);

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) {
            return $mail->shareMaxDownloads === null
                && $mail->shareDownloadCount === 3;
        });
    }

    // ── Message Audit Trail ──────────────────────────────────────────────

    public function test_message_record_created_on_send(): void
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

        $imageId = (int) $this->app['db']->connection()->table('images')->insertGetId([
            'gallery_id' => $galleryId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $event = $this->makeEvent([
            'galleryId'      => $galleryId,
            'imageId'        => $imageId,
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
            'galleryName'    => 'Test Gallery',
            'imageFilename'  => 'photo.jpg',
        ]);

        (new HandleImageDownloaded())->handle($event);

        $this->assertDatabaseHas('messages', [
            'studio_id'         => $studioId,
            'recipient_user_id' => $user->id,
            'gallery_id'        => $galleryId,
            'image_id'          => $imageId,
            'subject'           => 'Image Downloaded: Test Gallery',
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

        (new HandleImageDownloaded())->handle($event);

        $this->assertDatabaseCount('messages', 0);
    }

    // ── Dashboard URL ────────────────────────────────────────────────────

    public function test_email_contains_dashboard_url(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'galleryId'      => 99,
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        (new HandleImageDownloaded())->handle($event);

        Mail::assertSent(DownloadNotificationMail::class, function ($mail) {
            return $mail->dashboardUrl === 'http://prophoto-app.test/admin/galleries/99/edit';
        });
    }

    // ── Filament Notification ────────────────────────────────────────────

    public function test_filament_notification_skipped_gracefully_when_not_installed(): void
    {
        Mail::fake();

        $studioId = $this->makeStudio();
        $user     = $this->makeUser($studioId);

        $event = $this->makeEvent([
            'studioId'       => $studioId,
            'sharedByUserId' => $user->id,
        ]);

        // Should not throw even if Filament isn't installed
        (new HandleImageDownloaded())->handle($event);

        // Email still sent regardless
        Mail::assertSent(DownloadNotificationMail::class);
    }
}
