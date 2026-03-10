<?php

namespace ProPhoto\Contracts\DTOs;

readonly class LabelResult
{
    public function __construct(
        public AssetId $assetId,
        public int|string $runId,
        public string $label,
        public ?float $confidence = null,
        public ?string $generatorType = null,
        public ?string $generatorVersion = null,
        public ?string $modelName = null,
        public ?string $modelVersion = null,
        public ?string $createdAt = null
    ) {}
}
