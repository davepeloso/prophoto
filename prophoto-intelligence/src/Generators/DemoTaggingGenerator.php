<?php

namespace ProPhoto\Intelligence\Generators;

use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;

class DemoTaggingGenerator implements AssetIntelligenceGeneratorContract
{
    public function generatorType(): string
    {
        return 'demo_tagging';
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

        $labels = [
            new LabelResult(
                assetId: $runContext->assetId,
                runId: $runContext->runId,
                label: 'demo_tagged',
                confidence: 0.95,
                generatorType: $runContext->generatorType,
                generatorVersion: $runContext->generatorVersion,
                modelName: $runContext->modelName,
                modelVersion: $runContext->modelVersion,
                createdAt: $createdAt
            ),
            new LabelResult(
                assetId: $runContext->assetId,
                runId: $runContext->runId,
                label: 'asset_ready',
                confidence: 0.90,
                generatorType: $runContext->generatorType,
                generatorVersion: $runContext->generatorVersion,
                modelName: $runContext->modelName,
                modelVersion: $runContext->modelVersion,
                createdAt: $createdAt
            ),
        ];

        if (($canonicalMetadata['mime_type'] ?? null) === 'image/jpeg') {
            $labels[] = new LabelResult(
                assetId: $runContext->assetId,
                runId: $runContext->runId,
                label: 'jpeg',
                confidence: 0.85,
                generatorType: $runContext->generatorType,
                generatorVersion: $runContext->generatorVersion,
                modelName: $runContext->modelName,
                modelVersion: $runContext->modelVersion,
                createdAt: $createdAt
            );
        }

        return new GeneratorResult(
            runContext: $runContext,
            labels: $labels,
            embeddings: [],
            meta: [
                'generator' => 'demo',
            ]
        );
    }
}
