<?php

namespace ProPhoto\AI\Registry;

use InvalidArgumentException;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\DTOs\AI\AiProviderDescriptor;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use RuntimeException;

/**
 * Story 8.1 — Provider-agnostic registry for AI generation providers.
 *
 * Follows the same descriptor + lazy-resolver pattern as IntelligenceGeneratorRegistry.
 * Providers are registered during boot (via AIServiceProvider) and resolved on demand.
 * The registry never instantiates a provider until it's actually needed.
 */
class AiProviderRegistry
{
    /**
     * @var array<string, AiProviderDescriptor>
     */
    protected array $descriptors = [];

    /**
     * @var array<string, callable(): AiProviderContract>
     */
    protected array $resolvers = [];

    /**
     * Register a provider with its descriptor and lazy resolver.
     *
     * @param callable(): AiProviderContract $resolver
     *
     * @throws InvalidArgumentException if provider key is already registered
     */
    public function register(AiProviderDescriptor $descriptor, callable $resolver): void
    {
        $key = $descriptor->providerKey;

        if (isset($this->descriptors[$key])) {
            throw new InvalidArgumentException("AI provider '{$key}' is already registered.");
        }

        $this->descriptors[$key] = $descriptor;
        $this->resolvers[$key] = $resolver;
    }

    /**
     * Resolve a provider instance by key. Lazy-loads via the registered callable.
     *
     * @throws InvalidArgumentException if provider key is not registered
     * @throws RuntimeException if resolver does not return an AiProviderContract
     */
    public function resolve(string $providerKey): AiProviderContract
    {
        $resolver = $this->resolvers[$providerKey] ?? null;

        if (! is_callable($resolver)) {
            throw new InvalidArgumentException("Unknown AI provider: '{$providerKey}'.");
        }

        $provider = $resolver();

        if (! $provider instanceof AiProviderContract) {
            throw new RuntimeException("Resolver for '{$providerKey}' did not return an AiProviderContract.");
        }

        return $provider;
    }

    /**
     * Resolve the configured default provider.
     *
     * @throws InvalidArgumentException if default provider is not registered
     */
    public function default(): AiProviderContract
    {
        $defaultKey = config('ai.default_provider', 'astria');

        return $this->resolve($defaultKey);
    }

    /**
     * Check if a provider is registered.
     */
    public function has(string $providerKey): bool
    {
        return isset($this->descriptors[$providerKey]);
    }

    /**
     * Get all registered provider descriptors.
     *
     * @return list<AiProviderDescriptor>
     */
    public function all(): array
    {
        return array_values($this->descriptors);
    }

    /**
     * Get descriptors matching a specific provider role.
     *
     * @return list<AiProviderDescriptor>
     */
    public function forRole(ProviderRole $role): array
    {
        return array_values(
            array_filter(
                $this->descriptors,
                static fn (AiProviderDescriptor $d): bool => $d->providerRole === $role
            )
        );
    }

    /**
     * Get a single descriptor by provider key.
     *
     * @throws InvalidArgumentException if provider key is not registered
     */
    public function descriptor(string $providerKey): AiProviderDescriptor
    {
        $descriptor = $this->descriptors[$providerKey] ?? null;

        if (! $descriptor instanceof AiProviderDescriptor) {
            throw new InvalidArgumentException("Unknown AI provider descriptor: '{$providerKey}'.");
        }

        return $descriptor;
    }
}
