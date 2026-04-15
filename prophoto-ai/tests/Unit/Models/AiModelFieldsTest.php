<?php

namespace ProPhoto\AI\Tests\Unit\Models;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Models\AiGeneratedPortrait;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Models\AiGenerationRequest;

class AiModelFieldsTest extends TestCase
{
    // ── AiGeneration ───────────────────────────────────────────────

    public function test_ai_generation_has_provider_key_in_fillable(): void
    {
        $model = new AiGeneration();

        $this->assertContains('provider_key', $model->getFillable());
    }

    public function test_ai_generation_has_external_model_id_in_fillable(): void
    {
        $model = new AiGeneration();

        $this->assertContains('external_model_id', $model->getFillable());
    }

    public function test_ai_generation_has_provider_metadata_in_fillable(): void
    {
        $model = new AiGeneration();

        $this->assertContains('provider_metadata', $model->getFillable());
    }

    public function test_ai_generation_casts_provider_metadata_as_array(): void
    {
        $model = new AiGeneration();
        $casts = $model->getCasts();

        $this->assertArrayHasKey('provider_metadata', $casts);
        $this->assertSame('array', $casts['provider_metadata']);
    }

    public function test_ai_generation_preserves_existing_fillable(): void
    {
        $model = new AiGeneration();
        $fillable = $model->getFillable();

        $this->assertContains('gallery_id', $fillable);
        $this->assertContains('fine_tune_id', $fillable);
        $this->assertContains('model_status', $fillable);
        $this->assertContains('fine_tune_cost', $fillable);
    }

    // ── AiGenerationRequest ────────────────────────────────────────

    public function test_ai_generation_request_has_provider_key_in_fillable(): void
    {
        $model = new AiGenerationRequest();

        $this->assertContains('provider_key', $model->getFillable());
    }

    public function test_ai_generation_request_has_external_request_id_in_fillable(): void
    {
        $model = new AiGenerationRequest();

        $this->assertContains('external_request_id', $model->getFillable());
    }

    public function test_ai_generation_request_has_provider_metadata_in_fillable(): void
    {
        $model = new AiGenerationRequest();

        $this->assertContains('provider_metadata', $model->getFillable());
    }

    public function test_ai_generation_request_casts_provider_metadata_as_array(): void
    {
        $model = new AiGenerationRequest();
        $casts = $model->getCasts();

        $this->assertArrayHasKey('provider_metadata', $casts);
        $this->assertSame('array', $casts['provider_metadata']);
    }

    public function test_ai_generation_request_preserves_existing_fillable(): void
    {
        $model = new AiGenerationRequest();
        $fillable = $model->getFillable();

        $this->assertContains('ai_generation_id', $fillable);
        $this->assertContains('custom_prompt', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('generation_cost', $fillable);
    }

    // ── AiGeneratedPortrait ────────────────────────────────────────

    public function test_ai_generated_portrait_has_storage_driver_in_fillable(): void
    {
        $model = new AiGeneratedPortrait();

        $this->assertContains('storage_driver', $model->getFillable());
    }

    public function test_ai_generated_portrait_has_original_provider_url_in_fillable(): void
    {
        $model = new AiGeneratedPortrait();

        $this->assertContains('original_provider_url', $model->getFillable());
    }

    public function test_ai_generated_portrait_preserves_existing_fillable(): void
    {
        $model = new AiGeneratedPortrait();
        $fillable = $model->getFillable();

        $this->assertContains('imagekit_file_id', $fillable);
        $this->assertContains('imagekit_url', $fillable);
        $this->assertContains('imagekit_thumbnail_url', $fillable);
        $this->assertContains('file_size', $fillable);
    }
}
