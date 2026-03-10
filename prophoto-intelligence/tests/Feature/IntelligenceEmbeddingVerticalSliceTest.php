<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Intelligence\AssetEmbeddingUpdated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ProPhoto\Intelligence\Orchestration\IntelligencePersistenceService;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceEmbeddingVerticalSliceTest extends TestCase
{
    public function test_asset_ready_runs_demo_embedding_generator_and_persists_embedding(): void
    {
        $assetId = $this->createAsset();

        Event::fake([
            AssetIntelligenceRunStarted::class,
            AssetEmbeddingUpdated::class,
            AssetIntelligenceGenerated::class,
        ]);

        Event::dispatch(new AssetReadyV1(
            assetId: AssetId::from($assetId),
            studioId: 101,
            status: 'ready',
            hasOriginal: true,
            hasNormalizedMetadata: true,
            hasDerivatives: true,
            occurredAt: now()->toIso8601String()
        ));

        $run = DB::table('intelligence_runs')
            ->where('asset_id', $assetId)
            ->where('generator_type', 'demo_embedding')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('completed', $run->run_status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);

        $embedding = DB::table('asset_embeddings')
            ->where('run_id', $run->id)
            ->first();

        $this->assertSame(1, DB::table('asset_embeddings')->where('run_id', $run->id)->count());
        $this->assertNotNull($embedding);
        $this->assertSame($assetId, (int) $embedding->asset_id);
        $this->assertSame(3, (int) $embedding->vector_dimensions);
        $this->assertSame([0.12, -0.34, 0.56], json_decode((string) $embedding->embedding_vector, true, 512, JSON_THROW_ON_ERROR));

        Event::assertDispatched(AssetIntelligenceRunStarted::class, function (AssetIntelligenceRunStarted $event) use ($assetId, $run): bool {
            return $event->assetId->toInt() === $assetId
                && (int) $event->runId === (int) $run->id
                && $event->generatorType === 'demo_embedding';
        });

        Event::assertDispatched(AssetEmbeddingUpdated::class, function (AssetEmbeddingUpdated $event) use ($assetId, $run): bool {
            return $event->assetId->toInt() === $assetId
                && (int) $event->runId === (int) $run->id
                && $event->generatorType === 'demo_embedding'
                && $event->modelName === 'demo-embedding-model'
                && $event->modelVersion === 'v1'
                && $event->occurredAt !== ''
                && in_array('embeddings', $event->resultTypes, true);
        });

        Event::assertDispatched(AssetIntelligenceGenerated::class, function (AssetIntelligenceGenerated $event) use ($assetId, $run): bool {
            return $event->assetId->toInt() === $assetId
                && (int) $event->runId === (int) $run->id
                && $event->generatorType === 'demo_embedding'
                && $event->modelName === 'demo-embedding-model'
                && $event->modelVersion === 'v1'
                && $event->occurredAt !== ''
                && in_array('embeddings', $event->resultTypes, true);
        });
    }

    public function test_embedding_persistence_is_idempotent_for_the_same_run(): void
    {
        $assetId = $this->createAsset();
        $runId = $this->createEmbeddingRun($assetId);
        $context = $this->embeddingRunContext($assetId, $runId);

        $result = new GeneratorResult(
            runContext: $context,
            labels: [],
            embeddings: [
                new EmbeddingResult(
                    assetId: AssetId::from($assetId),
                    runId: $runId,
                    embeddingVector: [0.12, -0.34, 0.56],
                    vectorDimensions: 3
                ),
            ]
        );

        /** @var IntelligencePersistenceService $service */
        $service = $this->app->make(IntelligencePersistenceService::class);
        $service->persistEmbeddings($result);
        $service->persistEmbeddings($result);

        $count = DB::table('asset_embeddings')->where('run_id', $runId)->count();
        $this->assertSame(1, $count);
    }

    public function test_embedding_persistence_fails_on_run_id_mismatch(): void
    {
        $assetId = $this->createAsset();
        $runId = $this->createEmbeddingRun($assetId);
        $context = $this->embeddingRunContext($assetId, $runId);

        $result = new GeneratorResult(
            runContext: $context,
            labels: [],
            embeddings: [
                new EmbeddingResult(
                    assetId: AssetId::from($assetId),
                    runId: $runId + 1,
                    embeddingVector: [0.12, -0.34, 0.56],
                    vectorDimensions: 3
                ),
            ]
        );

        /** @var IntelligencePersistenceService $service */
        $service = $this->app->make(IntelligencePersistenceService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EmbeddingResult run ID does not match GeneratorResult run context.');

        $service->persistEmbeddings($result);
    }

    public function test_embedding_persistence_fails_on_asset_id_mismatch(): void
    {
        $assetId = $this->createAsset();
        $otherAssetId = $this->createAsset();
        $runId = $this->createEmbeddingRun($assetId);
        $context = $this->embeddingRunContext($assetId, $runId);

        $result = new GeneratorResult(
            runContext: $context,
            labels: [],
            embeddings: [
                new EmbeddingResult(
                    assetId: AssetId::from($otherAssetId),
                    runId: $runId,
                    embeddingVector: [0.12, -0.34, 0.56],
                    vectorDimensions: 3
                ),
            ]
        );

        /** @var IntelligencePersistenceService $service */
        $service = $this->app->make(IntelligencePersistenceService::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('EmbeddingResult asset ID does not match GeneratorResult run context.');

        $service->persistEmbeddings($result);
    }

    protected function embeddingRunContext(int $assetId, int $runId): IntelligenceRunContext
    {
        return new IntelligenceRunContext(
            assetId: AssetId::from($assetId),
            runId: $runId,
            generatorType: 'demo_embedding',
            generatorVersion: 'v1',
            modelName: 'demo-embedding-model',
            modelVersion: 'v1',
            runScope: RunScope::SINGLE_ASSET
        );
    }

    protected function createEmbeddingRun(int $assetId): int
    {
        return (int) DB::table('intelligence_runs')->insertGetId([
            'asset_id' => $assetId,
            'generator_type' => 'demo_embedding',
            'generator_version' => 'v1',
            'model_name' => 'demo-embedding-model',
            'model_version' => 'v1',
            'configuration_hash' => 'demo-embedding-hash',
            'run_scope' => 'single_asset',
            'run_status' => 'running',
            'retry_count' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
