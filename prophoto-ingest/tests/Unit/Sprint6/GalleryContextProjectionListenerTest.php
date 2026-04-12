<?php

namespace ProPhoto\Ingest\Tests\Unit\Sprint6;

use Illuminate\Support\Facades\DB;
use Mockery;
use Orchestra\Testbench\TestCase;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Gallery\Listeners\GalleryContextProjectionListener;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Ingest\Events\IngestSessionConfirmed;
use ProPhoto\Ingest\IngestServiceProvider;
use ProPhoto\Gallery\GalleryServiceProvider;

/**
 * Sprint 6 — Story 1c.6
 *
 * Tests that GalleryContextProjectionListener correctly:
 *  1. Creates Image records for every Asset linked to the confirmed session
 *  2. Skips projection when no gallery_id is set on the event
 *  3. Skips projection when the gallery cannot be found
 *  4. Is idempotent — re-running doesn't duplicate images
 *  5. Updates gallery.image_count by the number of projected images
 *  6. Stores ingest context columns (session_id, file_id, tags, calendar_event_id)
 *  7. Skips assets that belong to a different session
 *
 * NOTE: Requires prophoto-gallery installed as a sibling. Skipped in standalone
 * prophoto-ingest test runs. Run from prophoto-gallery or the host app instead.
 *
 * @group cross-package
 */
class GalleryContextProjectionListenerTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        if (! class_exists(\ProPhoto\Gallery\GalleryServiceProvider::class)) {
            return [IngestServiceProvider::class];
        }

        return [
            IngestServiceProvider::class,
            GalleryServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        // Skip BEFORE parent::setUp() to prevent Orchestra from booting with
        // a GalleryServiceProvider that doesn't exist in this package's vendor.
        if (! class_exists('ProPhoto\\Gallery\\GalleryServiceProvider')) {
            $this->markTestSkipped(
                'prophoto-gallery not installed — run these tests from prophoto-gallery or the host app'
            );
            return;
        }

        parent::setUp();
        $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../../prophoto-gallery/database/migrations');
        $this->loadMigrationsFrom(__DIR__ . '/../../../../prophoto-assets/database/migrations');
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => false,
        ]);
        $app['config']->set('queue.default', 'sync');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function makeEvent(string $sessionId = 'session-uuid-abc', ?int $galleryId = 1): IngestSessionConfirmed
    {
        return new IngestSessionConfirmed(
            sessionId:               $sessionId,
            studioId:                1,
            userId:                  1,
            occurredAt:              now()->toISOString(),
            calendarEventId:         'google-event-xyz',
            calendarProvider:        'google',
            calendarMatchConfidence: 0.88,
            galleryId:               $galleryId,
        );
    }

    private function makeGallery(int $id = 1): Gallery
    {
        return Gallery::create([
            'id'           => $id,
            'studio_id'    => 1,
            'subject_name' => 'Test Gallery',
            'status'       => Gallery::STATUS_ACTIVE,
            'image_count'  => 0,
        ]);
    }

    private function makeAsset(string $sessionId, string $filename = 'photo.jpg'): Asset
    {
        return Asset::create([
            'studio_id'            => 1,
            'type'                 => 'jpeg',
            'original_filename'    => $filename,
            'mime_type'            => 'image/jpeg',
            'bytes'                => 2048,
            'checksum_sha256'      => hash('sha256', $filename . rand()),
            'storage_driver'       => 'local',
            'storage_key_original' => "ingest/{$sessionId}/{$filename}",
            'logical_path'         => '',
            'status'               => 'ready',
            'metadata'             => [
                'session_id'     => $sessionId,
                'ingest_file_id' => 'file-uuid-' . rand(1000, 9999),
                'tags'           => ['iso-400', 'f1.8', 'wedding'],
            ],
        ]);
    }

    // ─── Test 1: Projects assets into gallery as Image records ────────────────

    public function test_listener_creates_image_records_for_session_assets(): void
    {
        $gallery   = $this->makeGallery();
        $sessionId = 'session-test-001';
        $this->makeAsset($sessionId, 'IMG_001.jpg');
        $this->makeAsset($sessionId, 'IMG_002.jpg');

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, $gallery->id));

        $this->assertEquals(2, Image::where('gallery_id', $gallery->id)->count());
    }

    // ─── Test 2: Skips when no gallery_id on event ────────────────────────────

    public function test_listener_skips_when_no_gallery_id(): void
    {
        $sessionId = 'session-test-002';
        $this->makeAsset($sessionId);

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, galleryId: null));

        $this->assertEquals(0, Image::count());
    }

    // ─── Test 3: Skips when gallery not found ────────────────────────────────

    public function test_listener_skips_when_gallery_not_found(): void
    {
        $sessionId = 'session-test-003';
        $this->makeAsset($sessionId);

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, galleryId: 9999));

        $this->assertEquals(0, Image::count());
    }

    // ─── Test 4: Idempotent — re-running doesn't duplicate images ─────────────

    public function test_listener_is_idempotent_on_repeat_runs(): void
    {
        $gallery   = $this->makeGallery();
        $sessionId = 'session-test-004';
        $this->makeAsset($sessionId);

        $listener = new GalleryContextProjectionListener();
        $event    = $this->makeEvent($sessionId, $gallery->id);

        $listener->handle($event);
        $listener->handle($event); // run again

        $this->assertEquals(1, Image::where('gallery_id', $gallery->id)->count());
    }

    // ─── Test 5: Updates gallery image_count ─────────────────────────────────

    public function test_listener_increments_gallery_image_count(): void
    {
        $gallery   = $this->makeGallery();
        $sessionId = 'session-test-005';
        $this->makeAsset($sessionId, 'A.jpg');
        $this->makeAsset($sessionId, 'B.jpg');
        $this->makeAsset($sessionId, 'C.jpg');

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, $gallery->id));

        $gallery->refresh();
        $this->assertEquals(3, $gallery->image_count);
    }

    // ─── Test 6: Stores ingest context columns on Image records ──────────────

    public function test_listener_stores_ingest_context_on_image(): void
    {
        $gallery   = $this->makeGallery();
        $sessionId = 'session-test-006';
        $this->makeAsset($sessionId, 'CONTEXT.jpg');

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, $gallery->id));

        $image = Image::where('gallery_id', $gallery->id)->first();

        $this->assertNotNull($image);
        $this->assertEquals($sessionId, $image->ingest_session_id);
        $this->assertEquals('google-event-xyz', $image->calendar_event_id);
        $this->assertIsArray($image->ingest_tags);
        $this->assertContains('iso-400', $image->ingest_tags);
    }

    // ─── Test 7: Assets from other sessions are not projected ─────────────────

    public function test_listener_only_projects_assets_from_matching_session(): void
    {
        $gallery     = $this->makeGallery();
        $sessionId   = 'session-test-007';
        $otherSession = 'session-other-999';

        $this->makeAsset($sessionId,    'MINE.jpg');
        $this->makeAsset($otherSession, 'NOT_MINE.jpg');

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, $gallery->id));

        $this->assertEquals(1, Image::where('gallery_id', $gallery->id)->count());
        $this->assertEquals('MINE.jpg', Image::where('gallery_id', $gallery->id)->value('original_filename'));
    }

    // ─── Test 8: Empty session projects zero images gracefully ────────────────

    public function test_listener_handles_session_with_no_assets_gracefully(): void
    {
        $gallery   = $this->makeGallery();
        $sessionId = 'session-test-008';
        // No assets created for this session

        $listener = new GalleryContextProjectionListener();
        $listener->handle($this->makeEvent($sessionId, $gallery->id));

        $gallery->refresh();
        $this->assertEquals(0, $gallery->image_count);
        $this->assertEquals(0, Image::where('gallery_id', $gallery->id)->count());
    }
}
