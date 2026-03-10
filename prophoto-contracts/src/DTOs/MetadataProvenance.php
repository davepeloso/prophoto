<?php

namespace ProPhoto\Contracts\DTOs;

readonly class MetadataProvenance
{
    public function __construct(
        public string $source,
        public ?string $toolVersion = null,
        public ?string $recordedAt = null,
        public array $context = []
    ) {}
}
