<?php

namespace ProPhoto\Contracts\Contracts\Intelligence;

use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\LabelResult;

interface AssetLabelRepositoryContract
{
    /**
     * Fetch labels for an asset, optionally scoped to a generator/model lineage.
     *
     * @return list<LabelResult>
     */
    public function findByAsset(
        AssetId $assetId,
        ?string $generatorType = null,
        ?string $modelName = null,
        ?string $modelVersion = null
    ): array;

    /**
     * Fetch labels produced by a specific run.
     *
     * @return list<LabelResult>
     */
    public function findByRun(int|string $runId): array;

    /**
     * Fetch the latest successful labels for an asset under optional model filters.
     *
     * @return list<LabelResult>
     */
    public function findLatestForAsset(
        AssetId $assetId,
        ?string $generatorType = null,
        ?string $modelName = null,
        ?string $modelVersion = null
    ): array;
}
