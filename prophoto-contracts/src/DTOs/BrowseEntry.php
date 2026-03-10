<?php

namespace ProPhoto\Contracts\DTOs;

readonly class BrowseEntry
{
    public function __construct(
        public string $path,
        public bool $isDirectory,
        public ?AssetId $assetId = null,
        public ?string $mimeType = null,
        public ?int $bytes = null,
        public array $metadata = []
    ) {}
}
