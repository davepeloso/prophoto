<?php

namespace ProPhoto\Intelligence\Registry;

use InvalidArgumentException;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Intelligence\Generators\DemoEmbeddingGenerator;
use ProPhoto\Intelligence\Generators\DemoTaggingGenerator;
use ProPhoto\Intelligence\Planning\GeneratorDescriptor;
use RuntimeException;

class IntelligenceGeneratorRegistry
{
    /**
     * @var array<string, GeneratorDescriptor>
     */
    protected array $descriptors = [];

    /**
     * @var array<string, callable(): AssetIntelligenceGeneratorContract>
     */
    protected array $resolvers = [];

    public function __construct(
        ?callable $demoTaggingResolver = null,
        ?callable $demoEmbeddingResolver = null
    ) {
        $this->register(
            descriptor: new GeneratorDescriptor(
                generator_type: 'demo_tagging',
                generator_version: 'v1',
                supported_media_kinds: ['image', 'pdf'],
                produces_outputs: ['labels'],
                default_model_name: 'demo-tag-model',
                default_model_version: 'v1'
            ),
            resolver: $demoTaggingResolver ?? static fn (): AssetIntelligenceGeneratorContract => new DemoTaggingGenerator()
        );

        $this->register(
            descriptor: new GeneratorDescriptor(
                generator_type: 'demo_embedding',
                generator_version: 'v1',
                supported_media_kinds: ['image'],
                produces_outputs: ['embeddings'],
                default_model_name: 'demo-embedding-model',
                default_model_version: 'v1'
            ),
            resolver: $demoEmbeddingResolver ?? static fn (): AssetIntelligenceGeneratorContract => new DemoEmbeddingGenerator()
        );
    }

    /**
     * @return list<GeneratorDescriptor>
     */
    public function descriptors(): array
    {
        return array_values($this->descriptors);
    }

    public function descriptor(string $generatorType): GeneratorDescriptor
    {
        $descriptor = $this->descriptors[$generatorType] ?? null;

        if (! $descriptor instanceof GeneratorDescriptor) {
            throw new InvalidArgumentException("Unknown generator descriptor: {$generatorType}");
        }

        return $descriptor;
    }

    public function resolve(string $generatorType): AssetIntelligenceGeneratorContract
    {
        $resolver = $this->resolvers[$generatorType] ?? null;

        if (! is_callable($resolver)) {
            throw new InvalidArgumentException("Unknown generator resolver: {$generatorType}");
        }

        $generator = $resolver();
        if (! $generator instanceof AssetIntelligenceGeneratorContract) {
            throw new RuntimeException("Resolver for {$generatorType} did not return an AssetIntelligenceGeneratorContract.");
        }

        return $generator;
    }

    /**
     * @param callable(): AssetIntelligenceGeneratorContract $resolver
     */
    public function register(GeneratorDescriptor $descriptor, callable $resolver): void
    {
        $generatorType = $descriptor->generator_type;

        if (isset($this->descriptors[$generatorType])) {
            throw new InvalidArgumentException("Generator {$generatorType} is already registered.");
        }

        $this->descriptors[$generatorType] = $descriptor;
        $this->resolvers[$generatorType] = $resolver;
    }
}
