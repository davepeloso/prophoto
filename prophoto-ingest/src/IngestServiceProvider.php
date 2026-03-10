<?php

namespace ProPhoto\Ingest;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class IngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ingest.php', 'ingest');
        $this->mergeConfigFrom(__DIR__ . '/../config/exiftool.php', 'exiftool');

        // Register IngestSettingsService
        $this->app->singleton(Services\IngestSettingsService::class, function ($app) {
            return new Services\IngestSettingsService();
        });

        $this->app->singleton(Services\MetadataExtractor::class, function ($app) {
            return new Services\MetadataExtractor(
                config('ingest.exif.denormalize_keys', [])
            );
        });

        $this->app->singleton(Services\IngestProcessor::class, function ($app) {
            return new Services\IngestProcessor(
                config('ingest.storage'),
                config('ingest.schema')
            );
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ingest');

        // Load dynamic configuration from database
        $this->loadDatabaseConfiguration();

        $this->registerRoutes();
        $this->registerPublishing();
        $this->registerCommands();
    }

// Final tag taxonomy (what users will see)
    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Config
        |--------------------------------------------------------------------------
        */
        $this->publishes([
            __DIR__ . '/../config/ingest.php' => config_path('ingest.php'),
            __DIR__ . '/../config/exiftool.php' => config_path('exiftool.php'),
        ], ['ingest', 'ingest-config']);

        /*
        |--------------------------------------------------------------------------
        | Migrations (explicit opt-in)
        |--------------------------------------------------------------------------
        */
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'ingest-migrations');

        /*
        |--------------------------------------------------------------------------
        | Compiled frontend assets
        |--------------------------------------------------------------------------
        */
        $distPath = __DIR__ . '/../dist';
        if (is_dir($distPath)) {
            $this->publishes([
                $distPath => public_path('vendor/ingest'),
            ], ['ingest', 'ingest-assets']);
        }

        /*
        |--------------------------------------------------------------------------
        | Frontend source (customization)
        |--------------------------------------------------------------------------
        */
        $this->publishes([
            __DIR__ . '/../resources/js' => resource_path('js/vendor/ingest'),
        ], ['ingest', 'ingest-source', 'ingest-views']); // ingest-views = deprecated alias

        /*
        |--------------------------------------------------------------------------
        | Deprecation notice (local only)
        |--------------------------------------------------------------------------
        */
        if ($this->app->environment('local')) {
            $this->commandDeprecationNotice();
        }
    }


 // Deprecation notice (clean + non-noisy)
    protected function commandDeprecationNotice(): void
    {
        if (! isset($_SERVER['argv'])) {
            return;
        }

        if (in_array('--tag=ingest-views', $_SERVER['argv'], true)) {
            $this->app['log']->warning(
                '[prophoto/ingest] The "ingest-views" publish tag is deprecated. ' .
                'Use "ingest-source" instead.'
            );
        }
    }

    /**
     * Load configuration from database and override config file defaults.
     */
    protected function loadDatabaseConfiguration(): void
    {
        try {
            // Only load if the table exists (prevents errors during initial installation)
            if (!$this->app->runningInConsole() || $this->tableExists('ingest_settings')) {
                $dbSettings = $this->app->make(Services\IngestSettingsService::class)->getAll();

                // Override config values with database settings
                $this->overrideConfig($dbSettings);
            }
        } catch (\Exception $e) {
            // Silently fail if database is not set up yet
            // This allows migrations to run without errors
        }
    }

    /**
     * Override configuration values with database settings.
     *
     * @param  array<string, mixed>  $dbSettings
     */
    protected function overrideConfig(array $dbSettings): void
    {
        // Core settings
        if (isset($dbSettings['route_prefix'])) {
            config(['ingest.route_prefix' => $dbSettings['route_prefix']]);
        }
        if (isset($dbSettings['middleware'])) {
            config(['ingest.middleware' => $dbSettings['middleware']]);
        }

        // Schema settings
        if (isset($dbSettings['schema.path'])) {
            config(['ingest.schema.path' => $dbSettings['schema.path']]);
        }
        if (isset($dbSettings['schema.filename'])) {
            config(['ingest.schema.filename' => $dbSettings['schema.filename']]);
        }
        if (isset($dbSettings['schema.sequence_start'])) {
            config(['ingest.schema.sequence_start' => $dbSettings['schema.sequence_start']]);
        }
        if (isset($dbSettings['schema.sequence_padding'])) {
            config(['ingest.schema.sequence_padding' => $dbSettings['schema.sequence_padding']]);
        }

        // Storage settings
        if (isset($dbSettings['storage.temp_disk'])) {
            config(['ingest.storage.temp_disk' => $dbSettings['storage.temp_disk']]);
        }
        if (isset($dbSettings['storage.temp_path'])) {
            config(['ingest.storage.temp_path' => $dbSettings['storage.temp_path']]);
        }
        if (isset($dbSettings['storage.final_disk'])) {
            config(['ingest.storage.final_disk' => $dbSettings['storage.final_disk']]);
        }
        if (isset($dbSettings['storage.final_path'])) {
            config(['ingest.storage.final_path' => $dbSettings['storage.final_path']]);
        }

        // EXIF/Processing settings - Thumbnail
        if (isset($dbSettings['exif.thumbnail.enabled'])) {
            config(['ingest.exif.thumbnail.enabled' => $dbSettings['exif.thumbnail.enabled']]);
        }
        if (isset($dbSettings['exif.thumbnail.width'])) {
            config(['ingest.exif.thumbnail.width' => $dbSettings['exif.thumbnail.width']]);
        }
        if (isset($dbSettings['exif.thumbnail.height'])) {
            config(['ingest.exif.thumbnail.height' => $dbSettings['exif.thumbnail.height']]);
        }
        if (isset($dbSettings['exif.thumbnail.quality'])) {
            config(['ingest.exif.thumbnail.quality' => $dbSettings['exif.thumbnail.quality']]);
        }

        // EXIF/Processing settings - Preview
        if (isset($dbSettings['exif.preview.enabled'])) {
            config(['ingest.exif.preview.enabled' => $dbSettings['exif.preview.enabled']]);
        }
        if (isset($dbSettings['exif.preview.max_dimension'])) {
            config(['ingest.exif.preview.max_dimension' => $dbSettings['exif.preview.max_dimension']]);
        }
        if (isset($dbSettings['exif.preview.quality'])) {
            config(['ingest.exif.preview.quality' => $dbSettings['exif.preview.quality']]);
        }

        // EXIF/Processing settings - Final
        if (isset($dbSettings['exif.final.enabled'])) {
            config(['ingest.exif.final.enabled' => $dbSettings['exif.final.enabled']]);
        }
        if (isset($dbSettings['exif.final.max_dimension'])) {
            config(['ingest.exif.final.max_dimension' => $dbSettings['exif.final.max_dimension']]);
        }
        if (isset($dbSettings['exif.final.quality'])) {
            config(['ingest.exif.final.quality' => $dbSettings['exif.final.quality']]);
        }

        // Metadata settings
        if (isset($dbSettings['metadata.chart_fields'])) {
            config(['ingest.metadata.chart_fields' => $dbSettings['metadata.chart_fields']]);
        }

        // Tagging settings
        if (isset($dbSettings['tagging.quick_tags'])) {
            config(['ingest.tagging.quick_tags' => $dbSettings['tagging.quick_tags']]);
        }
        if (isset($dbSettings['tagging.sources'])) {
            config(['ingest.tagging.sources' => $dbSettings['tagging.sources']]);
        }

        // Association settings
        if (isset($dbSettings['associations.models'])) {
            config(['ingest.associations.models' => $dbSettings['associations.models']]);
        }

        // Appearance settings
        if (isset($dbSettings['appearance.theme_mode'])) {
            config(['ingest.appearance.theme_mode' => $dbSettings['appearance.theme_mode']]);
        }
        
        // Light mode colors
        if (isset($dbSettings['appearance.light.accent_color'])) {
            config(['ingest.appearance.light.accent_color' => $dbSettings['appearance.light.accent_color']]);
        }
        if (isset($dbSettings['appearance.light.background'])) {
            config(['ingest.appearance.light.background' => $dbSettings['appearance.light.background']]);
        }
        if (isset($dbSettings['appearance.light.foreground'])) {
            config(['ingest.appearance.light.foreground' => $dbSettings['appearance.light.foreground']]);
        }
        
        // Dark mode colors
        if (isset($dbSettings['appearance.dark.accent_color'])) {
            config(['ingest.appearance.dark.accent_color' => $dbSettings['appearance.dark.accent_color']]);
        }
        if (isset($dbSettings['appearance.dark.background'])) {
            config(['ingest.appearance.dark.background' => $dbSettings['appearance.dark.background']]);
        }
        if (isset($dbSettings['appearance.dark.foreground'])) {
            config(['ingest.appearance.dark.foreground' => $dbSettings['appearance.dark.foreground']]);
        }
        
        // Shared appearance settings
        if (isset($dbSettings['appearance.border_radius'])) {
            config(['ingest.appearance.border_radius' => $dbSettings['appearance.border_radius']]);
        }
        if (isset($dbSettings['appearance.spacing_scale'])) {
            config(['ingest.appearance.spacing_scale' => $dbSettings['appearance.spacing_scale']]);
        }
    }

    /**
     * Check if a database table exists.
     *
     * @param  string  $table
     * @return bool
     */
    protected function tableExists(string $table): bool
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasTable($table);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function registerRoutes(): void
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'prefix' => config('ingest.route_prefix', 'ingest'),
            'middleware' => config('ingest.middleware', ['web', 'auth']),
        ];
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\Commands\ExiftoolDoctor::class,
            ]);
        }
    }
}
