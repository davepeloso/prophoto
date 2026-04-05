<?php

namespace ProPhoto\Intelligence\Generators;

use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;

class EventSceneTaggingGenerator implements AssetIntelligenceGeneratorContract
{
    public function generatorType(): string
    {
        return 'event_scene_tagging';
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
                label: 'event_scene_tagged',
                confidence: 0.93,
                generatorType: $runContext->generatorType,
                generatorVersion: $runContext->generatorVersion,
                modelName: $runContext->modelName,
                modelVersion: $runContext->modelVersion,
                createdAt: $createdAt
            ),
        ];

        $sessionContext = $runContext->sessionContextSnapshot;
        if ($sessionContext !== null) {
            if (is_string($sessionContext->sessionType) && $sessionContext->sessionType !== '') {
                $labels[] = new LabelResult(
                    assetId: $runContext->assetId,
                    runId: $runContext->runId,
                    label: 'session_' . $this->slug($sessionContext->sessionType),
                    confidence: 0.91,
                    generatorType: $runContext->generatorType,
                    generatorVersion: $runContext->generatorVersion,
                    modelName: $runContext->modelName,
                    modelVersion: $runContext->modelVersion,
                    createdAt: $createdAt
                );
            }

            if (is_string($sessionContext->jobType) && $sessionContext->jobType !== '') {
                $labels[] = new LabelResult(
                    assetId: $runContext->assetId,
                    runId: $runContext->runId,
                    label: 'job_' . $this->slug($sessionContext->jobType),
                    confidence: 0.89,
                    generatorType: $runContext->generatorType,
                    generatorVersion: $runContext->generatorVersion,
                    modelName: $runContext->modelName,
                    modelVersion: $runContext->modelVersion,
                    createdAt: $createdAt
                );
            }
        }

        return new GeneratorResult(
            runContext: $runContext,
            labels: $labels,
            embeddings: [],
            meta: [
                'generator' => 'event_scene_tagging',
                'has_session_context' => $sessionContext !== null,
            ]
        );
    }

    protected function slug(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        $slug = trim((string) $slug, '_');

        return $slug === '' ? 'unknown' : $slug;
    }
}

