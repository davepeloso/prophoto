<?php

namespace ProPhoto\Intelligence\Tests\Unit\Planning;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\SessionContextSnapshot;
use ProPhoto\Contracts\Enums\RunStatus;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Intelligence\Planning\GeneratorDescriptor;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Planning\PlannedIntelligenceRun;
use ProPhoto\Intelligence\Planning\PlannerDecisionReason;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;

class IntelligencePlannerMatrixTest extends TestCase
{
    protected IntelligencePlanner $planner;

    protected IntelligenceGeneratorRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->planner = new IntelligencePlanner();
        $this->registry = new IntelligenceGeneratorRegistry();
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     * @param list<string> $expectedRequiredOutputs
     */
    #[DataProvider('plannerMatrixProvider')]
    public function test_planner_behavior_matrix(
        int $assetIdValue,
        string $generatorType,
        array $canonicalMetadata,
        ?string $snapshotKind,
        string $existingRunScenario,
        string $expectedDecision,
        ?string $expectedSkipReason,
        array $expectedRequiredOutputs
    ): void {
        $assetId = AssetId::from($assetIdValue);
        $descriptor = $this->registry->descriptor($generatorType);
        $snapshot = $this->sessionContextSnapshot(kind: $snapshotKind, assetId: $assetId);
        $existingRunSummaries = $this->existingRunSummaries(
            scenario: $existingRunScenario,
            assetId: $assetId,
            canonicalMetadata: $canonicalMetadata,
            descriptor: $descriptor,
            sessionContextSnapshot: $snapshot
        );

        $plans = $this->planner->plan(
            assetId: $assetId,
            canonicalMetadata: $canonicalMetadata,
            generatorDescriptors: [$descriptor],
            intelligenceConfig: [],
            existingRunSummaries: $existingRunSummaries,
            sessionContextSnapshot: $snapshot
        );

        $this->assertCount(1, $plans);
        $plan = $plans[0];

        $this->assertSame($generatorType, $plan->generator_type);
        $this->assertSame($expectedDecision, $plan->decision);
        $this->assertSame($expectedRequiredOutputs, $plan->required_outputs);

        if ($expectedDecision === PlannedIntelligenceRun::DECISION_SKIPPED) {
            $this->assertSame($expectedSkipReason, $plan->skip_reason);
            return;
        }

        $this->assertNull($plan->skip_reason);
    }

