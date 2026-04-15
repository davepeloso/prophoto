<?php

namespace ProPhoto\AI\Tests\Unit\Providers\Astria;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Providers\Astria\AstriaConfig;

class AstriaConfigTest extends TestCase
{
    public function test_construction_with_defaults(): void
    {
        $config = new AstriaConfig(apiKey: 'sd_test_key_123');

        $this->assertSame('sd_test_key_123', $config->apiKey());
        $this->assertSame('https://api.astria.ai', $config->baseUrl());
        $this->assertSame(150, $config->trainingCostCents());
        $this->assertSame(23, $config->generationCostCents());
        $this->assertSame(5, $config->maxGenerationsPerModel());
        $this->assertSame(8, $config->defaultPromptImageCount());
        $this->assertSame(30, $config->modelExpiryDays());
        $this->assertSame('flux-lora-portrait', $config->preset());
        $this->assertSame('lora', $config->modelType());
        $this->assertTrue($config->faceCrop());
    }

    public function test_construction_with_custom_values(): void
    {
        $config = new AstriaConfig(
            apiKey: 'sd_custom',
            baseUrl: 'https://custom.api.astria.ai/',
            trainingCostCents: 200,
            generationCostCents: 50,
            maxGenerationsPerModel: 10,
        );

        $this->assertSame('sd_custom', $config->apiKey());
        $this->assertSame('https://custom.api.astria.ai', $config->baseUrl()); // trailing slash trimmed
        $this->assertSame(200, $config->trainingCostCents());
        $this->assertSame(50, $config->generationCostCents());
        $this->assertSame(10, $config->maxGenerationsPerModel());
    }

    public function test_validate_returns_true_for_valid_key(): void
    {
        $config = new AstriaConfig(apiKey: 'sd_valid_key');

        $this->assertTrue($config->validate());
    }

    public function test_validate_returns_false_for_empty_key(): void
    {
        $config = new AstriaConfig(apiKey: '');

        $this->assertFalse($config->validate());
    }

    public function test_validate_returns_false_for_invalid_prefix(): void
    {
        $config = new AstriaConfig(apiKey: 'invalid_key_no_prefix');

        $this->assertFalse($config->validate());
    }

    public function test_from_config_factory(): void
    {
        $config = AstriaConfig::fromConfig([
            'api_key' => 'sd_factory_test',
            'api_base_url' => 'https://api.astria.ai',
            'training_cost_cents' => 175,
            'generation_cost_cents' => 30,
            'max_generations_per_model' => 3,
            'default_images_per_prompt' => 4,
            'model_expiry_days' => 14,
            'preset' => 'custom-preset',
            'model_type' => 'full',
            'face_crop' => false,
        ]);

        $this->assertSame('sd_factory_test', $config->apiKey());
        $this->assertSame(175, $config->trainingCostCents());
        $this->assertSame(30, $config->generationCostCents());
        $this->assertSame(3, $config->maxGenerationsPerModel());
        $this->assertSame(4, $config->defaultPromptImageCount());
        $this->assertSame(14, $config->modelExpiryDays());
        $this->assertSame('custom-preset', $config->preset());
        $this->assertSame('full', $config->modelType());
        $this->assertFalse($config->faceCrop());
    }

    public function test_from_config_uses_defaults_for_missing_keys(): void
    {
        $config = AstriaConfig::fromConfig([]);

        $this->assertSame('', $config->apiKey());
        $this->assertSame('https://api.astria.ai', $config->baseUrl());
        $this->assertSame(150, $config->trainingCostCents());
        $this->assertFalse($config->validate());
    }

    public function test_default_negative_prompt_is_populated(): void
    {
        $config = new AstriaConfig(apiKey: 'sd_test');

        $this->assertStringContainsString('double torso', $config->defaultNegativePrompt());
        $this->assertStringContainsString('disfigured', $config->defaultNegativePrompt());
    }
}
