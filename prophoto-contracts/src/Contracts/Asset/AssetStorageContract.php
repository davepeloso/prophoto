<?php

namespace ProPhoto\Contracts\Contracts\Asset;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\StoredObjectRef;
use ProPhoto\Contracts\Enums\DerivativeType;

interface AssetStorageContract
{
    /**
     * Persist the original file for an asset.
     */
    public function putOriginal(string $sourcePath, AssetId $assetId, array $metadata = []): StoredObjectRef;

    /**
     * Get a readable stream (or stream-like handle) for the original.
     */
    public function getOriginalStream(AssetId $assetId): mixed;

    /**
     * Persist a generated derivative for an asset.
     */
    public function putDerivative(
        AssetId $assetId,
        DerivativeType $derivativeType,
        string $sourcePath,
        array $metadata = []
    ): StoredObjectRef;

    /**
     * Resolve a public or signed URL for a derivative.
     */
    public function getDerivativeUrl(AssetId $assetId, DerivativeType $derivativeType, array $options = []): string;

    /**
     * Delete original and derivative objects for an asset.
     */
    public function delete(AssetId $assetId): void;

    /**
     * Check whether an object exists for the given asset/type.
     */
    public function exists(AssetId $assetId, DerivativeType $type = DerivativeType::ORIGINAL): bool;
}
