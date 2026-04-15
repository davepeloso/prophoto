<?php

namespace ProPhoto\Notifications;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use ProPhoto\Gallery\Events\GalleryDelivered;
use ProPhoto\Gallery\Events\GallerySubmitted;
use ProPhoto\Gallery\Events\GalleryViewed;
use ProPhoto\Gallery\Events\ImageDownloaded;
use ProPhoto\Notifications\Listeners\HandleGalleryDelivered;
use ProPhoto\Notifications\Listeners\HandleGallerySubmitted;
use ProPhoto\Notifications\Listeners\HandleGalleryViewed;
use ProPhoto\Notifications\Listeners\HandleImageDownloaded;

/**
 * Story 5.1 — Notifications service provider.
 *
 * Loads migrations and registers event listeners for cross-package
 * notification delivery. The notifications package listens to domain
 * events from other packages but never mutates their state.
 */
class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'prophoto-notifications');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'prophoto-notifications-migrations');

        $this->publishes([
            __DIR__ . '/../resources/views' => resource_path('views/vendor/prophoto-notifications'),
        ], 'prophoto-notifications-views');

        // ── Event Listeners ──────────────────────────────────────────────
        Event::listen(GallerySubmitted::class, HandleGallerySubmitted::class);
        Event::listen(ImageDownloaded::class, HandleImageDownloaded::class);
        Event::listen(GalleryViewed::class, HandleGalleryViewed::class);
        Event::listen(GalleryDelivered::class, HandleGalleryDelivered::class);
    }
}
