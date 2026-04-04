<?php

namespace ProPhoto\Intelligence\Tests\Unit\Registry;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\Contracts\Intelligence\AssetIntelligenceGeneratorContract;
use ProPhoto\Intelligence\Planning\GeneratorDescriptor;
use ProPhoto\Intelligence\Registry\IntelligenceGeneratorRegistry;

class IntelligenceGeneratorRegistryTest extends TestCase
{
    public function test_descriptors_include_v1_demo_generators(): void
    {
        $registry = new IntelligenceGeneratorRegistry();
        $descriptors = $registry->descriptors();

        $this->assertCount(2, $descriptors);

        $byType = [];
        foreach ($descriptors as $descriptor) {
            $byType[$descriptor->generator_type] = $descriptor;
        }

        $this->assertArrayHasKey('demo_tagging', $byType);
        $this->assertArrayHasKey('demo_embedding', $byType);

        $this->assertSame('v1', $byType['demo_tagging']->generator_version);
        $this->assertSame(['labels'], $byType['demo_tagging']->produces_outputs);
        $this->assertSame(['image', 'pdf'], $byType['demo_tagging']->supported_media_kinds);
        $this->assertSame('demo-tag-model', $byType['demo_tagging']->default_model_name);
        $this->assertSame('v1', $byType['demo_tagging']->default_model_version);

        $this->assertSame('v1', $byType['demo_embedding']->generator_version);
        $this->assertSame(['embeddings'], $byType['demo_embedding']->produces_outputs);
        $this->assertSame(['image'], $byType['demo_embedding']->supported_media_kinds);
        $this->assertSame('demo-embedding-model', $byType['demo_embedding']->default_model_name);
        $this->assertSame('v1', $byType['demo_embedding']->default_model_version);
    }

    public function test_resolve_returns_generator_implementation_by_type(): void
    {
        $registry = new IntelligenceGeneratorRegistry();

        $tagging = $registry->resolve('demo_tagging');
        $embedding = $registry->resolve('demo_embedding');

        $this->assertInstanceOf(AssetIntelligenceGeneratorContract::class, $tagging);
        $this->assertInstanceOf(AssetIntelligenceGeneratorContract::class, $embedding);
        $this->assertSame('demo_tagging', $tagging->generatorType());
        $this->assertSame('demo_embedding', $embedding->generatorType());
    }

    public function test_descriptor_throws_for_unknown_generator_type(): void
    {
        $registry = new IntelligenceGeneratorRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown generator descriptor: does_not_exist');

        $registry->descriptor('does_not_exist');
    }

    public function test_resolve_throws_for_unknown_generator_type(): void
    {
        $registry = new IntelligenceGeneratorRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown generator resolver: does_not_exist');

        $registry->resolve('does_not_exist');
    }

    public function test_register_throws_for_duplicate_generator_type(): void
    {
        $registry = new IntelligenceGeneratorRegistry();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Generator demo_tagging is already registered.');

        $registry->register(
            descriptor: new GeneratorDescriptor(
                generator_type: 'demo_tagging',
                generator_version: 'v2',
                supported_media_kinds: ['image'],
                produces_outputs: ['labels'],
                default_model_name: 'alt-model',
                default_model_version: 'v2'
            ),
            resolver: static fn (): AssetIntelligenceGeneratorContract => throw new \RuntimeException('not used')
        );
    }
}
