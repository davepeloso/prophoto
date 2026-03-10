<?php

namespace ProPhoto\Gallery;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ProPhoto\Gallery\Console\Commands\BackfillGalleryImageAssetIdsCommand;
use ProPhoto\Gallery\Models\GalleryCollection;
use ProPhoto\Gallery\Models\GalleryShare;
use ProPhoto\Gallery\Models\GalleryTemplate;
use ProPhoto\Gallery\Policies\GalleryCollectionPolicy;
use ProPhoto\Gallery\Policies\GallerySharePolicy;
use ProPhoto\Gallery\Policies\GalleryTemplatePolicy;

class GalleryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__.'/../config/gallery.php', 'prophoto-gallery'
        );
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'prophoto-gallery');

        // Load API routes
        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/gallery.php' => config_path('prophoto-gallery.php'),
        ], 'prophoto-gallery-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'prophoto-gallery-migrations');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/prophoto-gallery'),
        ], 'prophoto-gallery-views');

        // Register policies
        $this->registerPolicies();

        // Register package console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                BackfillGalleryImageAssetIdsCommand::class,
            ]);
        }
    }

    /**
     * Register the application's policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(GalleryCollection::class, GalleryCollectionPolicy::class);
        Gate::policy(GalleryShare::class, GallerySharePolicy::class);
        Gate::policy(GalleryTemplate::class, GalleryTemplatePolicy::class);
    }
}
