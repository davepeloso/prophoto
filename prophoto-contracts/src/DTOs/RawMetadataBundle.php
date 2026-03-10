<?php

namespace ProPhoto\Contracts\DTOs;

readonly class RawMetadataBundle
{
    public function __construct(
        public array $payload,
        public string $source,
        public ?string $toolVersion = null,
        public ?string $schemaVersion = null,
        public ?string $hash = null
    ) {}
}
