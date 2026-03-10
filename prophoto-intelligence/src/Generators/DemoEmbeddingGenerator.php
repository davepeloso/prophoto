<?php

namespace ProPhoto\Intelligence\Generators;

use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;

class DemoEmbeddingGenerator implements AssetIntelligenceGeneratorContract
{
    public function generatorType(): string
    {
        return 'demo_embedding';
    }

    public function generatorVersion(): string
    {
        return 'v1';
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    public function generate(IntelligenceRunContext $runContext, array $canonicalMetadata = []): GeneratorResult
    {
        $createdAt = now()->toDateTimeString();

        $embedding = new EmbeddingResult(
            assetId: $runContext->assetId,
            runId: $runContext->runId,
            embeddingVector: [0.12, -0.34, 0.56],
            vectorDimensions: 3,
            generatorType: $runContext->generatorType,
            generatorVersion: $runContext->generatorVersion,
            modelName: $runContext->modelName,
            modelVersion: $runContext->modelVersion,
            createdAt: $createdAt
        );

        return new GeneratorResult(
            runContext: $runContext,
            labels: [],
            embeddings: [$embedding],
            meta: [
                'generator' => 'demo',
            ]
        );
    }
}
