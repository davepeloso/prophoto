<?php

namespace ProPhoto\Contracts\Tests\Feature;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;
use ProPhoto\Contracts\Enums\RunScope;

class IntelligenceDtoSerializationTest extends TestCase
{
    public function test_intelligence_run_context_serializes_and_restores(): void
    {
        $dto = new IntelligenceRunContext(
            assetId: AssetId::from('asset_123'),
            runId: 'run_abc',
            generatorType: 'openai_embeddings',
            generatorVersion: 'v2',
            modelName: 'text-embedding-3-large',
            modelVersion: '2025-02',
            runScope: RunScope::BATCH,
            configurationHash: 'cfg_hash',
            metadataContext: ['mime_type' => 'image/jpeg']
        );

        $restored = unserialize(serialize($dto));

        $this->assertInstanceOf(IntelligenceRunContext::class, $restored);
        $this->assertSame('asset_123', $restored->assetId->toString());
        $this->assertSame('run_abc', (string) $restored->runId);
        $this->assertSame('openai_embeddings', $restored->generatorType);
        $this->assertSame('v2', $restored->generatorVersion);
        $this->assertSame('text-embedding-3-large', $restored->modelName);
        $this->assertSame('2025-02', $restored->modelVersion);
        $this->assertSame(RunScope::BATCH, $restored->runScope);
        $this->assertSame('cfg_hash', $restored->configurationHash);
        $this->assertSame(['mime_type' => 'image/jpeg'], $restored->metadataContext);
    }

    public function test_label_result_serializes_and_restores(): void
    {
        $dto = new LabelResult(
            assetId: AssetId::from(10),
            runId: 99,
            label: 'wedding',
            confidence: 0.98,
            generatorType: 'ai_tags',
            generatorVersion: 'v1',
            modelName: 'clip-vit-l',
            modelVersion: '2025-01',
            createdAt: '2026-03-10T10:11:12Z'
        );

        $restored = unserialize(serialize($dto));

        $this->assertInstanceOf(LabelResult::class, $restored);
        $this->assertSame(10, $restored->assetId->toInt());
        $this->assertSame(99, $restored->runId);
        $this->assertSame('wedding', $restored->label);
        $this->assertSame(0.98, $restored->confidence);
        $this->assertSame('ai_tags', $restored->generatorType);
        $this->assertSame('v1', $restored->generatorVersion);
        $this->assertSame('clip-vit-l', $restored->modelName);
        $this->assertSame('2025-01', $restored->modelVersion);
        $this->assertSame('2026-03-10T10:11:12Z', $restored->createdAt);
    }

    public function test_embedding_result_serializes_and_restores(): void
    {
        $dto = new EmbeddingResult(
            assetId: AssetId::from('asset_456'),
            runId: 'run_xyz',
            embeddingVector: [0.1, 0.2, -0.3],
            vectorDimensions: 3,
            generatorType: 'openai_embeddings',
            generatorVersion: 'v2',
            modelName: 'text-embedding-3-large',
            modelVersion: '2025-02',
            createdAt: '2026-03-10T10:11:12Z'
        );

        $restored = unserialize(serialize($dto));

        $this->assertInstanceOf(EmbeddingResult::class, $restored);
        $this->assertSame('asset_456', $restored->assetId->toString());
        $this->assertSame('run_xyz', (string) $restored->runId);
        $this->assertSame([0.1, 0.2, -0.3], $restored->embeddingVector);
        $this->assertSame(3, $restored->vectorDimensions);
        $this->assertSame('openai_embeddings', $restored->generatorType);
        $this->assertSame('v2', $restored->generatorVersion);
        $this->assertSame('text-embedding-3-large', $restored->modelName);
        $this->assertSame('2025-02', $restored->modelVersion);
        $this->assertSame('2026-03-10T10:11:12Z', $restored->createdAt);
    }

    public function test_generator_result_serializes_and_restores(): void
    {
        $runContext = new IntelligenceRunContext(
            assetId: AssetId::from('asset_1'),
            runId: 'run_1',
            generatorType: 'demo',
            generatorVersion: 'v1',
            modelName: 'demo-model',
            modelVersion: '1'
        );

        $dto = new GeneratorResult(
            runContext: $runContext,
            labels: [
                new LabelResult(
                    assetId: $runContext->assetId,
                    runId: $runContext->runId,
                    label: 'portrait',
                    confidence: 0.95
                ),
            ],
            embeddings: [
                new EmbeddingResult(
                    assetId: $runContext->assetId,
                    runId: $runContext->runId,
                    embeddingVector: [0.5, -0.2],
                    vectorDimensions: 2
                ),
            ],
            meta: ['provider' => 'demo']
        );

        $restored = unserialize(serialize($dto));

        $this->assertInstanceOf(GeneratorResult::class, $restored);
        $this->assertSame('asset_1', $restored->runContext->assetId->toString());
        $this->assertCount(1, $restored->labels);
        $this->assertCount(1, $restored->embeddings);
        $this->assertSame(['provider' => 'demo'], $restored->meta);
    }
}
