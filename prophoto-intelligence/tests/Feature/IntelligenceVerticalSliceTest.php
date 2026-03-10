<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ProPhoto\Intelligence\Orchestration\IntelligencePersistenceService;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceVerticalSliceTest extends TestCase
{
    public function test_asset_ready_runs_demo_generator_and_persists_labels(): void
    {
        $assetId = $this->createAsset();

        Event::fake([
            AssetIntelligenceRunStarted::class,
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
            ->where('generator_type', 'demo_tagging')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($run);
        $this->assertSame('completed', $run->run_status);
        $this->assertNotNull($run->started_at);
        $this->assertNotNull($run->completed_at);

        $labels = DB::table('asset_labels')
            ->where('run_id', $run->id)
            ->orderBy('label')
            ->get();

        $this->assertSame(['asset_ready', 'demo_tagged'], $labels->pluck('label')->all());
        $this->assertSame($assetId, (int) $labels->first()->asset_id);

        Event::assertDispatched(AssetIntelligenceRunStarted::class, function (AssetIntelligenceRunStarted $event) use ($assetId, $run): bool {
            return $event->assetId->toInt() === $assetId
                && (int) $event->runId === (int) $run->id
                && $event->generatorType === 'demo_tagging';
        });

        Event::assertDispatched(AssetIntelligenceGenerated::class, function (AssetIntelligenceGenerated $event) use ($assetId, $run): bool {
            return $event->assetId->toInt() === $assetId
                && (int) $event->runId === (int) $run->id
                && $event->generatorType === 'demo_tagging'
                && in_array('labels', $event->resultTypes, true);
        });
    }

    public function test_label_persistence_is_idempotent_for_the_same_run(): void
    {
        $assetId = $this->createAsset();
        $runId = (int) DB::table('intelligence_runs')->insertGetId([
            'asset_id' => $assetId,
            'generator_type' => 'demo_tagging',
            'generator_version' => 'v1',
            'model_name' => 'demo-tag-model',
            'model_version' => 'v1',
            'configuration_hash' => 'demo-hash',
            'run_scope' => 'single_asset',
            'run_status' => 'running',
            'retry_count' => 0,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $context = new IntelligenceRunContext(
            assetId: AssetId::from($assetId),
            runId: $runId,
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            runScope: RunScope::SINGLE_ASSET
        );

        $result = new GeneratorResult(
            runContext: $context,
            labels: [
                new LabelResult(assetId: AssetId::from($assetId), runId: $runId, label: 'portrait', confidence: 0.9),
                new LabelResult(assetId: AssetId::from($assetId), runId: $runId, label: 'studio', confidence: 0.8),
            ],
            embeddings: []
        );

        /** @var IntelligencePersistenceService $service */
        $service = $this->app->make(IntelligencePersistenceService::class);
        $service->persist($result);
        $service->persist($result);

        $count = DB::table('asset_labels')->where('run_id', $runId)->count();
        $this->assertSame(2, $count);
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
