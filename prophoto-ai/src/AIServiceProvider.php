<?php

namespace ProPhoto\AI;

use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use ProPhoto\AI\Providers\Astria\AstriaApiClient;
use ProPhoto\AI\Providers\Astria\AstriaConfig;
use ProPhoto\AI\Providers\Astria\AstriaProvider;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiCostService;
use ProPhoto\AI\Services\AiOrchestrationService;
use ProPhoto\AI\Storage\ImageKitConfig;
use ProPhoto\AI\Storage\ImageKitStorage;
use ProPhoto\Contracts\Contracts\AI\AiStorageContract;
use ProPhoto\Contracts\DTOs\AI\AiProviderCapabilities;
use ProPhoto\Contracts\DTOs\AI\AiProviderDescriptor;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use Psr\Log\LoggerInterface;

class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Merge package config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/ai.php',
            'ai',
        );

        // Registry — singleton, lives for the request
        $this->app->singleton(AiProviderRegistry::class);

        // Orchestration service — coordinates train → generate → store flow
        $this->app->singleton(AiOrchestrationService::class, function ($app) {
            return new AiOrchestrationService(
                registry: $app->make(AiProviderRegistry::class),
                storage: $app->make(AiStorageContract::class),
                logger: $app->make(LoggerInterface::class),
            );
        });

        // Cost service — delegates to providers for estimates, aggregates from DB
        $this->app->singleton(AiCostService::class, function ($app) {
            return new AiCostService(
                registry: $app->make(AiProviderRegistry::class),
            );
        });

        // Storage — bind ImageKitStorage to the AiStorageContract interface
        $this->app->singleton(AiStorageContract::class, function ($app) {
            $config = ImageKitConfig::fromConfig(
                $app['config']->get('ai.storage.imagekit', [])
            );

            return new ImageKitStorage(
                httpClient: new Client(),
                config: $config,
                logger: $app->make(LoggerInterface::class),
            );
        });
    }

    public function boot(): void
    {
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Publish config
        $this->publishes([
            __DIR__ . '/../config/ai.php' => config_path('ai.php'),
        ], 'prophoto-ai-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'prophoto-ai-migrations');

        // Register providers
        $this->registerProviders();
    }

    /**
     * Register AI generation providers from config.
     *
     * Each enabled provider gets a descriptor + lazy resolver registered
     * in the AiProviderRegistry. The resolver is only called when the
     * provider is actually needed (lazy instantiation).
     */
    private function registerProviders(): void
    {
        $registry = $this->app->make(AiProviderRegistry::class);

        $this->registerAstriaProvider($registry);
    }

    /**
     * Register the Astria provider if enabled in config.
     */
    private function registerAstriaProvider(AiProviderRegistry $registry): void
    {
        $providerConfig = $this->app['config']->get('ai.providers.astria', []);

        if (empty($providerConfig['enabled'])) {
            return;
        }

        $descriptor = new AiProviderDescriptor(
            providerKey: 'astria',
            displayName: 'Astria',
            providerRole: ProviderRole::IDENTITY_GENERATION,
            capabilities: new AiProviderCapabilities(
                supportsTraining: true,
                supportsGeneration: true,
                supportsVideo: false,
                minTrainingImages: 8,
                maxTrainingImages: 20,
                maxGenerationsPerModel: $providerConfig['max_generations_per_model'] ?? 5,
                supportedOutputFormats: ['png', 'jpg'],
            ),
            defaultConfig: $providerConfig,
        );

        $registry->register($descriptor, function () use ($providerConfig) {
            $config = AstriaConfig::fromConfig($providerConfig);

            return new AstriaProvider(
                client: new AstriaApiClient(
                    httpClient: new Client(),
                    config: $config,
                    logger: $this->app->make(LoggerInterface::class),
                ),
                config: $config,
            );
        });
    }
}
