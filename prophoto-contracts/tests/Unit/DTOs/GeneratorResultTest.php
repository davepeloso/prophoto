<?php

namespace ProPhoto\Contracts\Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;

class GeneratorResultTest extends TestCase
{
    public function test_it_constructs_with_run_context_and_empty_results(): void
    {
        $runContext = $this->makeRunContext();

        $dto = new GeneratorResult(runContext: $runContext);

        $this->assertSame($runContext, $dto->runContext);
        $this->assertSame([], $dto->labels);
        $this->assertSame([], $dto->embeddings);
        $this->assertSame([], $dto->meta);
        $this->assertSame([], $dto->resultTypes());
        $this->assertFalse($dto->hasAnyResults());
    }

    public function test_it_reports_labels_result_type_when_labels_are_present(): void
    {
        $runContext = $this->makeRunContext();
        $label = $this->makeLabelResult($runContext);

        $dto = new GeneratorResult(
            runContext: $runContext,
            labels: [$label]
        );

        $this->assertSame(['labels'], $dto->resultTypes());
        $this->assertTrue($dto->hasAnyResults());
    }

    public function test_it_reports_embeddings_result_type_when_embeddings_are_present(): void
    {
        $runContext = $this->makeRunContext();
        $embedding = $this->makeEmbeddingResult($runContext);

        $dto = new GeneratorResult(
            runContext: $runContext,
            embeddings: [$embedding]
        );

        $this->assertSame(['embeddings'], $dto->resultTypes());
        $this->assertTrue($dto->hasAnyResults());
    }

    public function test_it_reports_both_result_types_when_labels_and_embeddings_are_present(): void
    {
        $runContext = $this->makeRunContext();
        $label = $this->makeLabelResult($runContext);
        $embedding = $this->makeEmbeddingResult($runContext);

        $dto = new GeneratorResult(
            runContext: $runContext,
            labels: [$label],
            embeddings: [$embedding]
        );

        $this->assertSame(['labels', 'embeddings'], $dto->resultTypes());
        $this->assertTrue($dto->hasAnyResults());
    }

    public function test_result_types_are_returned_in_deterministic_order(): void
    {
        $runContext = $this->makeRunContext();
        $dto = new GeneratorResult(
            runContext: $runContext,
            labels: [$this->makeLabelResult($runContext)],
            embeddings: [$this->makeEmbeddingResult($runContext)]
        );

        $this->assertSame(['labels', 'embeddings'], $dto->resultTypes());
    }

    public function test_it_preserves_meta_payload(): void
    {
        $runContext = $this->makeRunContext();
        $meta = [
            'provider' => 'demo',
            'latency_ms' => 82,
            'trace_id' => 'abc123',
        ];

        $dto = new GeneratorResult(
            runContext: $runContext,
            meta: $meta
        );

        $this->assertSame($meta, $dto->meta);
    }

    protected function makeRunContext(): IntelligenceRunContext
    {
        return new IntelligenceRunContext(
            assetId: AssetId::from('asset_1'),
            runId: 'run_1',
            generatorType: 'demo_generator',
            generatorVersion: 'v1',
            modelName: 'demo-model',
            modelVersion: '2025-02'
        );
    }

    protected function makeLabelResult(IntelligenceRunContext $runContext): LabelResult
    {
        return new LabelResult(
            assetId: $runContext->assetId,
            runId: $runContext->runId,
            label: 'portrait',
            confidence: 0.95,
            generatorType: $runContext->generatorType,
            generatorVersion: $runContext->generatorVersion,
            modelName: $runContext->modelName,
            modelVersion: $runContext->modelVersion
        );
    }

    protected function makeEmbeddingResult(IntelligenceRunContext $runContext): EmbeddingResult
    {
        return new EmbeddingResult(
            assetId: $runContext->assetId,
            runId: $runContext->runId,
            embeddingVector: [0.1, -0.2, 0.3],
            vectorDimensions: 3,
            generatorType: $runContext->generatorType,
            generatorVersion: $runContext->generatorVersion,
            modelName: $runContext->modelName,
            modelVersion: $runContext->modelVersion
        );
    }
}
