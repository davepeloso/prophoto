<?php

namespace ProPhoto\Contracts\DTOs;

use ProPhoto\Contracts\Enums\AssetType;

readonly class AssetRecord
{
    public function __construct(
        public AssetId $id,
        public int|string $studioId,
        public AssetType $type,
        public string $originalFilename,
        public string $mimeType,
        public int $bytes,
        public string $checksumSha256,
        public string $storageDriver,
        public string $storageKeyOriginal,
        public string $logicalPath,
        public string $status = 'pending',
        public ?string $capturedAt = null,
        public ?string $ingestedAt = null,
        public array $metadata = []
    ) {}
}
