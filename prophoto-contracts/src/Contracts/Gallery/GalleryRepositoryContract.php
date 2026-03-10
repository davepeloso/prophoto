<?php

namespace ProPhoto\Contracts\Contracts\Gallery;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GalleryId;

interface GalleryRepositoryContract
{
    /**
     * Create a new gallery.
     */
    public function createGallery(string $name, ?int $userId = null, array $options = []): GalleryId;

    /**
     * Attach an asset to a gallery.
     */
    public function attachAsset(GalleryId $galleryId, AssetId $assetId): void;

    /**
     * List all assets in a gallery.
     */
    public function listAssets(GalleryId $galleryId): array;

    /**
     * Delete a gallery.
     */
    public function deleteGallery(GalleryId $galleryId): void;
}
