<?php

namespace ProPhoto\Intelligence\Tests\Unit\Planning;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\SessionContextSnapshot;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Planning\PlannedIntelligenceRun;
use ProPhoto\Intelligence\Planning\PlannerDecisionReason;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;

class IntelligencePlannerTest extends TestCase
{
    protected IntelligencePlanner $planner;

    protected IntelligenceGeneratorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = new IntelligencePlanner();
        $this->registry = new IntelligenceGeneratorRegistry();
    }

    public function test_image_asset_plans_tagging_and_embedding(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(101),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: [],
            triggerSource: 'asset_ready',
            runScope: RunScope::SINGLE_ASSET
        );

        $this->assertCount(3, $plans);

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $tagging->decision);
        $this->assertSame(['labels'], $tagging->required_outputs);
        $this->assertNull($tagging->skip_reason);

        $embedding = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $embedding->decision);
        $this->assertSame(['embeddings'], $embedding->required_outputs);
        $this->assertNull($embedding->skip_reason);

        $eventSceneTagging = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneTagging->decision);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
            $eventSceneTagging->skip_reason
        );
    }

    public function test_unsupported_media_kind_skips_correctly(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(202),
            canonicalMetadata: ['mime_type' => 'video/mp4'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        $this->assertCount(3, $plans);

        foreach ($plans as $plan) {
            $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $plan->decision);
            $this->assertSame(PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value, $plan->skip_reason);
        }
    }

    public function test_pdf_asset_plans_tagging_and_skips_embedding(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(260),
            canonicalMetadata: ['mime_type' => 'application/pdf'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        $this->assertCount(3, $plans);

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $tagging->decision);
        $this->assertNull($tagging->skip_reason);

        $embedding = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $embedding->decision);
        $this->assertSame(PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value, $embedding->skip_reason);

        $eventSceneTagging = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneTagging->decision);
        $this->assertSame(PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value, $eventSceneTagging->skip_reason);
    }

    public function test_disabled_generator_skips_correctly(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(303),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [
                'enabled' => true,
                'generators' => [
                    'demo_embedding' => [
                        'enabled' => false,
                    ],
                ],
            ],
            existingRunSummaries: []
        );

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $tagging->decision);
        $this->assertNull($tagging->skip_reason);

        $embedding = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $embedding->decision);
        $this->assertSame(PlannerDecisionReason::DISABLED_BY_CONFIG->value, $embedding->skip_reason);
    }

    public function test_explicit_not_ready_flag_skips_with_asset_not_ready_reason(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(350),
            canonicalMetadata: [
                'mime_type' => 'image/jpeg',
                'is_ready_for_intelligence' => false,
            ],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        $this->assertCount(3, $plans);

        foreach ($plans as $plan) {
            $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $plan->decision);
            $this->assertSame(PlannerDecisionReason::ASSET_NOT_READY->value, $plan->skip_reason);
        }
    }

    public function test_matching_completed_run_skips_correctly(): void
    {
        $descriptors = [$this->registry->descriptor('demo_tagging')];
        $baselinePlans = $this->planner->plan(
            assetId: AssetId::from(404),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: []
        );
        $baselineTaggingPlan = $this->findPlan($baselinePlans, 'demo_tagging');

        $plans = $this->planner->plan(
            assetId: AssetId::from(404),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: [[
                'asset_id' => 404,
                'generator_type' => 'demo_tagging',
                'generator_version' => $baselineTaggingPlan->generator_version,
                'model_name' => $baselineTaggingPlan->model_name,
                'model_version' => $baselineTaggingPlan->model_version,
                'configuration_hash' => $baselineTaggingPlan->configuration_hash,
                'run_status' => 'completed',
            ]]
        );

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $tagging->decision);
        $this->assertSame(PlannerDecisionReason::MATCHING_COMPLETED_RUN_EXISTS->value, $tagging->skip_reason);
    }

    public function test_missing_asset_id_in_existing_summary_is_ignored(): void
    {
        $descriptors = [$this->registry->descriptor('demo_tagging')];
        $baselinePlans = $this->planner->plan(
            assetId: AssetId::from(450),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: []
        );
        $baselineTaggingPlan = $this->findPlan($baselinePlans, 'demo_tagging');

        $plans = $this->planner->plan(
            assetId: AssetId::from(450),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: [[
                // Intentional malformed summary: missing asset_id should never be treated as a match.
                'generator_type' => 'demo_tagging',
                'generator_version' => $baselineTaggingPlan->generator_version,
                'model_name' => $baselineTaggingPlan->model_name,
                'model_version' => $baselineTaggingPlan->model_version,
                'configuration_hash' => $baselineTaggingPlan->configuration_hash,
                'run_status' => 'completed',
            ]]
        );

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $tagging->decision);
        $this->assertNull($tagging->skip_reason);
    }

    public function test_active_run_skips_correctly(): void
    {
        $descriptors = [$this->registry->descriptor('demo_embedding')];
        $plans = $this->planner->plan(
            assetId: AssetId::from(505),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: [[
                'asset_id' => 505,
                'generator_type' => 'demo_embedding',
                'generator_version' => 'v1',
                'model_name' => 'demo-embedding-model',
                'model_version' => 'v1',
                'run_status' => 'running',
            ]]
        );

        $embedding = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $embedding->decision);
        $this->assertSame(PlannerDecisionReason::ACTIVE_RUN_EXISTS->value, $embedding->skip_reason);
    }

    public function test_required_context_generator_skips_when_snapshot_missing(): void
    {
        $descriptors = [$this->registry->descriptor('event_scene_tagging')];
        $plans = $this->planner->plan(
            assetId: AssetId::from(700),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        $this->assertCount(1, $plans);
        $eventSceneTagging = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneTagging->decision);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
            $eventSceneTagging->skip_reason
        );
    }

    public function test_required_context_generator_skips_when_reliability_too_low(): void
    {
        $descriptors = [$this->registry->descriptor('event_scene_tagging')];
        $plans = $this->planner->plan(
            assetId: AssetId::from(701),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: $this->sessionContextSnapshot(
                assetId: AssetId::from(701),
                reliability: SessionContextReliability::LOW
            )
        );

        $this->assertCount(1, $plans);
        $eventSceneTagging = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $eventSceneTagging->decision);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_RELIABILITY_TOO_LOW->value,
            $eventSceneTagging->skip_reason
        );
    }

    public function test_non_required_generators_plan_without_session_context(): void
    {
        $descriptors = [
            $this->registry->descriptor('demo_tagging'),
            $this->registry->descriptor('demo_embedding'),
        ];

        $plans = $this->planner->plan(
            assetId: AssetId::from(702),
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $descriptors,
            intelligenceConfig: [],
            existingRunSummaries: []
        );

        $this->assertCount(2, $plans);

        $tagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $tagging->decision);

        $embedding = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $embedding->decision);
    }

    public function test_manual_unassigned_lock_skips_required_context_generators_only(): void
    {
        $assetId = AssetId::from(999);
        $snapshot = new SessionContextSnapshot(
            assetId: $assetId,
            sessionId: null,
            bookingId: 'booking_999',
            sessionStatus: 'tentative',
            sessionType: 'wedding',
            jobType: 'wedding',
            sessionTimezone: 'UTC',
            sessionWindowStart: '2026-04-04T10:00:00Z',
            sessionWindowEnd: '2026-04-04T12:00:00Z',
            locationHint: 'Venue',
            associationSource: SessionAssociationSource::MANUAL,
            associationConfidenceTier: SessionMatchConfidenceTier::HIGH,
            contextReliability: SessionContextReliability::HIGH,
            manualLockState: SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK,
            snapshotVersion: 1,
            snapshotCapturedAt: '2026-04-04T09:59:00Z'
        );

        $plans = $this->planner->plan(
            assetId: $assetId,
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: $snapshot
        );

        $required = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $required->decision);
        $this->assertSame(
            PlannerDecisionReason::SESSION_CONTEXT_LOCKED_UNASSIGNED->value,
            $required->skip_reason
        );

        $optional = $this->findPlan($plans, 'demo_embedding');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $optional->decision);

        $optionalTagging = $this->findPlan($plans, 'demo_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $optionalTagging->decision);
    }

    public function test_required_context_generator_allows_medium_reliability(): void
    {
        $assetId = AssetId::from(1000);
        $snapshot = $this->sessionContextSnapshot(
            assetId: $assetId,
            reliability: SessionContextReliability::MEDIUM
        );

        $plans = $this->planner->plan(
            assetId: $assetId,
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: $snapshot
        );

        $plan = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $plan->decision);
    }

    public function test_skip_reason_priority_is_deterministic_for_unsupported_media(): void
    {
        $plans = $this->planner->plan(
            assetId: AssetId::from(2000),
            canonicalMetadata: ['mime_type' => 'video/mp4'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: null
        );

        $plan = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_SKIPPED, $plan->decision);
        $this->assertSame(
            PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value,
            $plan->skip_reason
        );
    }

    public function test_snapshot_is_used_consistently_for_planning(): void
    {
        $assetId = AssetId::from(3000);
        $snapshot = new SessionContextSnapshot(
            assetId: $assetId,
            sessionId: 'session_3000',
            bookingId: 'booking_3000',
            sessionStatus: 'confirmed',
            sessionType: 'wedding',
            jobType: 'ceremony',
            sessionTimezone: 'UTC',
            sessionWindowStart: '2026-04-04T10:00:00Z',
            sessionWindowEnd: '2026-04-04T12:00:00Z',
            locationHint: 'Venue',
            associationSource: SessionAssociationSource::MANUAL,
            associationConfidenceTier: SessionMatchConfidenceTier::HIGH,
            contextReliability: SessionContextReliability::HIGH,
            manualLockState: SessionAssociationLockState::NONE,
            snapshotVersion: 1,
            snapshotCapturedAt: '2026-04-04T09:59:00Z'
        );

        $plans = $this->planner->plan(
            assetId: $assetId,
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            generatorDescriptors: $this->registry->descriptors(),
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: $snapshot
        );

        $plan = $this->findPlan($plans, 'event_scene_tagging');
        $this->assertSame(PlannedIntelligenceRun::DECISION_PLANNED, $plan->decision);
    }

    /**
     * @param list<PlannedIntelligenceRun> $plans
     */
    protected function findPlan(array $plans, string $generatorType): PlannedIntelligenceRun
    {
        foreach ($plans as $plan) {
            if ($plan->generator_type === $generatorType) {
                return $plan;
            }
        }

        $this->fail("Missing plan for generator type {$generatorType}.");
    }

    protected function sessionContextSnapshot(
        AssetId $assetId,
        SessionContextReliability $reliability = SessionContextReliability::HIGH
    ): SessionContextSnapshot {
        return new SessionContextSnapshot(
            assetId: $assetId,
            sessionId: 'session_1',
            bookingId: 'booking_1',
            sessionStatus: 'confirmed',
            sessionType: 'wedding',
            jobType: 'wedding',
            sessionTimezone: 'UTC',
            sessionWindowStart: '2026-04-04T10:00:00Z',
            sessionWindowEnd: '2026-04-04T12:00:00Z',
            locationHint: 'Venue',
            associationSource: SessionAssociationSource::MANUAL,
            associationConfidenceTier: SessionMatchConfidenceTier::HIGH,
            contextReliability: $reliability,
            manualLockState: SessionAssociationLockState::NONE,
            snapshotVersion: 1,
            snapshotCapturedAt: '2026-04-04T09:59:00Z'
        );
    }
}
