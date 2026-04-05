<?php

namespace ProPhoto\Intelligence\Tests\Unit\Orchestration;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\DTOs\EmbeddingResult;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\DTOs\LabelResult;
use ProPhoto\Contracts\DTOs\SessionContextSnapshot;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Enums\SessionAssociationLockState;
use ProPhoto\Contracts\Enums\SessionAssociationSource;
use ProPhoto\Contracts\Enums\SessionContextReliability;
use ProPhoto\Contracts\Enums\SessionMatchConfidenceTier;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Intelligence\Orchestration\IntelligenceEntryOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligenceExecutionService;
use ProPhoto\Intelligence\Orchestration\IntelligencePersistenceService;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Planning\PlannedIntelligenceRun;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;
use ProPhoto\Intelligence\Repositories\IntelligenceRunRepository;
use RuntimeException;

class IntelligenceEntryOrchestratorTest extends TestCase
{
    public function test_assert_result_satisfies_intent_accepts_exact_output_family(): void
    {
        $orchestrator = $this->orchestratorHarness();

        $orchestrator->assertIntentResult(
            result: $this->resultWithLabelsOnly(),
            intent: $this->plannedIntent(requiredOutputs: ['labels'])
        );

        $this->addToAssertionCount(1);
    }

    public function test_assert_result_satisfies_intent_rejects_unexpected_output_family(): void
    {
        $orchestrator = $this->orchestratorHarness();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('returned unexpected outputs: embeddings.');

        $orchestrator->assertIntentResult(
            result: $this->resultWithLabelsAndEmbeddings(),
            intent: $this->plannedIntent(requiredOutputs: ['labels'])
        );
    }

    public function test_run_context_uses_same_snapshot_passed_into_planner(): void
    {
        $assetId = AssetId::from(88);
        $sessionSnapshot = new SessionContextSnapshot(
            assetId: $assetId,
            sessionId: 'session_88',
            bookingId: 'booking_88',
            sessionStatus: 'confirmed',
            sessionType: 'wedding',
            jobType: 'wedding',
            sessionTimezone: 'UTC',
            sessionWindowStart: '2026-04-04T10:00:00Z',
            sessionWindowEnd: '2026-04-04T12:00:00Z',
            locationHint: 'Venue',
            associationSource: SessionAssociationSource::MANUAL,
            associationConfidenceTier: SessionMatchConfidenceTier::HIGH,
            contextReliability: SessionContextReliability::HIGH,
            manualLockState: SessionAssociationLockState::MANUAL_ASSIGNED_LOCK,
            snapshotVersion: 1,
            snapshotCapturedAt: '2026-04-04T09:59:00Z'
        );
        $plannedIntent = PlannedIntelligenceRun::planned(
            assetId: $assetId,
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            configurationHash: 'demo-hash',
            runScope: RunScope::SINGLE_ASSET,
            triggerSource: 'asset_ready',
            requiredOutputs: ['labels']
        );

        $runRepository = $this->createMock(IntelligenceRunRepository::class);
        $runRepository->expects($this->once())
            ->method('plannerRunSummariesForAsset')
            ->with($assetId)
            ->willReturn([]);

        $executionService = $this->createMock(IntelligenceExecutionService::class);
        $persistenceService = $this->createMock(IntelligencePersistenceService::class);
        $generatorRegistry = $this->createMock(IntelligenceGeneratorRegistry::class);
        $planner = $this->createMock(IntelligencePlanner::class);

        $planner->expects($this->once())
            ->method('plan')
            ->with(
                $assetId,
                ['mime_type' => 'image/jpeg'],
                [],
                [],
                [],
                'asset_ready',
                RunScope::SINGLE_ASSET,
                $sessionSnapshot
            )
            ->willReturn([$plannedIntent]);

        $generatorRegistry->expects($this->once())
            ->method('descriptors')
            ->willReturn([]);

        $orchestrator = new class(
            $runRepository,
            $executionService,
            $persistenceService,
            $generatorRegistry,
            $planner
        ) extends IntelligenceEntryOrchestrator {
            public ?IntelligenceRunContext $capturedRunContext = null;

            protected function executePlannedIntent(
                AssetReadyV1 $event,
                PlannedIntelligenceRun $intent,
                array $canonicalMetadata,
                ?SessionContextSnapshot $sessionContextSnapshot = null
            ): void {
                $this->capturedRunContext = $this->buildRunContext(
                    event: $event,
                    intent: $intent,
                    runId: 999,
                    sessionContextSnapshot: $sessionContextSnapshot
                );
            }
        };

        $event = new AssetReadyV1(
            assetId: $assetId,
            studioId: 101,
            status: 'ready',
            hasOriginal: true,
            hasNormalizedMetadata: true,
            hasDerivatives: true,
            occurredAt: '2026-04-04T10:01:00Z'
        );

        $orchestrator->handleAssetReady(
            event: $event,
            canonicalMetadata: ['mime_type' => 'image/jpeg'],
            intelligenceConfig: [],
            sessionContextSnapshot: $sessionSnapshot
        );

        $this->assertInstanceOf(IntelligenceRunContext::class, $orchestrator->capturedRunContext);
        $this->assertSame($sessionSnapshot, $orchestrator->capturedRunContext->sessionContextSnapshot);
    }

