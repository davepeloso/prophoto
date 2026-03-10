<?php

namespace ProPhoto\Contracts\Events\Intelligence;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetEmbeddingUpdated
{
    /**
     * @param list<string> $resultTypes
     */
    public function __construct(
        public AssetId $assetId,
        public int|string $runId,
        public string $generatorType,
        public string $generatorVersion,
        public string $modelName,
        public string $modelVersion,
        public array $resultTypes,
        public string $occurredAt
    ) {}
}
