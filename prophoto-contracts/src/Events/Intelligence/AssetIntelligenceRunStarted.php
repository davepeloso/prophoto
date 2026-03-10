<?php

namespace ProPhoto\Contracts\Events\Intelligence;

use ProPhoto\Contracts\DTOs\AssetId;

readonly class AssetIntelligenceRunStarted
{
    // Run-start event carries planned execution identity only.
    // Actual output families are emitted on completion events.
    public function __construct(
        public AssetId $assetId,
        public int|string $runId,
        public string $generatorType,
        public string $generatorVersion,
        public string $modelName,
        public string $modelVersion,
        public string $occurredAt
    ) {}
}
