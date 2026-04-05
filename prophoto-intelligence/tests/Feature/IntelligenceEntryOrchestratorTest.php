<?php

namespace ProPhoto\Intelligence\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Intelligence\Orchestration\IntelligenceEntryOrchestrator;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Planning\PlannedIntelligenceRun;
use ProPhoto\Intelligence\Planning\PlannerDecisionReason;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;
use ProPhoto\Intelligence\Tests\TestCase;

class IntelligenceEntryOrchestratorTest extends TestCase
{
    public function test_image_asset_plans_and_executes_tagging_and_embedding(): void
    {
        $assetId = $this->createAsset();

        /** @var IntelligenceEntryOrchestrator $orchestrator */
        $orchestrator = $this->app->make(IntelligenceEntryOrchestrator::class);

        $intents = $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg']
        );

        $this->assertCount(3, $intents);
        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());

        $tagRun = DB::table('intelligence_runs')
            ->where('asset_id', $assetId)
            ->where('generator_type', 'demo_tagging')
            ->first();
        $embeddingRun = DB::table('intelligence_runs')
            ->where('asset_id', $assetId)
            ->where('generator_type', 'demo_embedding')
            ->first();

        $this->assertNotNull($tagRun);
        $this->assertNotNull($embeddingRun);
        $this->assertSame('completed', $tagRun->run_status);
        $this->assertSame('completed', $embeddingRun->run_status);

        $eventSceneIntent = $this->findIntent($intents, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneIntent->decision);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
            $eventSceneIntent->skip_reason
        );

        $this->assertGreaterThan(0, DB::table('asset_labels')->where('run_id', $tagRun->id)->count());
        $this->assertSame(1, DB::table('asset_embeddings')->where('run_id', $embeddingRun->id)->count());
    }

    public function test_pdf_asset_executes_tagging_and_skips_embedding(): void
    {
        $assetId = $this->createAsset();

        /** @var IntelligenceEntryOrchestrator $orchestrator */
        $orchestrator = $this->app->make(IntelligenceEntryOrchestrator::class);

        $intents = $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'application/pdf']
        );

        $taggingIntent = $this->findIntent($intents, 'demo_tagging');
        $embeddingIntent = $this->findIntent($intents, 'demo_embedding');
        $eventSceneIntent = $this->findIntent($intents, 'event_scene_tagging');

        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $taggingIntent->decision);
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $embeddingIntent->decision);
        $this->assertSame(PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value, $embeddingIntent->skip_reason);
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneIntent->decision);
        $this->assertSame(PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value, $eventSceneIntent->skip_reason);

        $this->assertSame(1, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertNotNull(
            DB::table('intelligence_runs')
                ->where('asset_id', $assetId)
                ->where('generator_type', 'demo_tagging')
                ->first()
        );
        $this->assertSame(0, DB::table('intelligence_runs')->where('asset_id', $assetId)->where('generator_type', 'demo_embedding')->count());
    }

    public function test_matching_completed_runs_are_skipped(): void
    {
        $assetId = $this->createAsset();
        $intents = $this->plannedIntentsForImageAsset($assetId);

        foreach ($intents as $intent) {
            $this->insertRunFromIntent($assetId, $intent, 'completed');
        }

        /** @var IntelligenceEntryOrchestrator $orchestrator */
        $orchestrator = $this->app->make(IntelligenceEntryOrchestrator::class);

        $resultIntents = $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg']
        );

        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(0, DB::table('asset_labels')->count());
        $this->assertSame(0, DB::table('asset_embeddings')->count());

        $taggingIntent = $this->findIntent($resultIntents, 'demo_tagging');
        $embeddingIntent = $this->findIntent($resultIntents, 'demo_embedding');
        $eventSceneIntent = $this->findIntent($resultIntents, 'event_scene_tagging');

        $this->assertSame(PlannerDecisionReason::MATCHING_COMPLETED_RUN_EXISTS->value, $taggingIntent->skip_reason);
        $this->assertSame(PlannerDecisionReason::MATCHING_COMPLETED_RUN_EXISTS->value, $embeddingIntent->skip_reason);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
            $eventSceneIntent->skip_reason
        );
    }

    public function test_active_runs_are_skipped(): void
    {
        $assetId = $this->createAsset();
        $intents = $this->plannedIntentsForImageAsset($assetId);

        foreach ($intents as $intent) {
            $this->insertRunFromIntent($assetId, $intent, 'running');
        }

        /** @var IntelligenceEntryOrchestrator $orchestrator */
        $orchestrator = $this->app->make(IntelligenceEntryOrchestrator::class);

        $resultIntents = $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg']
        );

        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(0, DB::table('asset_labels')->count());
        $this->assertSame(0, DB::table('asset_embeddings')->count());

        $taggingIntent = $this->findIntent($resultIntents, 'demo_tagging');
        $embeddingIntent = $this->findIntent($resultIntents, 'demo_embedding');
        $eventSceneIntent = $this->findIntent($resultIntents, 'event_scene_tagging');

        $this->assertSame(PlannerDecisionReason::ACTIVE_RUN_EXISTS->value, $taggingIntent->skip_reason);
        $this->assertSame(PlannerDecisionReason::ACTIVE_RUN_EXISTS->value, $embeddingIntent->skip_reason);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
            $eventSceneIntent->skip_reason
        );
    }

    public function test_no_duplicate_run_execution_occurs_for_skipped_cases(): void
    {
        $assetId = $this->createAsset();
        $intents = $this->plannedIntentsForImageAsset($assetId);

        foreach ($intents as $intent) {
            $this->insertRunFromIntent($assetId, $intent, 'completed');
        }

        /** @var IntelligenceEntryOrchestrator $orchestrator */
        $orchestrator = $this->app->make(IntelligenceEntryOrchestrator::class);

        $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg']
        );
        $orchestrator->handleAssetReady(
            event: $this->assetReadyEvent($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg']
        );

        $this->assertSame(2, DB::table('intelligence_runs')->where('asset_id', $assetId)->count());
        $this->assertSame(0, DB::table('asset_labels')->count());
        $this->assertSame(0, DB::table('asset_embeddings')->count());
    }

    /**
     * @return list<PlannedIntelligenceRun>
     */
    protected function plannedIntentsForImageAsset(int $assetId): array
    {
        /** @var IntelligencePlanner $planner */
        $planner = $this->app->make(IntelligencePlanner::class);
        /** @var IntelligenceGeneratorRegistry $registry */
        $registry = $this->app->make(IntelligenceGeneratorRegistry::class);

        $intents = $planner->plan(
            assetId: AssetId::from($assetId),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        return array_values(array_filter(
            $intents,
            static fn (PlannedIntelligenceRun $intent): bool => $intent->decision === PlannedIntelligenceRun::DECISION_PLANNED
        ));
    }

    protected function insertRunFromIntent(int $assetId, PlannedIntelligenceRun $intent, string $runStatus): int
    {
        return (int) DB::table('intelligence_runs')->insertGetId([
            'asset_id' => $assetId,
            'generator_type' => $intent->generator_type,
            'generator_version' => $intent->generator_version,
            'model_name' => $intent->model_name,
            'model_version' => $intent->model_version,
            'configuration_hash' => $intent->configuration_hash,
            'run_scope' => $intent->run_scope->value,
            'run_status' => $runStatus,
            'retry_count' => 0,
            'started_at' => $runStatus === 'running' ? now()->toDateTimeString() : null,
            'completed_at' => $runStatus === 'completed' ? now()->toDateTimeString() : null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }

    protected function assetReadyEvent(int $assetId): AssetReadyV1
    {
        return new AssetReadyV1(
            assetId: AssetId::from($assetId),
            studioId: 101,
            status: 'ready',
            hasOriginal: true,
            hasNormalizedMetadata: true,
            hasDerivatives: true,
            occurredAt: now()->toIso8601String()
        );
    }

    /**
     * @param list<PlannedIntelligenceRun> $intents
     */
    protected function findIntent(array $intents, string $generatorType): PlannedIntelligenceRun
    {
        foreach ($intents as $intent) {
            if ($intent->generator_type === $generatorType) {
                return $intent;
            }
        }

        throw new InvalidArgumentException("Missing intent for generator type {$generatorType}.");
    }

    protected function createAsset(): int
    {
        return (int) DB::table('assets')->insertGetId([
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);
    }
}
