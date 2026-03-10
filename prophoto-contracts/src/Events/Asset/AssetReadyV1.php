<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetReadyV1
{
    /**
     * Versioned additive event for downstream consumers.
     *
     * Emitted when an asset has reached minimum ready state for use.
     */
    public function __construct(
        public AssetId $assetId,
        public int|string $studioId,
        public string $status,
        public bool $hasOriginal,
        public bool $hasNormalizedMetadata,
        public bool $hasDerivatives,
        public string $occurredAt
    ) {}
}
