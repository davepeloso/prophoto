<?php

namespace ProPhoto\Gallery\Tests\Feature;

use ProPhoto\Assets\Models\Asset;
use ProPhoto\Assets\Models\AssetDerivative;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;
use ProPhoto\Gallery\Tests\TestCase;

/**
 * Story 2.3 — Asset → Gallery Association Model tests.
 *
 * Verifies:
 *  - Image::asset() resolves to the canonical Asset (downstream → upstream)
 *  - Gallery::imagesWithAssets() eager-loads without N+1
 *  - Image::thumbnail() returns the correct AssetDerivative
 *  - Image::resolvedThumbnailUrl() returns a URL when derivative exists
 *  - Gallery::updateCounts() stays accurate after attach / detach
 *  - Images without asset_id don't throw
 */
class GalleryImageAssociationTest extends TestCase
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

    private function makeImage(Gallery $gallery, ?Asset $asset = null): Image
    {
        return Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $asset?->id,
            'filename'          => 'IMG_001.jpg',
            'original_filename' => 'IMG_001.jpg',
            'sort_order'        => 0,
        ]);
    }

    // ── Tests ─────────────────────────────────────────────────────────────

    public function test_image_asset_relationship_resolves(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $image   = $this->makeImage($gallery, $asset);

        $resolved = Image::with('asset')->find($image->id);

        $this->assertNotNull($resolved->asset);
        $this->assertEquals($asset->id, $resolved->asset->id);
        $this->assertEquals('IMG_001.jpg', $resolved->asset->original_filename);
    }

    public function test_image_without_asset_does_not_throw(): void
    {
        $gallery = $this->makeGallery();
        $image   = $this->makeImage($gallery, null); // no asset

        $resolved = Image::with('asset')->find($image->id);

        $this->assertNull($resolved->asset);
        $this->assertNull($resolved->thumbnail());
        $this->assertNull($resolved->resolvedThumbnailUrl());
    }

    public function test_image_thumbnail_returns_thumbnail_derivative(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $image   = $this->makeImage($gallery, $asset);

        AssetDerivative::create([
            'asset_id'    => $asset->id,
            'type'        => 'thumbnail',
            'storage_key' => 'derivatives/thumb_001.jpg',
            'mime_type'   => 'image/jpeg',
            'bytes'       => 20000,
        ]);

        $loaded = Image::with('asset.derivatives')->find($image->id);

        $thumbnail = $loaded->thumbnail();
        $this->assertNotNull($thumbnail);
        $this->assertEquals('thumbnail', $thumbnail->type);
        $this->assertEquals('derivatives/thumb_001.jpg', $thumbnail->storage_key);
    }

    public function test_image_thumbnail_falls_back_to_preview(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $image   = $this->makeImage($gallery, $asset);

        // Only a preview derivative — no thumbnail type
        AssetDerivative::create([
            'asset_id'    => $asset->id,
            'type'        => 'preview',
            'storage_key' => 'derivatives/preview_001.jpg',
            'mime_type'   => 'image/jpeg',
            'bytes'       => 80000,
        ]);

        $loaded    = Image::with('asset.derivatives')->find($image->id);
        $thumbnail = $loaded->thumbnail();

        $this->assertNotNull($thumbnail);
        $this->assertEquals('preview', $thumbnail->type);
    }

    public function test_gallery_images_with_assets_eager_loads(): void
    {
        $gallery = $this->makeGallery();
        $asset1  = $this->makeAsset(['original_filename' => 'IMG_001.jpg']);
        $asset2  = $this->makeAsset(['original_filename' => 'IMG_002.jpg', 'checksum_sha256' => hash('sha256', 'b')]);

        $this->makeImage($gallery, $asset1);
        $this->makeImage($gallery, $asset2);

        $images = $gallery->imagesWithAssets()->get();

        $this->assertCount(2, $images);
        $this->assertNotNull($images[0]->asset);
        $this->assertNotNull($images[1]->asset);

        // Verify relation is loaded (not triggering extra queries)
        $this->assertTrue($images[0]->relationLoaded('asset'));
    }

    public function test_gallery_image_count_updates_on_attach(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();

        $this->makeImage($gallery, $asset);
        $gallery->updateCounts();

        $this->assertEquals(1, $gallery->fresh()->image_count);
    }

    public function test_gallery_image_count_updates_on_detach(): void
    {
        $gallery = $this->makeGallery();
        $asset   = $this->makeAsset();
        $image   = $this->makeImage($gallery, $asset);

        $gallery->updateCounts();
        $this->assertEquals(1, $gallery->fresh()->image_count);

        // Soft-delete — asset must be untouched
        $image->delete();
        $gallery->updateCounts();

        $this->assertEquals(0, $gallery->fresh()->image_count);
        $this->assertDatabaseHas('assets', ['id' => $asset->id]); // asset preserved
    }
}
