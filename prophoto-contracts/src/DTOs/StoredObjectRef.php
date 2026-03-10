<?php

namespace ProPhoto\Contracts\DTOs;

readonly class StoredObjectRef
{
    public function __construct(
        public string $storageDriver,
        public string $storageKey,
        public string $mimeType = 'application/octet-stream',
        public ?int $bytes = null,
        public array $metadata = []
    ) {}
}
