<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Assets\Events\AssetSessionContextAttached;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Intelligence\AssetEmbeddingUpdated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceServiceProviderRoutingTest extends TestCase
{
    public function test_asset_session_context_event_routes_to_entry_orchestrator(): void
    {
        $assetId = $this->createAsset();

        $this->app['config']->set('intelligence.entry_orchestrator_default_media_kind', 'image');
        $this->app['config']->set('intelligence.entry_orchestrator_enabled', true);

        Event::fake([
            AssetIntelligenceRunStarted::class,
            AssetIntelligenceGenerated::class,
            AssetEmbeddingUpdated::class,
        ]);

        Event::dispatch(new AssetSessionContextAttached(
            assetId: $assetId,
            sessionId: 5001,
            sourceDecisionId: 'decision_ctx_1',
            triggerSource: 'asset_session_context',
            occurredAt: now()->toIso8601String()
        ));

        $this->assertSame(3, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(
            3,
            DB::table('intelligence_runs')
                ->where('asset_id', $assetId)
                ->where('trigger_source', 'asset_session_context')
                ->count()
        );
        $this->assertSame(
            1,
            DB::table('intelligence_runs')
                ->where('asset_id', $assetId)
                ->where('generator_type', 'event_scene_tagging')
                ->count()
        );

        Event::assertDispatched(AssetIntelligenceRunStarted::class, function (AssetIntelligenceRunStarted $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId;
        });
    }

    public function test_asset_ready_uses_entry_orchestrator_when_flag_enabled(): void
    {
        $assetId = $this->createAsset();

        $this->app['config']->set('intelligence.entry_orchestrator_enabled', true);
        $this->app['config']->set('intelligence.entry_orchestrator_default_media_kind', 'pdf');
        Event::fake([
            AssetIntelligenceRunStarted::class,
            AssetIntelligenceGenerated::class,
            AssetEmbeddingUpdated::class,
        ]);

        Event::dispatch($this->assetReadyEvent($assetId));

        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_tagging')->count());
        $this->assertSame(0, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_embedding')->count());

        Event::assertDispatched(AssetIntelligenceRunStarted::class, function (AssetIntelligenceRunStarted $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId
                && $event->generatorType === 'demo_tagging';
        });

        Event::assertDispatched(AssetIntelligenceGenerated::class, function (AssetIntelligenceGenerated $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId
                && $event->generatorType === 'demo_tagging'
                && in_array('labels', $event->resultTypes, true);
        });

        Event::assertNotDispatched(AssetEmbeddingUpdated::class);
    }

    public function test_asset_ready_uses_legacy_orchestrators_when_flag_disabled(): void
    {
        $assetId = $this->createAsset();

        $this->app['config']->set('intelligence.entry_orchestrator_enabled', false);
        $this->app['config']->set('intelligence.entry_orchestrator_default_media_kind', 'pdf');

        Event::dispatch($this->assetReadyEvent($assetId));

        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_tagging')->count());
        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_embedding')->count());
    }

    public function test_entry_orchestrator_dispatches_embedding_events_when_outputs_include_embeddings(): void
    {
        $assetId = $this->createAsset();

        $this->app['config']->set('intelligence.entry_orchestrator_enabled', true);
        $this->app['config']->set('intelligence.entry_orchestrator_default_media_kind', 'image');
        Event::fake([
            AssetIntelligenceRunStarted::class,
            AssetIntelligenceGenerated::class,
            AssetEmbeddingUpdated::class,
        ]);

        Event::dispatch($this->assetReadyEvent($assetId));

        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_tagging')->count());
        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_embedding')->count());

        Event::assertDispatched(AssetIntelligenceRunStarted::class, function (AssetIntelligenceRunStarted $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId
                && $event->generatorType === 'demo_embedding';
        });

        Event::assertDispatched(AssetIntelligenceGenerated::class, function (AssetIntelligenceGenerated $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId
                && $event->generatorType === 'demo_embedding'
                && in_array('embeddings', $event->resultTypes, true);
        });

        Event::assertDispatched(AssetEmbeddingUpdated::class, function (AssetEmbeddingUpdated $event) use ($assetId): bool {
            return $event->assetId->toInt() === $assetId
                && $event->generatorType === 'demo_embedding'
                && in_array('embeddings', $event->resultTypes, true);
        });
    }

    public function test_entry_orchestrator_skips_execution_when_normalized_metadata_is_missing(): void
    {
        $assetId = $this->createAsset();

        $this->app['config']->set('intelligence.entry_orchestrator_enabled', true);
        $this->app['config']->set('intelligence.entry_orchestrator_default_media_kind', 'image');
        Event::fake([
            AssetIntelligenceRunStarted::class,
            AssetIntelligenceGenerated::class,
            AssetEmbeddingUpdated::class,
        ]);

        Event::dispatch($this->assetReadyEvent($assetId, hasNormalizedMetadata: false));

        $this->assertSame(0, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        Event::assertNotDispatched(AssetIntelligenceRunStarted::class);
        Event::assertNotDispatched(AssetIntelligenceGenerated::class);
        Event::assertNotDispatched(AssetEmbeddingUpdated::class);
    }

    protected function assetReadyEvent(int $assetId, bool $hasNormalizedMetadata = true): AssetReadyV1
    {
        return new AssetReadyV1(
            assetId: AssetId::from($assetId),
            studioId: 101,
            status: 'ready',
            hasOriginal: true,
            hasNormalizedMetadata: $hasNormalizedMetadata,
            hasDerivatives: true,
            occurredAt: now()->toIso8601String()
        );
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
