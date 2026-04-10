<?php

namespace ProPhoto\Ingest;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Ingest\Repositories\SessionAssignmentDecisionRepository;
use ProPhoto\Ingest\Repositories\SessionAssignmentRepository;
use ProPhoto\Ingest\Services\BatchUploadRecognitionService;
use ProPhoto\Ingest\Services\IngestItemContextBuilder;
use ProPhoto\Ingest\Services\SessionAssociationWriteService;
use ProPhoto\Ingest\Services\SessionMatchingService;
use ProPhoto\Ingest\Services\IngestItemSessionMatchingFlowService;
use ProPhoto\Ingest\Services\Matching\SessionMatchCandidateGenerator;
use ProPhoto\Ingest\Services\Matching\SessionMatchScoringService;
use ProPhoto\Ingest\Services\Matching\SessionMatchDecisionClassifier;

class IngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ingest.php', 'prophoto-ingest');

        // Repository bindings
        $this->app->singleton(SessionAssignmentDecisionRepository::class);
        $this->app->singleton(SessionAssignmentRepository::class);

        // Service bindings
        $this->app->singleton(IngestItemContextBuilder::class);
        $this->app->singleton(BatchUploadRecognitionService::class);
        $this->app->singleton(SessionAssociationWriteService::class);
        $this->app->singleton(SessionMatchingService::class);
        $this->app->singleton(IngestItemSessionMatchingFlowService::class);

        // Matching service bindings
        $this->app->singleton(SessionMatchCandidateGenerator::class);
        $this->app->singleton(SessionMatchScoringService::class);
        $this->app->singleton(SessionMatchDecisionClassifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../config/ingest.php' => config_path('prophoto-ingest.php'),
        ], 'prophoto-ingest-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'prophoto-ingest-migrations');
    }
}
