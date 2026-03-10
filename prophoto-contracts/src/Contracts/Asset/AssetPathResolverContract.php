<?php

namespace ProPhoto\Contracts\Contracts\Asset;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;

interface AssetPathResolverContract
{
    /**
     * Resolve storage key for the original object.
     */
    public function originalKey(AssetId $assetId, int|string $studioId, string $originalFilename): string;

    /**
     * Resolve storage key for a derivative object.
     */
    public function derivativeKey(
        AssetId $assetId,
        int|string $studioId,
        DerivativeType $derivativeType,
        string $extension
    ): string;

    /**
     * Resolve logical browse path prefix for an asset.
     */
    public function logicalPath(AssetId $assetId, int|string $studioId, ?string $prefix = null): string;
}
