<?php

namespace ProPhoto\Contracts\DTOs;

readonly class NormalizedAssetMetadata
{
    public function __construct(
        public string $schemaVersion,
        public array $payload,
        public array $index = []
    ) {}
}
