<?php

namespace ProPhoto\Gallery\Tests\Feature;

use Illuminate\Support\Facades\Event;
use ProPhoto\Gallery\Events\GalleryViewed;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 6.3 — GalleryViewed event dispatch tests.
 *
 * Verifies:
 *  1. Event dispatched on first view (access_count = 1)
 *  2. Event NOT dispatched on second view
 *  3. Event dispatched on 5th view threshold
 *  4. Event dispatched on 10th view threshold
 *  5. Event NOT dispatched on 3rd view (non-threshold)
 *  6. Event carries correct data
 */
class GalleryViewedEventTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Smith Wedding',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PRESENTATION,
            'status'          => Gallery::STATUS_ACTIVE,
            'image_count'     => 0,
        ], $attrs));
    }

    private function makeShare(Gallery $gallery, array $attrs = []): GalleryShare
    {
        return GalleryShare::create(array_merge([
            'gallery_id'        => $gallery->id,
            'shared_by_user_id' => 1,
            'shared_with_email' => 'client@example.com',
            'can_view'          => true,
            'can_download'      => false,
            'can_approve'       => false,
            'can_comment'       => false,
            'can_share'         => false,
            'access_count'      => 0,
        ], $attrs));
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_event_dispatched_on_first_view(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery);

        // First view — access_count goes from 0 to 1
        $this->get("/g/{$share->share_token}");

        Event::assertDispatched(GalleryViewed::class, function ($event) use ($gallery) {
            return $event->galleryId === $gallery->id
                && $event->viewCount === 1;
        });
    }

    public function test_event_not_dispatched_on_second_view(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['access_count' => 1]);

        // Second view — access_count goes from 1 to 2 (not a threshold)
        $this->get("/g/{$share->share_token}");

        Event::assertNotDispatched(GalleryViewed::class);
    }

    public function test_event_dispatched_on_5th_view(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['access_count' => 4]);

        // 5th view — threshold
        $this->get("/g/{$share->share_token}");

        Event::assertDispatched(GalleryViewed::class, function ($event) {
            return $event->viewCount === 5;
        });
    }

    public function test_event_dispatched_on_10th_view(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['access_count' => 9]);

        $this->get("/g/{$share->share_token}");

        Event::assertDispatched(GalleryViewed::class, function ($event) {
            return $event->viewCount === 10;
        });
    }

    public function test_event_not_dispatched_on_non_threshold_view(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery();
        $share   = $this->makeShare($gallery, ['access_count' => 2]);

        // 3rd view — not a threshold
        $this->get("/g/{$share->share_token}");

        Event::assertNotDispatched(GalleryViewed::class);
    }

    public function test_event_carries_correct_data(): void
    {
        Event::fake([GalleryViewed::class]);

        $gallery = $this->makeGallery(['subject_name' => 'Johnson Portraits']);
        $share   = $this->makeShare($gallery, [
            'shared_by_user_id' => 42,
            'shared_with_email' => 'viewer@example.com',
            'confirmed_email'   => 'viewer@example.com',
        ]);

        $this->get("/g/{$share->share_token}");

        Event::assertDispatched(GalleryViewed::class, function ($event) use ($gallery, $share) {
            return $event->galleryId === $gallery->id
                && $event->galleryShareId === $share->id
                && $event->studioId === 1
                && $event->galleryName === 'Johnson Portraits'
                && $event->viewedByEmail === 'viewer@example.com'
                && $event->viewCount === 1
                && $event->sharedByUserId === 42;
        });
    }
}
