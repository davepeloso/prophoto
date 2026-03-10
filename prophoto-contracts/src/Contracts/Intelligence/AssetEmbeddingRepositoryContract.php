<?php

namespace ProPhoto\Contracts\Contracts\Intelligence;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\EmbeddingResult;

interface AssetEmbeddingRepositoryContract
{
    /**
     * Fetch one embedding for an asset under optional model filters.
     */
    public function findByAsset(
        AssetId $assetId,
        ?string $generatorType = null,
        ?string $modelName = null,
        ?string $modelVersion = null
    ): ?EmbeddingResult;

    /**
     * Fetch embedding produced by a specific run.
     */
    public function findByRun(int|string $runId): ?EmbeddingResult;

    /**
     * Fetch the latest successful embedding for an asset under optional model filters.
     */
    public function findLatestForAsset(
        AssetId $assetId,
        ?string $generatorType = null,
        ?string $modelName = null,
        ?string $modelVersion = null
    ): ?EmbeddingResult;
}
