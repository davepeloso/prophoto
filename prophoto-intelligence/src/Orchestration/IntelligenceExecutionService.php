<?php

namespace ProPhoto\Intelligence\Orchestration;

use InvalidArgumentException;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;

class IntelligenceExecutionService
{
    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    public function execute(
        AssetIntelligenceGeneratorContract $generator,
        IntelligenceRunContext $runContext,
        array $canonicalMetadata = []
    ): GeneratorResult {
        $result = $generator->generate($runContext, $canonicalMetadata);

        if ((string) $result->runContext->runId !== (string) $runContext->runId) {
            throw new InvalidArgumentException('Generator result run context does not match the orchestrated run ID.');
        }

        if ($result->runContext->assetId->toString() !== $runContext->assetId->toString()) {
            throw new InvalidArgumentException('Generator result asset ID does not match the orchestrated asset ID.');
        }

        return $result;
    }
}
