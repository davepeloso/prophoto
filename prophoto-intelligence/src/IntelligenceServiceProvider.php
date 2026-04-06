<?php

namespace ProPhoto\Intelligence;

use Illuminate\Support\Facades\Event;
use ProPhoto\Assets\Events\AssetSessionContextAttached;
use Illuminate\Support\ServiceProvider;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Intelligence\Generators\DemoEmbeddingGenerator;
use ProPhoto\Intelligence\Generators\DemoTaggingGenerator;
use ProPhoto\Intelligence\Generators\EventSceneTaggingGenerator;
use ProPhoto\Intelligence\Listeners\HandleAssetSessionContextAttached;
use ProPhoto\Intelligence\Orchestration\IntelligenceExecutionService;
use ProPhoto\Intelligence\Orchestration\IntelligenceEmbeddingOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligenceEntryOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligenceOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligencePersistenceService;
use ProPhoto\Intelligence\Planning\IntelligencePlanner;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;
use ProPhoto\Intelligence\Repositories\IntelligenceRunRepository;

class IntelligenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IntelligenceRunRepository::class);
        $this->app->singleton(IntelligenceExecutionService::class);
        $this->app->singleton(IntelligencePersistenceService::class);
        $this->app->singleton(DemoTaggingGenerator::class);
        $this->app->singleton(DemoEmbeddingGenerator::class);
        $this->app->singleton(EventSceneTaggingGenerator::class);
        $this->app->singleton(IntelligenceGeneratorRegistry::class, function ($app): IntelligenceGeneratorRegistry {
            return new IntelligenceGeneratorRegistry(
                demoTaggingResolver: static fn () => $app->make(DemoTaggingGenerator::class),
                demoEmbeddingResolver: static fn () => $app->make(DemoEmbeddingGenerator::class),
                eventSceneTaggingResolver: static fn () => $app->make(EventSceneTaggingGenerator::class)
            );
        });
        $this->app->singleton(IntelligencePlanner::class);
        $this->app->singleton(IntelligenceEntryOrchestrator::class, function ($app): IntelligenceEntryOrchestrator {
            return new IntelligenceEntryOrchestrator(
                runRepository: $app->make(IntelligenceRunRepository::class),
                executionService: $app->make(IntelligenceExecutionService::class),
                persistenceService: $app->make(IntelligencePersistenceService::class),
                generatorRegistry: $app->make(IntelligenceGeneratorRegistry::class),
                planner: $app->make(IntelligencePlanner::class)
            );
        });

        $this->app->singleton(IntelligenceOrchestrator::class, function ($app): IntelligenceOrchestrator {
            return new IntelligenceOrchestrator(
                runRepository: $app->make(IntelligenceRunRepository::class),
                executionService: $app->make(IntelligenceExecutionService::class),
                persistenceService: $app->make(IntelligencePersistenceService::class),
                generator: $app->make(DemoTaggingGenerator::class),
                modelName: (string) config('prophoto-intelligence.demo.model_name', 'demo-tag-model'),
                modelVersion: (string) config('prophoto-intelligence.demo.model_version', 'v1')
            );
        });

        $this->app->singleton(IntelligenceEmbeddingOrchestrator::class, function ($app): IntelligenceEmbeddingOrchestrator {
            return new IntelligenceEmbeddingOrchestrator(
                runRepository: $app->make(IntelligenceRunRepository::class),
                executionService: $app->make(IntelligenceExecutionService::class),
                persistenceService: $app->make(IntelligencePersistenceService::class),
                generator: $app->make(DemoEmbeddingGenerator::class),
                modelName: (string) config('prophoto-intelligence.demo_embedding.model_name', 'demo-embedding-model'),
                modelVersion: (string) config('prophoto-intelligence.demo_embedding.model_version', 'v1')
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'prophoto-intelligence-migrations');

        Event::listen(AssetReadyV1::class, function (AssetReadyV1 $event): void {
            $entryOrchestratorEnabled = (bool) config(
                'intelligence.entry_orchestrator_enabled',
                (bool) config(
                    'prophoto-intelligence.entry_orchestrator_enabled',
                    (bool) env('INTELLIGENCE_ENTRY_ORCHESTRATOR_ENABLED', true)
                )
            );

            if ($entryOrchestratorEnabled) {
                $defaultMediaKind = (string) config(
                    'intelligence.entry_orchestrator_default_media_kind',
                    (string) config(
                        'prophoto-intelligence.entry_orchestrator_default_media_kind',
                        (string) env('INTELLIGENCE_ENTRY_ORCHESTRATOR_DEFAULT_MEDIA_KIND', 'image')
                    )
                );

                $this->app->make(IntelligenceEntryOrchestrator::class)->handleAssetReady(
                    event: $event,
                    canonicalMetadata: [
                        'media_kind' => $defaultMediaKind,
                        'is_ready_for_intelligence' => $event->status === 'ready'
                            && $event->hasOriginal
                            && $event->hasNormalizedMetadata
                            && $event->hasDerivatives,
                    ]
                );

                return;
            }

            $this->app->make(IntelligenceOrchestrator::class)->handleAssetReady($event);
            $this->app->make(IntelligenceEmbeddingOrchestrator::class)->handleAssetReady($event);
        });

        Event::listen(AssetSessionContextAttached::class, HandleAssetSessionContextAttached::class);
    }
}
