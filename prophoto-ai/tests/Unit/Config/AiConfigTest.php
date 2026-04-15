<?php

namespace ProPhoto\AI\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;

class AiConfigTest extends TestCase
{
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require __DIR__ . '/../../../config/ai.php';
    }

    public function test_config_has_default_provider(): void
    {
        $this->assertArrayHasKey('default_provider', $this->config);
        $this->assertSame('astria', $this->config['default_provider']);
    }

    public function test_config_has_providers_section(): void
    {
        $this->assertArrayHasKey('providers', $this->config);
        $this->assertArrayHasKey('astria', $this->config['providers']);
    }

    public function test_astria_config_has_required_keys(): void
    {
        $astria = $this->config['providers']['astria'];

        $this->assertArrayHasKey('enabled', $astria);
        $this->assertArrayHasKey('api_key', $astria);
        $this->assertArrayHasKey('api_base_url', $astria);
        $this->assertArrayHasKey('max_generations_per_model', $astria);
        $this->assertArrayHasKey('default_images_per_prompt', $astria);
        $this->assertArrayHasKey('model_expiry_days', $astria);
        $this->assertArrayHasKey('training_cost_cents', $astria);
        $this->assertArrayHasKey('generation_cost_cents', $astria);
        $this->assertArrayHasKey('preset', $astria);
        $this->assertArrayHasKey('model_type', $astria);
        $this->assertArrayHasKey('face_crop', $astria);
        $this->assertArrayHasKey('default_negative_prompt', $astria);
    }

    public function test_astria_config_defaults(): void
    {
        $astria = $this->config['providers']['astria'];

        $this->assertSame(5, $astria['max_generations_per_model']);
        $this->assertSame(8, $astria['default_images_per_prompt']);
        $this->assertSame(30, $astria['model_expiry_days']);
        $this->assertSame(150, $astria['training_cost_cents']);
        $this->assertSame(23, $astria['generation_cost_cents']);
        $this->assertSame('flux-lora-portrait', $astria['preset']);
        $this->assertSame('lora', $astria['model_type']);
        $this->assertTrue($astria['face_crop']);
    }

    public function test_config_has_storage_section(): void
    {
        $this->assertArrayHasKey('storage', $this->config);
        $this->assertArrayHasKey('driver', $this->config['storage']);
        $this->assertSame('imagekit', $this->config['storage']['driver']);
    }

    public function test_imagekit_storage_config_has_required_keys(): void
    {
        $imagekit = $this->config['storage']['imagekit'];

        $this->assertArrayHasKey('public_key', $imagekit);
        $this->assertArrayHasKey('private_key', $imagekit);
        $this->assertArrayHasKey('url_endpoint', $imagekit);
    }

    public function test_config_has_queue_section(): void
    {
        $queue = $this->config['queue'];

        $this->assertArrayHasKey('name', $queue);
        $this->assertArrayHasKey('max_training_poll_hours', $queue);
        $this->assertArrayHasKey('max_generation_poll_hours', $queue);
        $this->assertSame('ai', $queue['name']);
        $this->assertSame(24, $queue['max_training_poll_hours']);
        $this->assertSame(2, $queue['max_generation_poll_hours']);
    }
}
