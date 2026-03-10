<?php

namespace ProPhoto\Contracts\DTOs;

use ProPhoto\Contracts\Enums\RunScope;

readonly class IntelligenceRunContext
{
    public function __construct(
        public AssetId $assetId,
        public int|string $runId,
        public string $generatorType,
        public string $generatorVersion,
        public string $modelName,
        public string $modelVersion,
        public RunScope $runScope = RunScope::SINGLE_ASSET,
        public ?string $configurationHash = null,
        public array $metadataContext = []
    ) {}
}