    /**
     * @return array<string, array{
     *     0: int,
     *     1: string,
     *     2: array<string, mixed>,
     *     3: ?string,
     *     4: string,
     *     5: string,
     *     6: ?string,
     *     7: list<string>
     * }>
     */
    public static function plannerMatrixProvider(): array
    {
        return [
            'event_scene_tagging image missing session context' => [
                1001,
                'event_scene_tagging',
                ['mime_type' => 'image/jpeg'],
                null,
                'none',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::SESSION_CONTEXT_REQUIRED_BUT_MISSING->value,
                ['labels'],
            ],
            'event_scene_tagging image low reliability' => [
                1002,
                'event_scene_tagging',
                ['mime_type' => 'image/jpeg'],
                'low',
                'none',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::SESSION_CONTEXT_RELIABILITY_TOO_LOW->value,
                ['labels'],
            ],
            'event_scene_tagging image high reliability' => [
                1003,
                'event_scene_tagging',
                ['mime_type' => 'image/jpeg'],
                'high',
                'none',
                PlannedIntelligenceRun::DECISION_PLANNED,
                null,
                ['labels'],
            ],
            'demo_embedding image no session context' => [
                1004,
                'demo_embedding',
                ['mime_type' => 'image/jpeg'],
                null,
                'none',
                PlannedIntelligenceRun::DECISION_PLANNED,
                null,
                ['embeddings'],
            ],
            'demo_embedding image manual unassigned lock still planned' => [
                1005,
                'demo_embedding',
                ['mime_type' => 'image/jpeg'],
                'manual_unassigned',
                'none',
                PlannedIntelligenceRun::DECISION_PLANNED,
                null,
                ['embeddings'],
            ],
            'event_scene_tagging unsupported media kind' => [
                1006,
                'event_scene_tagging',
                ['mime_type' => 'video/mp4'],
                null,
                'none',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value,
                ['labels'],
            ],
            'demo_embedding pdf unsupported media kind' => [
                1007,
                'demo_embedding',
                ['mime_type' => 'application/pdf'],
                null,
                'none',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::UNSUPPORTED_MEDIA_KIND->value,
                ['embeddings'],
            ],
            'event_scene_tagging matching completed run exists' => [
                1008,
                'event_scene_tagging',
                ['mime_type' => 'image/jpeg'],
                'high',
                'matching_completed',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::MATCHING_COMPLETED_RUN_EXISTS->value,
                ['labels'],
            ],
            'demo_embedding active run exists' => [
                1009,
                'demo_embedding',
                ['mime_type' => 'image/jpeg'],
                null,
                'active',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::ACTIVE_RUN_EXISTS->value,
                ['embeddings'],
            ],
            'event_scene_tagging manual unassigned lock overrides high reliability' => [
                1010,
                'event_scene_tagging',
                ['mime_type' => 'image/jpeg'],
                'manual_unassigned',
                'none',
                PlannedIntelligenceRun::DECISION_SKIPPED,
                PlannerDecisionReason::SESSION_CONTEXT_LOCKED_UNASSIGNED->value,
                ['labels'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     * @param list<array<string, mixed>> $existingRunSummaries
     * @return list<array<string, mixed>>
     */
    protected function existingRunSummaries(
        string $scenario,
        AssetId $assetId,
        array $canonicalMetadata,
        GeneratorDescriptor $descriptor,
        ?SessionContextSnapshot $sessionContextSnapshot
    ): array {
        if ($scenario === 'none') {
            return [];
        }

        $baselinePlans = $this->planner->plan(
            assetId: $assetId,
            canonicalMetadata: $canonicalMetadata,
            generatorDescriptors: [$descriptor],
            intelligenceConfig: [],
            existingRunSummaries: [],
            sessionContextSnapshot: $sessionContextSnapshot
        );
        $baselinePlan = $baselinePlans[0];

        if ($scenario === 'matching_completed') {
            return [[
                'asset_id' => $assetId->toString(),
                'generator_type' => $descriptor->generator_type,
                'generator_version' => $baselinePlan->generator_version,
                'model_name' => $baselinePlan->model_name,
                'model_version' => $baselinePlan->model_version,
                'configuration_hash' => $baselinePlan->configuration_hash,
                'run_status' => RunStatus::COMPLETED->value,
            ]];
        }

        if ($scenario === 'active') {
            return [[
                'asset_id' => $assetId->toString(),
                'generator_type' => $descriptor->generator_type,
                'generator_version' => $baselinePlan->generator_version,
                'model_name' => $baselinePlan->model_name,
                'model_version' => $baselinePlan->model_version,
                'run_status' => RunStatus::RUNNING->value,
            ]];
        }

        throw new InvalidArgumentException("Unsupported existing-run scenario {$scenario}.");
    }

    protected function sessionContextSnapshot(?string $kind, AssetId $assetId): ?SessionContextSnapshot
    {
        if ($kind === null) {
            return null;
        }

        $reliability = match ($kind) {
            'high', 'manual_unassigned' => SessionContextReliability::HIGH,
            'low' => SessionContextReliability::LOW,
            default => throw new InvalidArgumentException("Unsupported snapshot kind {$kind}."),
        };

        $manualLockState = $kind === 'manual_unassigned'
            ? SessionAssociationLockState::MANUAL_UNASSIGNED_LOCK
            : SessionAssociationLockState::NONE;

        return new SessionContextSnapshot(
            assetId: $assetId,
            sessionId: $kind === 'manual_unassigned' ? null : "session_{$assetId->toString()}",
            bookingId: "booking_{$assetId->toString()}",
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
            manualLockState: $manualLockState,
            snapshotVersion: 1,
            snapshotCapturedAt: '2026-04-04T09:59:00Z'
        );
    }
}

