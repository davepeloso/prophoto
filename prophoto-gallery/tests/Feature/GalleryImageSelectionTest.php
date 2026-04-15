<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Assets\Models\AssetSessionContext;
use ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GalleryId;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Repositories\EloquentGalleryRepository;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 2.4 — Session → Gallery Image Selection tests.
 *
 * Verifies:
 *  - GalleryRepositoryContract::attachAsset() creates correct Image rows
 *  - attachAsset() is idempotent (no duplicates)
 *  - listAssets() returns asset IDs currently in the gallery
 *  - Gallery image_count updates after attach
 *  - Assets linked to session via asset_session_contexts are discoverable
 *  - Already-added assets are excluded from the available set
 */
class GalleryImageSelectionTest extends TestCase
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
        return new EloquentGalleryRepository();
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_attach_asset_creates_image_row(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $repo    = $this->repository();

        $repo->attachAsset(GalleryId::from($gallery->id), AssetId::from($asset->id));

        $this->assertDatabaseHas('images', [
            'gallery_id' => $gallery->id,
            'asset_id'   => $asset->id,
        ]);

        $image = Image::where('gallery_id', $gallery->id)
            ->where('asset_id', $asset->id)
            ->first();

        $this->assertEquals('IMG_001.jpg', $image->filename);
        $this->assertEquals('IMG_001.jpg', $image->original_filename);
    }

    public function test_attach_asset_is_idempotent(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $repo    = $this->repository();

        $galleryId = GalleryId::from($gallery->id);
        $assetId   = AssetId::from($asset->id);

        // Attach twice
        $repo->attachAsset($galleryId, $assetId);
        $repo->attachAsset($galleryId, $assetId);

        // Should still be exactly one Image row
        $count = Image::where('gallery_id', $gallery->id)
            ->where('asset_id', $asset->id)
            ->count();

        $this->assertEquals(1, $count);
    }

    public function test_attach_asset_updates_gallery_image_count(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset(['original_filename' => 'IMG_001.jpg']);
        $asset2  = $this->makeAsset(['original_filename' => 'IMG_002.jpg']);
        $repo    = $this->repository();

        $galleryId = GalleryId::from($gallery->id);

        $repo->attachAsset($galleryId, AssetId::from($asset1->id));
        $this->assertEquals(1, $gallery->fresh()->image_count);

        $repo->attachAsset($galleryId, AssetId::from($asset2->id));
        $this->assertEquals(2, $gallery->fresh()->image_count);
    }

    public function test_list_assets_returns_attached_asset_ids(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset();
        $asset2  = $this->makeAsset();
        $repo    = $this->repository();

        $galleryId = GalleryId::from($gallery->id);

        $repo->attachAsset($galleryId, AssetId::from($asset1->id));
        $repo->attachAsset($galleryId, AssetId::from($asset2->id));

        $listed = $repo->listAssets($galleryId);

        $this->assertCount(2, $listed);

        $listedValues = array_map(fn (AssetId $id) => $id->toInt(), $listed);
        $this->assertContains($asset1->id, $listedValues);
        $this->assertContains($asset2->id, $listedValues);
    }

    public function test_session_assets_discoverable_via_session_context(): void
    {
        $sessionId = 42;
        $gallery   = $this->makeGallery(['session_id' => $sessionId]);

        $asset1 = $this->makeAsset(['original_filename' => 'session-img-1.jpg']);
        $asset2 = $this->makeAsset(['original_filename' => 'session-img-2.jpg']);
        $asset3 = $this->makeAsset(['original_filename' => 'other-session.jpg']);

        // Link asset1 and asset2 to session 42
        $this->linkAssetToSession($asset1, $sessionId);
        $this->linkAssetToSession($asset2, $sessionId);
        // asset3 belongs to a different session
        $this->linkAssetToSession($asset3, 999);

        // Query the same way AddImagesFromSessionAction does
        $sessionAssetIds = AssetSessionContext::where('session_id', $gallery->session_id)
            ->pluck('asset_id');

        $availableAssets = Asset::whereIn('id', $sessionAssetIds)
            ->where('status', 'ready')
            ->get();

        $this->assertCount(2, $availableAssets);
        $this->assertTrue($availableAssets->pluck('id')->contains($asset1->id));
        $this->assertTrue($availableAssets->pluck('id')->contains($asset2->id));
        $this->assertFalse($availableAssets->pluck('id')->contains($asset3->id));
    }

    public function test_already_added_assets_excluded_from_available(): void
    {
        $sessionId = 42;
        $gallery   = $this->makeGallery(['session_id' => $sessionId]);
        $repo      = $this->repository();

        $asset1 = $this->makeAsset(['original_filename' => 'already-added.jpg']);
        $asset2 = $this->makeAsset(['original_filename' => 'not-yet-added.jpg']);

        $this->linkAssetToSession($asset1, $sessionId);
        $this->linkAssetToSession($asset2, $sessionId);

        // Attach asset1 to the gallery
        $repo->attachAsset(GalleryId::from($gallery->id), AssetId::from($asset1->id));

        // Replicate the exclusion query from AddImagesFromSessionAction
        $sessionAssetIds = AssetSessionContext::where('session_id', $gallery->session_id)
            ->pluck('asset_id');

        $existingAssetIds = Image::where('gallery_id', $gallery->id)
            ->whereNotNull('asset_id')
            ->pluck('asset_id');

        $available = Asset::whereIn('id', $sessionAssetIds)
            ->whereNotIn('id', $existingAssetIds)
            ->where('status', 'ready')
            ->get();

        $this->assertCount(1, $available);
        $this->assertEquals($asset2->id, $available->first()->id);
    }
}
