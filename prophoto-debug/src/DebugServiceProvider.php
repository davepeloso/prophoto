<?php

namespace ProPhoto\Debug;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Debug\Console\Commands\CleanupTracesCommand;
use ProPhoto\Debug\Console\Commands\SnapshotConfigCommand;
use ProPhoto\Debug\Console\Commands\ViewTraceCommand;
use ProPhoto\Debug\Services\ConfigRecorder;
use ProPhoto\Debug\Services\IngestTracer;

class DebugServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/debug.php',
            'debug'
        );

        // Register the IngestTracer service
        $this->app->singleton(IngestTracer::class, function ($app) {
            return new IngestTracer();
        });

        // Register the ConfigRecorder service
        $this->app->singleton(ConfigRecorder::class, function ($app) {
            return new ConfigRecorder();
        });

        // Create convenient aliases
        $this->app->alias(IngestTracer::class, 'prophoto.debug.tracer');
        $this->app->alias(ConfigRecorder::class, 'prophoto.debug.recorder');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'debug');

        // Register event listeners only if debug is enabled
        if (config('debug.enabled')) {
            $this->registerEventListeners();
        }

        // Register Artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                CleanupTracesCommand::class,
                SnapshotConfigCommand::class,
                ViewTraceCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/debug.php' => config_path('debug.php'),
            ], 'debug-config');

            $this->publishes([
                __DIR__.'/../database/migrations/' => database_path('migrations'),
            ], 'debug-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/debug'),
            ], 'debug-views');
        }

        // Register Filament plugin if available and enabled
        if (config('debug.filament.enabled') && class_exists(\Filament\FilamentServiceProvider::class)) {
            $this->registerFilamentPlugin();
        }
    }

    /**
     * Register event listeners for ingest tracing.
     */
    protected function registerEventListeners(): void
    {
        $events = $this->app['events'];

        // Listen for preview extraction events from prophoto-ingest
        $events->listen(
            'ProPhoto\Ingest\Events\PreviewExtractionAttempted',
            'ProPhoto\Debug\Listeners\RecordPreviewAttempt'
        );

        $events->listen(
            'ProPhoto\Ingest\Events\PreviewExtractionCompleted',
            'ProPhoto\Debug\Listeners\RecordPreviewCompletion'
        );

        $events->listen(
            'ProPhoto\Ingest\Events\MetadataExtractionCompleted',
            'ProPhoto\Debug\Listeners\RecordMetadataExtraction'
        );

        $events->listen(
            'ProPhoto\Ingest\Events\ThumbnailGenerationCompleted',
            'ProPhoto\Debug\Listeners\RecordThumbnailGeneration'
        );

        $events->listen(
            'ProPhoto\Ingest\Events\TraceSessionStarted',
            'ProPhoto\Debug\Listeners\RecordSessionStart'
        );

        $events->listen(
            'ProPhoto\Ingest\Events\TraceSessionEnded',
            'ProPhoto\Debug\Listeners\RecordSessionEnd'
        );
    }

    /**
     * Register the Filament plugin.
     */
    protected function registerFilamentPlugin(): void
    {
        // Filament plugin registration will be handled by the DebugPlugin class
        // which should be registered in the application's Filament panel provider
    }
}
