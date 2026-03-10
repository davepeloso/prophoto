<?php

namespace ProPhoto\Contracts\DTOs;

readonly class EmbeddingResult
{
    public function __construct(
        public AssetId $assetId,
        public int|string $runId,
        public array $embeddingVector,
        public int $vectorDimensions,
        public ?string $generatorType = null,
        public ?string $generatorVersion = null,
        public ?string $modelName = null,
        public ?string $modelVersion = null,
        public ?string $createdAt = null
    ) {}
}
