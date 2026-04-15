<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetSessionContext;
use ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GalleryId;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 2.5 — Gallery Image Management tests.
 *
 * Verifies:
 *  - Remove image soft-deletes Image, preserves Asset
 *  - Remove updates gallery image_count
 *  - Reorder persists sort_order to DB
 *  - Add more images from session works after initial add
 *  - Bulk remove works correctly
 */
class GalleryImageManagementTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────

    private function makeGallery(array $attrs = []): Gallery
    {
        return Gallery::create(array_merge([
            'subject_name'    => 'Test Gallery',
            'studio_id'       => 1,
            'organization_id' => 1,
            'type'            => Gallery::TYPE_PROOFING,
            'status'          => Gallery::STATUS_ACTIVE,
            'image_count'     => 0,
        ], $attrs));
    }

    private function makeAsset(array $attrs = []): Asset
    {
        return Asset::create(array_merge([
            'studio_id'            => 1,
            'type'                 => 'image',
            'original_filename'    => 'IMG_001.jpg',
            'mime_type'            => 'image/jpeg',
            'bytes'                => 1024000,
            'checksum_sha256'      => hash('sha256', uniqid()),
            'storage_driver'       => 'local',
            'storage_key_original' => 'ingest/test/IMG_001.jpg',
            'logical_path'         => 'studio/1/IMG_001.jpg',
            'status'               => 'ready',
        ], $attrs));
    }

    private function makeImage(Gallery $gallery, ?Asset $asset = null, int $sortOrder = 0): Image
    {
        return Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $asset?->id,
            'filename'          => $asset?->original_filename ?? 'IMG_001.jpg',
            'original_filename' => $asset?->original_filename ?? 'IMG_001.jpg',
            'sort_order'        => $sortOrder,
        ]);
    }

    private function linkAssetToSession(Asset $asset, int $sessionId): void
    {
        AssetSessionContext::create([
            'asset_id'           => $asset->id,
            'session_id'         => $sessionId,
            'source_decision_id' => 'test-decision-' . uniqid(),
            'decision_type'      => 'auto_assigned',
            'subject_type'       => 'session',
            'subject_id'         => (string) $sessionId,
            'algorithm_version'  => 'test-v1',
            'occurred_at'        => now(),
        ]);
    }

    private function repository(): GalleryRepositoryContract
    {
        return app(GalleryRepositoryContract::class);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_remove_image_soft_deletes_and_preserves_asset(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $image   = $this->makeImage($gallery, $asset);

        // Soft-delete the image
        $image->delete();

        // Image is trashed
        $this->assertSoftDeleted('images', ['id' => $image->id]);

        // Asset is untouched
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
        $this->assertNull(Asset::find($asset->id)->deleted_at ?? null);
    }

    public function test_remove_image_updates_gallery_count(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset(['original_filename' => 'IMG_001.jpg']);
        $asset2  = $this->makeAsset(['original_filename' => 'IMG_002.jpg']);

        $image1 = $this->makeImage($gallery, $asset1, 0);
        $image2 = $this->makeImage($gallery, $asset2, 1);

        $gallery->updateCounts();
        $this->assertEquals(2, $gallery->fresh()->image_count);

        // Remove one
        $image1->delete();
        $gallery->updateCounts();

        $this->assertEquals(1, $gallery->fresh()->image_count);

        // Remove the other
        $image2->delete();
        $gallery->updateCounts();

        $this->assertEquals(0, $gallery->fresh()->image_count);
    }

    public function test_reorder_persists_sort_order(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset(['original_filename' => 'first.jpg']);
        $asset2  = $this->makeAsset(['original_filename' => 'second.jpg']);
        $asset3  = $this->makeAsset(['original_filename' => 'third.jpg']);

        $img1 = $this->makeImage($gallery, $asset1, 0);
        $img2 = $this->makeImage($gallery, $asset2, 1);
        $img3 = $this->makeImage($gallery, $asset3, 2);

        // Simulate reorder: swap img3 to first, img1 to last
        $img3->update(['sort_order' => 0]);
        $img2->update(['sort_order' => 1]);
        $img1->update(['sort_order' => 2]);

        // Verify persisted order
        $ordered = Image::where('gallery_id', $gallery->id)
            ->orderBy('sort_order')
            ->pluck('original_filename')
            ->all();

        $this->assertEquals(['third.jpg', 'second.jpg', 'first.jpg'], $ordered);
    }

    public function test_add_more_images_from_session_works(): void
    {
        $sessionId = 42;
        $gallery   = $this->makeGallery(['session_id' => $sessionId]);
        $repo      = $this->repository();

        $asset1 = $this->makeAsset(['original_filename' => 'batch1.jpg']);
        $asset2 = $this->makeAsset(['original_filename' => 'batch2.jpg']);
        $asset3 = $this->makeAsset(['original_filename' => 'batch3.jpg']);

        $this->linkAssetToSession($asset1, $sessionId);
        $this->linkAssetToSession($asset2, $sessionId);
        $this->linkAssetToSession($asset3, $sessionId);

        $galleryId = GalleryId::from($gallery->id);

        // First batch — add asset1 only
        $repo->attachAsset($galleryId, AssetId::from($asset1->id));
        $this->assertEquals(1, $gallery->fresh()->image_count);

        // Second batch — add asset2 and asset3
        $repo->attachAsset($galleryId, AssetId::from($asset2->id));
        $repo->attachAsset($galleryId, AssetId::from($asset3->id));

        $this->assertEquals(3, $gallery->fresh()->image_count);

        // All three images linked
        $linkedAssetIds = Image::where('gallery_id', $gallery->id)
            ->whereNotNull('asset_id')
            ->pluck('asset_id')
            ->sort()
            ->values()
            ->all();

        $expected = collect([$asset1->id, $asset2->id, $asset3->id])
            ->sort()
            ->values()
            ->all();

        $this->assertEquals($expected, $linkedAssetIds);
    }

    public function test_bulk_remove_deletes_multiple_and_updates_count(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset(['original_filename' => 'IMG_001.jpg']);
        $asset2  = $this->makeAsset(['original_filename' => 'IMG_002.jpg']);
        $asset3  = $this->makeAsset(['original_filename' => 'IMG_003.jpg']);

        $img1 = $this->makeImage($gallery, $asset1, 0);
        $img2 = $this->makeImage($gallery, $asset2, 1);
        $img3 = $this->makeImage($gallery, $asset3, 2);

        $gallery->updateCounts();
        $this->assertEquals(3, $gallery->fresh()->image_count);

        // Bulk remove img1 and img3
        $img1->delete();
        $img3->delete();
        $gallery->updateCounts();

        $this->assertEquals(1, $gallery->fresh()->image_count);

        // Only img2 remains
        $remaining = Image::where('gallery_id', $gallery->id)->pluck('id')->all();
        $this->assertEquals([$img2->id], $remaining);

        // Assets all preserved
        $this->assertDatabaseHas('assets', ['id' => $asset1->id]);
        $this->assertDatabaseHas('assets', ['id' => $asset2->id]);
        $this->assertDatabaseHas('assets', ['id' => $asset3->id]);
    }
}
