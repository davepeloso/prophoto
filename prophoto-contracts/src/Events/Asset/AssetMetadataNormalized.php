<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetMetadataNormalized
{
    public function __construct(
        public AssetId $assetId,
        public string $schemaVersion,
        public string $normalizedAt,
        public string $occurredAt
    ) {}
}
