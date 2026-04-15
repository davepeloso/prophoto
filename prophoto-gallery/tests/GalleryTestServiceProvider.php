<?php

namespace ProPhoto\Gallery\Tests;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Contracts\Contracts\Gallery\GalleryRepositoryContract;
use ProPhoto\Gallery\Repositories\EloquentGalleryRepository;

/**
 * Minimal service provider for package tests.
 *
 * Loads gallery migrations, config, and contract bindings without
 * registering the GalleryContextProjectionListener, which requires
 * prophoto-ingest (not a test dependency).
 */
class GalleryTestServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/gallery.php',
            'prophoto-gallery'
        );

        $this->app->singleton(
            GalleryRepositoryContract::class,
            EloquentGalleryRepository::class
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'prophoto-gallery');
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
    }
}
