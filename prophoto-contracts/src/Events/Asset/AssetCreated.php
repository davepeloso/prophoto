<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\AssetType;

readonly class AssetCreated
{
    public function __construct(
        public AssetId $assetId,
        public int|string $studioId,
        public AssetType $type,
        public string $logicalPath,
        public string $occurredAt
    ) {}
}
