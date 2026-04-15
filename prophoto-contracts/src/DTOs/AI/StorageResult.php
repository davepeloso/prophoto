<?php
namespace ProPhoto\Contracts\DTOs\AI;

readonly class StorageResult
{
    public function __construct(
        public string $fileId,
        public string $url,
        public ?string $thumbnailUrl = null,
        public ?int $fileSize = null,
        public array $metadata = [],
    ) {}
}
