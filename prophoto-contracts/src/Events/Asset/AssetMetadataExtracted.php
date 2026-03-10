<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetMetadataExtracted
{
    public function __construct(
        public AssetId $assetId,
        public string $source,
        public string $extractedAt,
        public string $occurredAt
    ) {}
}
