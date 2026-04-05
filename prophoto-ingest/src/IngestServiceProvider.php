<?php

namespace ProPhoto\Ingest;

use Illuminate\Support\ServiceProvider;

class IngestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ingest.php', 'prophoto-ingest');
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
