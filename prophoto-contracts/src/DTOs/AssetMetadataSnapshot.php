<?php

namespace ProPhoto\Contracts\DTOs;

readonly class AssetMetadataSnapshot
{
    public function __construct(
        public ?RawMetadataBundle $raw = null,
        public ?NormalizedAssetMetadata $normalized = null
    ) {}
}
