<?php

namespace ProPhoto\AI\Tests\Unit\Registry;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\DTOs\AI\AiProviderCapabilities;
use ProPhoto\Contracts\DTOs\AI\AiProviderDescriptor;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use RuntimeException;

class AiProviderRegistryTest extends TestCase
{
    private function makeDescriptor(
        string $key = 'test_provider',
        ProviderRole $role = ProviderRole::IDENTITY_GENERATION,
    ): AiProviderDescriptor {
        return new AiProviderDescriptor(
            providerKey: $key,
            displayName: ucfirst($key),
            providerRole: $role,
            capabilities: new AiProviderCapabilities(
                supportsTraining: true,
                supportsGeneration: true,
                minTrainingImages: 8,
                maxTrainingImages: 20,
                maxGenerationsPerModel: 5,
            ),
        );
    }

    private function makeMockResolver(): callable
    {
        $mock = $this->createMock(AiProviderContract::class);
        $mock->method('providerKey')->willReturn('test_provider');

        return static fn (): AiProviderContract => $mock;
    }

    // ── Registration ────────────────────────────────────────────────

    public function test_register_and_resolve_provider(): void
    {
        $registry = new AiProviderRegistry();
        $descriptor = $this->makeDescriptor();
        $registry->register($descriptor, $this->makeMockResolver());

        $provider = $registry->resolve('test_provider');

        $this->assertInstanceOf(AiProviderContract::class, $provider);
        $this->assertSame('test_provider', $provider->providerKey());
    }

    public function test_duplicate_registration_throws(): void
    {
        $registry = new AiProviderRegistry();
        $descriptor = $this->makeDescriptor();
        $registry->register($descriptor, $this->makeMockResolver());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("AI provider 'test_provider' is already registered.");

        $registry->register($descriptor, $this->makeMockResolver());
    }

    // ── Resolution ──────────────────────────────────────────────────

    public function test_resolve_throws_for_unknown_provider(): void
    {
        $registry = new AiProviderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown AI provider: 'does_not_exist'.");

        $registry->resolve('does_not_exist');
    }

    public function test_resolve_throws_when_resolver_returns_wrong_type(): void
    {
        $registry = new AiProviderRegistry();
        $descriptor = $this->makeDescriptor();
        $registry->register($descriptor, static fn () => new \stdClass());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Resolver for 'test_provider' did not return an AiProviderContract.");

        $registry->resolve('test_provider');
    }

    // ── Has ─────────────────────────────────────────────────────────

    public function test_has_returns_true_for_registered_provider(): void
    {
        $registry = new AiProviderRegistry();
        $registry->register($this->makeDescriptor(), $this->makeMockResolver());

        $this->assertTrue($registry->has('test_provider'));
    }

    public function test_has_returns_false_for_unregistered_provider(): void
    {
        $registry = new AiProviderRegistry();

        $this->assertFalse($registry->has('unknown'));
    }

    // ── All ─────────────────────────────────────────────────────────

    public function test_all_returns_empty_array_when_no_providers(): void
    {
        $registry = new AiProviderRegistry();

        $this->assertSame([], $registry->all());
    }

    public function test_all_returns_registered_descriptors(): void
    {
        $registry = new AiProviderRegistry();
        $registry->register($this->makeDescriptor('alpha'), $this->makeMockResolver());
        $registry->register($this->makeDescriptor('beta'), $this->makeMockResolver());

        $all = $registry->all();

        $this->assertCount(2, $all);
        $keys = array_map(fn ($d) => $d->providerKey, $all);
        $this->assertContains('alpha', $keys);
        $this->assertContains('beta', $keys);
    }

    // ── ForRole ─────────────────────────────────────────────────────

    public function test_for_role_filters_by_provider_role(): void
    {
        $registry = new AiProviderRegistry();
        $registry->register(
            $this->makeDescriptor('astria', ProviderRole::IDENTITY_GENERATION),
            $this->makeMockResolver()
        );
        $registry->register(
            $this->makeDescriptor('fal', ProviderRole::REALTIME_GENERATION),
            $this->makeMockResolver()
        );
        $registry->register(
            $this->makeDescriptor('magnific', ProviderRole::ENHANCEMENT),
            $this->makeMockResolver()
        );

        $identity = $registry->forRole(ProviderRole::IDENTITY_GENERATION);
        $realtime = $registry->forRole(ProviderRole::REALTIME_GENERATION);
        $commercial = $registry->forRole(ProviderRole::COMMERCIAL_BACKGROUND);

        $this->assertCount(1, $identity);
        $this->assertSame('astria', $identity[0]->providerKey);

        $this->assertCount(1, $realtime);
        $this->assertSame('fal', $realtime[0]->providerKey);

        $this->assertCount(0, $commercial);
    }

    // ── Descriptor ──────────────────────────────────────────────────

    public function test_descriptor_returns_registered_descriptor(): void
    {
        $registry = new AiProviderRegistry();
        $original = $this->makeDescriptor();
        $registry->register($original, $this->makeMockResolver());

        $retrieved = $registry->descriptor('test_provider');

        $this->assertSame($original, $retrieved);
    }

    public function test_descriptor_throws_for_unknown_provider(): void
    {
        $registry = new AiProviderRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown AI provider descriptor: 'missing'.");

        $registry->descriptor('missing');
    }
}
