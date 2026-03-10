<?php

namespace ProPhoto\Assets;

use Illuminate\Support\ServiceProvider;
use ProPhoto\Assets\Console\Commands\RenormalizeAssetsMetadataCommand;
use ProPhoto\Assets\Repositories\EloquentAssetRepository;
use ProPhoto\Assets\Services\Assets\AssetCreationService;
use ProPhoto\Assets\Services\Metadata\EloquentAssetMetadataRepository;
use ProPhoto\Assets\Services\Metadata\NullAssetMetadataExtractor;
use ProPhoto\Assets\Services\Metadata\PassThroughAssetMetadataNormalizer;
use ProPhoto\Assets\Services\Path\DefaultAssetPathResolver;
use ProPhoto\Assets\Services\Storage\LaravelAssetStorage;
use ProPhoto\Assets\Services\Storage\LaravelSignedUrlGenerator;
use ProPhoto\Contracts\Contracts\Asset\AssetPathResolverContract;
use ProPhoto\Contracts\Contracts\Asset\AssetRepositoryContract;
use ProPhoto\Contracts\Contracts\Asset\AssetStorageContract;
use ProPhoto\Contracts\Contracts\Asset\SignedUrlGeneratorContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataExtractorContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataRepositoryContract;

class AssetServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/assets.php', 'prophoto-assets');

        $this->app->singleton(AssetPathResolverContract::class, DefaultAssetPathResolver::class);
        $this->app->singleton(SignedUrlGeneratorContract::class, LaravelSignedUrlGenerator::class);
        $this->app->singleton(AssetStorageContract::class, LaravelAssetStorage::class);
        $this->app->singleton(AssetRepositoryContract::class, EloquentAssetRepository::class);

        $this->app->singleton(AssetMetadataExtractorContract::class, NullAssetMetadataExtractor::class);
        $this->app->singleton(AssetMetadataNormalizerContract::class, PassThroughAssetMetadataNormalizer::class);
        $this->app->singleton(AssetMetadataRepositoryContract::class, EloquentAssetMetadataRepository::class);
        $this->app->singleton(AssetCreationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                RenormalizeAssetsMetadataCommand::class,
            ]);
        }

        $this->publishes([
            __DIR__ . '/../config/assets.php' => config_path('prophoto-assets.php'),
        ], 'prophoto-assets-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'prophoto-assets-migrations');
    }
}
