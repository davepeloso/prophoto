<?php

namespace ProPhoto\Gallery\Tests\Feature;

use Illuminate\Support\Facades\Event;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Gallery\Events\ImageDownloaded;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryAccessLog;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 6.1 — Download Controller tests.
 *
 * Verifies:
 *  1. Successful download redirects to image URL
 *  2. Download blocked when can_download is false
 *  3. Download blocked when max_downloads reached
 *  4. Download blocked for expired share
 *  5. Download blocked for revoked share
 *  6. Download returns 404 for image not in gallery
 *  7. Download returns 404 for invalid token
 *  8. Download counters increment on success
 *  9. Activity log entry created on download
 * 10. Access log entry created on download
 * 11. ImageDownloaded event dispatched on success
 * 12. Event NOT dispatched when download blocked
 * 13. canDownload() helper works correctly
 * 14. hasReachedMaxDownloads() helper works correctly
 */
class DownloadControllerTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Smith Wedding',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PROOFING,
            'status'          => Gallery::STATUS_ACTIVE,
            'image_count'     => 2,
            'download_count'  => 0,
        ], $attrs));
    }

    private function makeShare(Gallery $gallery, array $attrs = []): GalleryShare
    {
        return GalleryShare::create(array_merge([
            'gallery_id'            => $gallery->id,
            'shared_by_user_id'     => 1,
            'shared_with_email'     => 'client@example.com',
            'confirmed_email'       => 'client@example.com',
            'identity_confirmed_at' => now(),
            'can_view'              => true,
            'can_download'          => true,
            'can_approve'           => true,
            'can_comment'           => false,
            'can_share'             => false,
            'download_count'        => 0,
        ], $attrs));
    }

    private function makeAsset(string $filename = 'IMG_001.jpg'): Asset
    {
        return Asset::create([
            'studio_id'            => 1,
            'type'                 => 'image',
            'original_filename'    => $filename,
            'mime_type'            => 'image/jpeg',
            'bytes'                => 1024000,
            'checksum_sha256'      => hash('sha256', uniqid()),
            'storage_driver'       => 'local',
            'storage_key_original' => "ingest/test/{$filename}",
            'logical_path'         => "studio/1/{$filename}",
            'status'               => 'ready',
        ]);
    }

    private function makeImage(Gallery $gallery, string $filename = 'IMG_001.jpg'): Image
    {
        $asset = $this->makeAsset($filename);

        return Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $asset->id,
            'filename'          => $filename,
            'original_filename' => $filename,
            'imagekit_url'      => "https://ik.imagekit.io/test/galleries/{$gallery->id}/{$filename}",
            'sort_order'        => 0,
        ]);
    }

    // ── Tests: Successful Download ───────────────────────────────────────

    public function test_successful_download_redirects_to_image_url(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        // Should redirect (302) to the resolved image URL
        $response->assertStatus(302);
    }

    public function test_download_increments_share_download_count(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $this->assertEquals(0, $share->download_count);

        $this->get("/g/{$share->share_token}/download/{$image->id}");

        $share->refresh();
        $this->assertEquals(1, $share->download_count);
    }

    public function test_download_increments_gallery_download_count(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $this->assertEquals(0, $gallery->download_count);

        $this->get("/g/{$share->share_token}/download/{$image->id}");

        $gallery->refresh();
        $this->assertEquals(1, $gallery->download_count);
    }

    public function test_download_creates_activity_log_entry(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $this->get("/g/{$share->share_token}/download/{$image->id}");

        $this->assertDatabaseHas('gallery_activity_log', [
            'gallery_id'       => $gallery->id,
            'gallery_share_id' => $share->id,
            'image_id'         => $image->id,
            'action_type'      => 'download',
            'actor_email'      => 'client@example.com',
        ]);
    }

    public function test_download_creates_access_log_entry(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $this->get("/g/{$share->share_token}/download/{$image->id}");

        $this->assertDatabaseHas('gallery_access_logs', [
            'gallery_id'    => $gallery->id,
            'action'        => GalleryAccessLog::ACTION_DOWNLOAD,
            'resource_type' => 'share',
            'resource_id'   => $share->id,
        ]);
    }

    public function test_download_dispatches_image_downloaded_event(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $this->get("/g/{$share->share_token}/download/{$image->id}");

        Event::assertDispatched(ImageDownloaded::class, function ($event) use ($gallery, $share, $image) {
            return $event->galleryId === $gallery->id
                && $event->galleryShareId === $share->id
                && $event->imageId === $image->id
                && $event->downloadedByEmail === 'client@example.com'
                && $event->shareDownloadCount === 1
                && $event->studioId === 1;
        });
    }

    // ── Tests: Permission Denied ─────────────────────────────────────────

    public function test_download_blocked_when_can_download_is_false(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['can_download' => false]);
        $image   = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(403);
        Event::assertNotDispatched(ImageDownloaded::class);
    }

    public function test_download_blocked_when_max_downloads_reached(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'max_downloads'  => 5,
            'download_count' => 5,
        ]);
        $image = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(403);
        Event::assertNotDispatched(ImageDownloaded::class);
    }

    public function test_download_allowed_when_under_max_downloads(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'max_downloads'  => 5,
            'download_count' => 4,
        ]);
        $image = $this->makeImage($gallery);

        Event::fake([ImageDownloaded::class]);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(302);
        Event::assertDispatched(ImageDownloaded::class);
    }

    // ── Tests: Invalid Share ─────────────────────────────────────────────

    public function test_download_returns_410_for_expired_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'expires_at' => now()->subDay(),
        ]);
        $image = $this->makeImage($gallery);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(410);
    }

    public function test_download_returns_410_for_revoked_share(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'revoked_at' => now()->subHour(),
        ]);
        $image = $this->makeImage($gallery);

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(410);
    }

    public function test_download_returns_404_for_invalid_token(): void
    {
        $response = $this->get('/g/nonexistent-token-12345/download/1');

        $response->assertStatus(404);
    }

    public function test_download_returns_404_for_image_not_in_gallery(): void
    {
        $gallery1 = $this->makeGallery(['subject_name' => 'Gallery 1']);
        $gallery2 = $this->makeGallery(['subject_name' => 'Gallery 2']);
        $share    = $this->makeShare($gallery1);
        $image    = $this->makeImage($gallery2); // belongs to gallery2, not gallery1

        $response = $this->get("/g/{$share->share_token}/download/{$image->id}");

        $response->assertStatus(404);
    }

    // ── Tests: Model Helpers ─────────────────────────────────────────────

    public function test_can_download_returns_true_when_permitted_and_under_limit(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'can_download'   => true,
            'max_downloads'  => 10,
            'download_count' => 5,
        ]);

        $this->assertTrue($share->canDownload());
    }

    public function test_can_download_returns_false_when_not_permitted(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['can_download' => false]);

        $this->assertFalse($share->canDownload());
    }

    public function test_can_download_returns_false_when_limit_reached(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'can_download'   => true,
            'max_downloads'  => 3,
            'download_count' => 3,
        ]);

        $this->assertFalse($share->canDownload());
    }

    public function test_can_download_returns_true_when_no_limit_set(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'can_download'  => true,
            'max_downloads' => null,
        ]);

        $this->assertTrue($share->canDownload());
    }

    public function test_has_reached_max_downloads_with_no_limit(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'max_downloads'  => null,
            'download_count' => 100,
        ]);

        $this->assertFalse($share->hasReachedMaxDownloads());
    }

    public function test_has_reached_max_downloads_at_limit(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, [
            'max_downloads'  => 5,
            'download_count' => 5,
        ]);

        $this->assertTrue($share->hasReachedMaxDownloads());
    }

    public function test_increment_download_count_is_atomic(): void
    {
        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        $this->assertEquals(0, $share->download_count);

        $share->incrementDownloadCount();
        $share->refresh();

        $this->assertEquals(1, $share->download_count);

        $share->incrementDownloadCount();
        $share->incrementDownloadCount();
        $share->refresh();

        $this->assertEquals(3, $share->download_count);
    }

    public function test_gallery_increment_download_count(): void
    {
        $gallery = $this->makeGallery();

        $this->assertEquals(0, $gallery->download_count);

        $gallery->incrementDownloadCount();
        $gallery->refresh();

        $this->assertEquals(1, $gallery->download_count);
    }
}
