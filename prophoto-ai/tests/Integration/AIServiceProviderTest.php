<?php

namespace ProPhoto\AI\Tests\Integration;

use Orchestra\Testbench\TestCase;
use ProPhoto\AI\AIServiceProvider;
use ProPhoto\AI\Providers\Astria\AstriaProvider;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiCostService;
use ProPhoto\AI\Services\AiOrchestrationService;
use ProPhoto\AI\Storage\ImageKitStorage;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\Contracts\AI\AiStorageContract;

class AIServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [AIServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Set Astria config for testing
        $app['config']->set('ai.providers.astria.enabled', true);
        $app['config']->set('ai.providers.astria.api_key', 'sd_test_key');

        // Set ImageKit config for testing
        $app['config']->set('ai.storage.imagekit.public_key', 'public_test');
        $app['config']->set('ai.storage.imagekit.private_key', 'private_test');
        $app['config']->set('ai.storage.imagekit.url_endpoint', 'https://ik.imagekit.io/test');
    }

    // ── Config ─────────────────────────────────────────────────────

    public function test_config_is_loaded(): void
    {
        $this->assertSame('astria', config('ai.default_provider'));
        $this->assertSame('ai', config('ai.queue.name'));
    }

    public function test_config_has_provider_section(): void
    {
        $this->assertNotNull(config('ai.providers.astria'));
        $this->assertTrue(config('ai.providers.astria.enabled'));
    }

    // ── Registry ───────────────────────────────────────────────────

    public function test_registry_is_singleton(): void
    {
        $first = $this->app->make(AiProviderRegistry::class);
        $second = $this->app->make(AiProviderRegistry::class);

        $this->assertSame($first, $second);
    }

    public function test_registry_has_astria_registered(): void
    {
        $registry = $this->app->make(AiProviderRegistry::class);

        $this->assertTrue($registry->has('astria'));
    }

    public function test_registry_resolves_astria_provider(): void
    {
        $registry = $this->app->make(AiProviderRegistry::class);
        $provider = $registry->resolve('astria');

        $this->assertInstanceOf(AiProviderContract::class, $provider);
        $this->assertInstanceOf(AstriaProvider::class, $provider);
        $this->assertSame('astria', $provider->providerKey());
    }

    public function test_registry_astria_descriptor_has_correct_role(): void
    {
        $registry = $this->app->make(AiProviderRegistry::class);
        $descriptor = $registry->descriptor('astria');

        $this->assertSame('Astria', $descriptor->displayName);
        $this->assertTrue($descriptor->capabilities->supportsTraining);
    }

    // ── Storage ────────────────────────────────────────────────────

    public function test_storage_contract_is_bound(): void
    {
        $this->assertTrue($this->app->bound(AiStorageContract::class));
    }

    public function test_storage_resolves_to_imagekit(): void
    {
        $storage = $this->app->make(AiStorageContract::class);

        $this->assertInstanceOf(ImageKitStorage::class, $storage);
    }

    public function test_storage_is_singleton(): void
    {
        $first = $this->app->make(AiStorageContract::class);
        $second = $this->app->make(AiStorageContract::class);

        $this->assertSame($first, $second);
    }

    public function test_storage_validates_configuration(): void
    {
        $storage = $this->app->make(AiStorageContract::class);

        $this->assertTrue($storage->validateConfiguration());
    }

    // ── Orchestration Service ─────────────────────────────────────

    public function test_orchestration_service_is_bound(): void
    {
        $this->assertTrue($this->app->bound(AiOrchestrationService::class));
    }

    public function test_orchestration_service_is_singleton(): void
    {
        $first = $this->app->make(AiOrchestrationService::class);
        $second = $this->app->make(AiOrchestrationService::class);

        $this->assertSame($first, $second);
    }

    public function test_orchestration_service_is_correct_type(): void
    {
        $service = $this->app->make(AiOrchestrationService::class);

        $this->assertInstanceOf(AiOrchestrationService::class, $service);
    }

    // ── Cost Service ────────────────────────────────────────────────

    public function test_cost_service_is_bound(): void
    {
        $this->assertTrue($this->app->bound(AiCostService::class));
    }

    public function test_cost_service_is_singleton(): void
    {
        $first = $this->app->make(AiCostService::class);
        $second = $this->app->make(AiCostService::class);

        $this->assertSame($first, $second);
    }

    public function test_cost_service_is_correct_type(): void
    {
        $service = $this->app->make(AiCostService::class);

        $this->assertInstanceOf(AiCostService::class, $service);
    }

    // ── Disabled Provider ──────────────────────────────────────────

    public function test_astria_not_registered_when_disabled(): void
    {
        // Create fresh app with Astria disabled
        $app = $this->resolveApplication();
        $this->resolveApplicationConfiguration($app);
        $app['config']->set('ai.providers.astria.enabled', false);

        $provider = new AIServiceProvider($app);
        $provider->register();
        $provider->boot();

        $registry = $app->make(AiProviderRegistry::class);

        $this->assertFalse($registry->has('astria'));
    }
}
