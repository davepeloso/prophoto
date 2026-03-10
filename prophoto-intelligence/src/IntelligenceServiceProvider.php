<?php

namespace ProPhoto\Intelligence;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use ProPhoto\Contracts\Events\Asset\AssetReadyV1;
use ProPhoto\Intelligence\Generators\DemoEmbeddingGenerator;
use ProPhoto\Intelligence\Generators\DemoTaggingGenerator;
use ProPhoto\Intelligence\Orchestration\IntelligenceExecutionService;
use ProPhoto\Intelligence\Orchestration\IntelligenceEmbeddingOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligenceOrchestrator;
use ProPhoto\Intelligence\Orchestration\IntelligencePersistenceService;
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
            $this->app->make(IntelligenceOrchestrator::class)->handleAssetReady($event);
            $this->app->make(IntelligenceEmbeddingOrchestrator::class)->handleAssetReady($event);
        });
    }
}
