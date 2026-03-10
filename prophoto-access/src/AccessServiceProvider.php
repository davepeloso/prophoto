<?php

namespace ProPhoto\Access;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use ProPhoto\Access\Models\Gallery;
use ProPhoto\Access\Models\Invoice;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Access\Models\Session;
use ProPhoto\Access\Policies\GalleryPolicy;
use ProPhoto\Access\Policies\InvoicePolicy;
use ProPhoto\Access\Policies\OrganizationPolicy;
use ProPhoto\Access\Policies\SessionPolicy;
use ProPhoto\Access\Services\PermissionService;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the package.
     *
     * @var array<class-string, class-string>
     */
    protected array $policies = [
        Gallery::class => GalleryPolicy::class,
        Session::class => SessionPolicy::class,
        Organization::class => OrganizationPolicy::class,
        Invoice::class => InvoicePolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge default config
        $this->mergeConfigFrom(
            __DIR__.'/../config/permissions.php',
            'prophoto-access'
        );

        // Register PermissionService as singleton
        $this->app->singleton(PermissionService::class, function ($app) {
            return new PermissionService();
        });

        // Register alias for easier access
        $this->app->alias(PermissionService::class, 'prophoto.permissions');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load views for Filament pages
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'prophoto-access');

        // Publish config
        $this->publishes([
            __DIR__.'/../config/permissions.php' => config_path('prophoto-access.php'),
        ], 'prophoto-access-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations/' => database_path('migrations'),
        ], 'prophoto-access-migrations');

        // Publish seeders
        $this->publishes([
            __DIR__.'/../database/seeders/' => database_path('seeders'),
        ], 'prophoto-access-seeders');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/prophoto-access'),
        ], 'prophoto-access-views');

        // Register middleware
        $this->registerMiddleware();

        // Register policies
        $this->registerPolicies();
    }

    /**
     * Register middleware aliases.
     */
    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];

        $router->aliasMiddleware(
            'contextual_permission',
            \ProPhoto\Access\Http\Middleware\CheckContextualPermission::class
        );
    }

    /**
     * Register the package policies.
     */
    protected function registerPolicies(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }
}
