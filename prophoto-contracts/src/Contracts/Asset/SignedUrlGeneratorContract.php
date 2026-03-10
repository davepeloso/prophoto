<?php

namespace ProPhoto\Contracts\Contracts\Asset;

use DateTimeInterface;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;

interface SignedUrlGeneratorContract
{
    /**
     * Generate a signed URL for a raw storage object key.
     */
    public function forStorageKey(
        string $storageDriver,
        string $storageKey,
        DateTimeInterface $expiresAt,
        array $options = []
    ): string;

    /**
     * Generate a signed URL for an asset derivative.
     */
    public function forAssetDerivative(
        AssetId $assetId,
        DerivativeType $derivativeType,
        DateTimeInterface $expiresAt,
        array $options = []
    ): string;
}
