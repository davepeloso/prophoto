<?php

namespace ProPhoto\Gallery\Repositories;

use ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GalleryId;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;

/**
 * Eloquent implementation of GalleryRepositoryContract.
 *
 * This lives in prophoto-gallery (the owning package for galleries and images).
 * Cross-package consumers resolve this via the container — they never
 * instantiate it directly.
 */
class EloquentGalleryRepository implements GalleryRepositoryContract
{
    public function createGallery(string $name, ?int $userId = null, array $options = []): GalleryId
    {
        $gallery = Gallery::create(array_merge([
            'subject_name' => $name,
            'status'       => Gallery::STATUS_ACTIVE,
        ], $options));

        return GalleryId::from($gallery->id);
    }

    /**
     * Attach an asset to a gallery by creating an Image row.
     *
     * Skips silently if the asset is already attached (idempotent).
     * After attach, gallery image_count is refreshed.
     */
    public function attachAsset(GalleryId $galleryId, AssetId $assetId): void
    {
        $gallery = Gallery::findOrFail($galleryId->toInt());

        // Idempotent — skip if already linked
        $exists = Image::where('gallery_id', $gallery->id)
            ->where('asset_id', $assetId->toInt())
            ->exists();

        if ($exists) {
            return;
        }

        // Resolve the original filename from the Asset for display
        $asset = \ProPhoto\Assets\Models\Asset::find($assetId->toInt());
        $filename = $asset?->original_filename ?? 'unknown.jpg';

        Image::create([
            'gallery_id'        => $gallery->id,
            'asset_id'          => $assetId->toInt(),
            'filename'          => $filename,
            'original_filename' => $filename,
            'sort_order'        => $gallery->images()->count(),
        ]);

        $gallery->updateCounts();
    }

    /**
     * List all asset IDs currently in a gallery.
     *
     * @return list<AssetId>
     */
    public function listAssets(GalleryId $galleryId): array
    {
        return Image::where('gallery_id', $galleryId->toInt())
            ->whereNotNull('asset_id')
            ->pluck('asset_id')
            ->map(fn (int $id): AssetId => AssetId::from($id))
            ->all();
    }

    public function deleteGallery(GalleryId $galleryId): void
    {
        Gallery::findOrFail($galleryId->toInt())->delete();
    }
}
