<?php

namespace ProPhoto\Contracts\Events\Asset;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetStored
{
    public function __construct(
        public AssetId $assetId,
        public string $storageDriver,
        public string $storageKeyOriginal,
        public int $bytes,
        public string $checksumSha256,
        public string $occurredAt
    ) {}
}
