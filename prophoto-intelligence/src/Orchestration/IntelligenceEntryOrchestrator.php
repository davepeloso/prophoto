<?php

namespace ProPhoto\Intelligence\Orchestration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\DTOs\GeneratorResult;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Intelligence\AssetEmbeddingUpdated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Planning\PlannedIntelligenceRun;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;
use ProPhoto\Intelligence\Repositories\IntelligenceRunRepository;
use RuntimeException;
use Throwable;

class IntelligenceEntryOrchestrator
{
    public function __construct(
        protected IntelligenceRunRepository $runRepository,
        protected IntelligenceExecutionService $executionService,
        protected IntelligencePersistenceService $persistenceService,
        protected IntelligenceGeneratorRegistry $generatorRegistry,
        protected IntelligencePlanner $planner
    ) {}

    /**
     * @param array<string, mixed> $canonicalMetadata
     * @param array<string, mixed> $intelligenceConfig
     * @return list<PlannedIntelligenceRun>
     */
    public function handleAssetReady(
        AssetReadyV1 $event,
        array $canonicalMetadata = [],
        array $intelligenceConfig = []
    ): array {
        $existingRunSummaries = $this->runRepository->plannerRunSummariesForAsset($event->assetId);

        $plannedIntents = $this->planner->plan(
            assetId: $event->assetId,
            canonicalMetadata: $canonicalMetadata,
            generatorDescriptors: $this->generatorRegistry->descriptors(),
            intelligenceConfig: $intelligenceConfig,
            existingRunSummaries: $existingRunSummaries,
            triggerSource: 'asset_ready'
        );

        foreach ($plannedIntents as $intent) {
            if ($intent->decision !== PlannedIntelligenceRun::DECISION_PLANNED) {
                continue;
            }

            $this->executePlannedIntent($event, $intent, $canonicalMetadata);
        }

        return $plannedIntents;
    }

    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    protected function executePlannedIntent(
        AssetReadyV1 $event,
        PlannedIntelligenceRun $intent,
        array $canonicalMetadata
    ): void {
        $generator = $this->generatorRegistry->resolve($intent->generator_type);

        $runId = $this->runRepository->createPendingRun(
            assetId: $intent->asset_id,
            generatorType: $intent->generator_type,
            generatorVersion: $intent->generator_version,
            modelName: $intent->model_name,
            modelVersion: $intent->model_version,
            configurationHash: $intent->configuration_hash,
            runScope: $intent->run_scope,
            triggerSource: $intent->trigger_source
        );

        // If another worker has already acquired this run, skip duplicate execution.
        if (! $this->runRepository->markRunning($runId)) {
            return;
        }

        Event::dispatch(new AssetIntelligenceRunStarted(
            assetId: $intent->asset_id,
            runId: $runId,
            generatorType: $intent->generator_type,
            generatorVersion: $intent->generator_version,
            modelName: $intent->model_name,
            modelVersion: $intent->model_version,
            occurredAt: now()->toIso8601String()
        ));

        try {
            $runContext = new IntelligenceRunContext(
                assetId: $intent->asset_id,
                runId: $runId,
                generatorType: $intent->generator_type,
                generatorVersion: $intent->generator_version,
                modelName: $intent->model_name,
                modelVersion: $intent->model_version,
                runScope: $intent->run_scope,
                configurationHash: $intent->configuration_hash,
                metadataContext: [
                    'asset_ready_status' => $event->status,
                    'has_original' => $event->hasOriginal,
                    'has_normalized_metadata' => $event->hasNormalizedMetadata,
                    'has_derivatives' => $event->hasDerivatives,
                ]
            );

            $result = $this->executionService->execute($generator, $runContext, $canonicalMetadata);
            $this->assertResultSatisfiesIntent($result, $intent);

            DB::transaction(function () use ($result, $runId): void {
                $this->persistenceService->persist($result);
                $this->runRepository->markCompleted($runId);
            });

            $this->dispatchCompletionEvents($intent, $runId, $result);
        } catch (Throwable $exception) {
            $this->runRepository->markFailed(
                runId: $runId,
                failureCode: $this->errorCodeFrom($exception),
                failureMessage: $exception->getMessage()
            );

            throw $exception;
        }
    }

    protected function assertResultSatisfiesIntent(GeneratorResult $result, PlannedIntelligenceRun $intent): void
    {
        $resultTypes = array_values(array_unique($result->resultTypes()));
        $requiredOutputs = array_values(array_unique($intent->required_outputs));

        $missingOutputs = array_values(array_diff($requiredOutputs, $resultTypes));
        if ($missingOutputs !== []) {
            throw new RuntimeException(
                "Planned generator run [{$intent->generator_type}] is missing required outputs: "
                . implode(', ', $missingOutputs)
                . '.'
            );
        }

        $unexpectedOutputs = array_values(array_diff($resultTypes, $requiredOutputs));
        if ($unexpectedOutputs !== []) {
            throw new RuntimeException(
                "Planned generator run [{$intent->generator_type}] returned unexpected outputs: "
                . implode(', ', $unexpectedOutputs)
                . '.'
            );
        }
    }

    protected function dispatchCompletionEvents(
        PlannedIntelligenceRun $intent,
        int|string $runId,
        GeneratorResult $result
    ): void {
        $resultTypes = $result->resultTypes();

        if (in_array('embeddings', $resultTypes, true)) {
            Event::dispatch(new AssetEmbeddingUpdated(
                assetId: $intent->asset_id,
                runId: $runId,
                generatorType: $intent->generator_type,
                generatorVersion: $intent->generator_version,
                modelName: $intent->model_name,
                modelVersion: $intent->model_version,
                resultTypes: $resultTypes,
                occurredAt: now()->toIso8601String()
            ));
        }

        Event::dispatch(new AssetIntelligenceGenerated(
            assetId: $intent->asset_id,
            runId: $runId,
            generatorType: $intent->generator_type,
            generatorVersion: $intent->generator_version,
            modelName: $intent->model_name,
            modelVersion: $intent->model_version,
            resultTypes: $resultTypes,
            occurredAt: now()->toIso8601String()
        ));
    }

    protected function errorCodeFrom(Throwable $exception): string
    {
        $fqcn = $exception::class;
        $parts = explode('\\', $fqcn);

        return end($parts) ?: 'UnhandledException';
    }
}