    private function plannedIntent(array $requiredOutputs): PlannedIntelligenceRun
    {
        return PlannedIntelligenceRun::planned(
            assetId: AssetId::from(1),
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            configurationHash: 'demo-hash',
            runScope: RunScope::SINGLE_ASSET,
            triggerSource: 'asset_ready',
            requiredOutputs: $requiredOutputs
        );
    }

    private function resultWithLabelsOnly(): GeneratorResult
    {
        return new GeneratorResult(
            runContext: $this->runContext(),
            labels: [
                new LabelResult(
                    assetId: AssetId::from(1),
                    runId: 1,
                    label: 'demo_label',
                    confidence: 0.9
                ),
            ],
            embeddings: []
        );
    }

    private function resultWithLabelsAndEmbeddings(): GeneratorResult
    {
        return new GeneratorResult(
            runContext: $this->runContext(),
            labels: [
                new LabelResult(
                    assetId: AssetId::from(1),
                    runId: 1,
                    label: 'demo_label',
                    confidence: 0.9
                ),
            ],
            embeddings: [
                new EmbeddingResult(
                    assetId: AssetId::from(1),
                    runId: 1,
                    embeddingVector: [0.12, -0.34, 0.56],
                    vectorDimensions: 3
                ),
            ]
        );
    }

    private function runContext(): IntelligenceRunContext
    {
        return new IntelligenceRunContext(
            assetId: AssetId::from(1),
            runId: 1,
            generatorType: 'demo_tagging',
            generatorVersion: 'v1',
            modelName: 'demo-tag-model',
            modelVersion: 'v1',
            runScope: RunScope::SINGLE_ASSET,
            configurationHash: 'demo-hash'
        );
    }

    private function orchestratorHarness(): object
    {
        $runRepository = $this->createMock(IntelligenceRunRepository::class);
        $executionService = $this->createMock(IntelligenceExecutionService::class);
        $persistenceService = $this->createMock(IntelligencePersistenceService::class);
        $generatorRegistry = $this->createMock(IntelligenceGeneratorRegistry::class);
        $planner = $this->createMock(IntelligencePlanner::class);

        return new class(
            $runRepository,
            $executionService,
            $persistenceService,
            $generatorRegistry,
            $planner
        ) extends IntelligenceEntryOrchestrator {
            public function assertIntentResult(GeneratorResult $result, PlannedIntelligenceRun $intent): void
            {
                $this->assertResultSatisfiesIntent($result, $intent);
            }
        };
    }
}
