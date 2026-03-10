<?php

namespace ProPhoto\Intelligence\Orchestration;

use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Contracts\DTOs\IntelligenceRunContext;
use ProPhoto\Contracts\Enums\RunScope;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceGenerated;
use ProPhoto\Contracts\Events\Intelligence\AssetIntelligenceRunStarted;
use ProPhoto\Intelligence\Repositories\IntelligenceRunRepository;

class IntelligenceOrchestrator
{
    // Thin-slice note: this orchestrator runs one injected demo generator.
    // v1 multi-generator rollout should switch to registry/planner-based selection.
    public function __construct(
        protected IntelligenceRunRepository $runRepository,
        protected IntelligenceExecutionService $executionService,
        protected IntelligencePersistenceService $persistenceService,
        protected AssetIntelligenceGeneratorContract $generator,
        protected string $modelName = 'demo-tag-model',
        protected string $modelVersion = 'v1'
    ) {}

    /**
     * @param array<string, mixed> $canonicalMetadata
     */
    public function handleAssetReady(AssetReadyV1 $event, array $canonicalMetadata = []): int
    {
        $configurationHash = $this->buildConfigurationHash();

        $runId = $this->runRepository->createPendingRun(
            assetId: $event->assetId,
            generatorType: $this->generator->generatorType(),
            generatorVersion: $this->generator->generatorVersion(),
            modelName: $this->modelName,
            modelVersion: $this->modelVersion,
            configurationHash: $configurationHash,
            runScope: RunScope::SINGLE_ASSET,
            triggerSource: 'asset_ready'
        );

        // If another worker already acquired this run, skip duplicate execution.
        if (! $this->runRepository->markRunning($runId)) {
            return $runId;
        }

        Event::dispatch(new AssetIntelligenceRunStarted(
            assetId: $event->assetId,
            runId: $runId,
            generatorType: $this->generator->generatorType(),
            generatorVersion: $this->generator->generatorVersion(),
            modelName: $this->modelName,
            modelVersion: $this->modelVersion,
            occurredAt: now()->toIso8601String()
        ));

        try {
            $runContext = new IntelligenceRunContext(
                assetId: $event->assetId,
                runId: $runId,
                generatorType: $this->generator->generatorType(),
                generatorVersion: $this->generator->generatorVersion(),
                modelName: $this->modelName,
                modelVersion: $this->modelVersion,
                runScope: RunScope::SINGLE_ASSET,
                configurationHash: $configurationHash,
                metadataContext: [
                    'asset_ready_status' => $event->status,
                    'has_original' => $event->hasOriginal,
                    'has_normalized_metadata' => $event->hasNormalizedMetadata,
                    'has_derivatives' => $event->hasDerivatives,
                ]
            );

            $result = $this->executionService->execute($this->generator, $runContext, $canonicalMetadata);
            if ($result->embeddings !== []) {
                throw new RuntimeException('Label run returned embeddings; runs must stay generator-scoped.');
            }
            if ($result->labels === []) {
                throw new RuntimeException('Labels-required run returned no labels.');
            }

            DB::transaction(function () use ($result, $runId): void {
                $this->persistenceService->persistLabels($result);
                $this->runRepository->markCompleted($runId);
            });

            Event::dispatch(new AssetIntelligenceGenerated(
                assetId: $event->assetId,
                runId: $runId,
                generatorType: $this->generator->generatorType(),
                generatorVersion: $this->generator->generatorVersion(),
                modelName: $this->modelName,
                modelVersion: $this->modelVersion,
                resultTypes: $result->resultTypes(),
                occurredAt: now()->toIso8601String()
            ));

            return $runId;
        } catch (Throwable $exception) {
            $this->runRepository->markFailed(
                runId: $runId,
                failureCode: $this->errorCodeFrom($exception),
                failureMessage: $exception->getMessage()
            );

            throw $exception;
        }
    }

    protected function buildConfigurationHash(): string
    {
        $payload = [
            'generator_type' => $this->generator->generatorType(),
            'generator_version' => $this->generator->generatorVersion(),
            'model_name' => $this->modelName,
            'model_version' => $this->modelVersion,
            'required_outputs' => ['labels'],
        ];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    protected function errorCodeFrom(Throwable $exception): string
    {
        $fqcn = $exception::class;
        $parts = explode('\\', $fqcn);

        return end($parts) ?: 'UnhandledException';
    }
}
