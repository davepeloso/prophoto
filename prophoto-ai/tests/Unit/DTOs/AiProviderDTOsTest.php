<?php

namespace ProPhoto\AI\Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use ProPhoto\Contracts\DTOs\AI\AiProviderCapabilities;
use ProPhoto\Contracts\DTOs\AI\AiProviderDescriptor;
use ProPhoto\Contracts\DTOs\AI\GenerationRequest;
use ProPhoto\Contracts\DTOs\AI\GenerationResponse;
use ProPhoto\Contracts\DTOs\AI\GenerationStatusResponse;
use ProPhoto\Contracts\DTOs\AI\Money;
use ProPhoto\Contracts\DTOs\AI\StorageResult;
use ProPhoto\Contracts\DTOs\AI\TrainingRequest;
use ProPhoto\Contracts\DTOs\AI\TrainingResponse;
use ProPhoto\Contracts\DTOs\AI\TrainingStatusResponse;
use ProPhoto\Contracts\Enums\AI\GenerationStatus;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use ProPhoto\Contracts\Enums\AI\TrainingStatus;

class AiProviderDTOsTest extends TestCase
{
    // ── AiProviderCapabilities ──────────────────────────────────────

    public function test_capabilities_construction(): void
    {
        $caps = new AiProviderCapabilities(
            supportsTraining: true,
            supportsGeneration: true,
            supportsVideo: false,
            minTrainingImages: 8,
            maxTrainingImages: 20,
            maxGenerationsPerModel: 5,
            supportedOutputFormats: ['png', 'jpg'],
        );

        $this->assertTrue($caps->supportsTraining);
        $this->assertTrue($caps->supportsGeneration);
        $this->assertFalse($caps->supportsVideo);
        $this->assertSame(8, $caps->minTrainingImages);
        $this->assertSame(20, $caps->maxTrainingImages);
        $this->assertSame(5, $caps->maxGenerationsPerModel);
        $this->assertSame(['png', 'jpg'], $caps->supportedOutputFormats);
    }

    public function test_capabilities_defaults(): void
    {
        $caps = new AiProviderCapabilities(
            supportsTraining: false,
            supportsGeneration: true,
        );

        $this->assertFalse($caps->supportsVideo);
        $this->assertSame(0, $caps->minTrainingImages);
        $this->assertSame(0, $caps->maxTrainingImages);
        $this->assertNull($caps->maxGenerationsPerModel);
        $this->assertSame(['png', 'jpg'], $caps->supportedOutputFormats);
    }

    // ── AiProviderDescriptor ────────────────────────────────────────

    public function test_descriptor_construction(): void
    {
        $caps = new AiProviderCapabilities(supportsTraining: true, supportsGeneration: true);
        $descriptor = new AiProviderDescriptor(
            providerKey: 'astria',
            displayName: 'Astria',
            providerRole: ProviderRole::IDENTITY_GENERATION,
            capabilities: $caps,
            defaultConfig: ['preset' => 'flux-lora-portrait'],
        );

        $this->assertSame('astria', $descriptor->providerKey);
        $this->assertSame('Astria', $descriptor->displayName);
        $this->assertSame(ProviderRole::IDENTITY_GENERATION, $descriptor->providerRole);
        $this->assertSame($caps, $descriptor->capabilities);
        $this->assertSame(['preset' => 'flux-lora-portrait'], $descriptor->defaultConfig);
    }

    // ── TrainingRequest ─────────────────────────────────────────────

    public function test_training_request_construction(): void
    {
        $request = new TrainingRequest(
            providerKey: 'astria',
            imageUrls: ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            subjectName: 'man',
        );

        $this->assertSame('astria', $request->providerKey);
        $this->assertCount(2, $request->imageUrls);
        $this->assertSame('man', $request->subjectName);
        $this->assertNull($request->callbackUrl);
        $this->assertSame([], $request->metadata);
    }

    // ── TrainingResponse ────────────────────────────────────────────

    public function test_training_response_with_cost(): void
    {
        $response = new TrainingResponse(
            externalModelId: '12345',
            estimatedDurationSeconds: 900,
            cost: new Money(150),
        );

        $this->assertSame('12345', $response->externalModelId);
        $this->assertSame(900, $response->estimatedDurationSeconds);
        $this->assertSame(150, $response->cost->amount);
    }

    // ── TrainingStatusResponse ──────────────────────────────────────

    public function test_training_status_trained(): void
    {
        $status = new TrainingStatusResponse(
            status: TrainingStatus::TRAINED,
            externalModelId: '12345',
            completedAt: '2026-04-15T12:00:00Z',
            expiresAt: '2026-05-15T12:00:00Z',
        );

        $this->assertSame(TrainingStatus::TRAINED, $status->status);
        $this->assertNull($status->errorMessage);
        $this->assertSame('2026-05-15T12:00:00Z', $status->expiresAt);
    }

    public function test_training_status_failed(): void
    {
        $status = new TrainingStatusResponse(
            status: TrainingStatus::FAILED,
            externalModelId: '12345',
            errorMessage: 'Insufficient training images',
        );

        $this->assertSame(TrainingStatus::FAILED, $status->status);
        $this->assertSame('Insufficient training images', $status->errorMessage);
    }

    // ── GenerationRequest ───────────────────────────────────────────

    public function test_generation_request_defaults(): void
    {
        $request = new GenerationRequest(
            externalModelId: '12345',
            prompt: 'professional headshot, studio lighting',
        );

        $this->assertSame('12345', $request->externalModelId);
        $this->assertSame(8, $request->numImages);
        $this->assertSame([], $request->metadata);
    }

    public function test_generation_request_custom_count(): void
    {
        $request = new GenerationRequest(
            externalModelId: '12345',
            prompt: 'headshot',
            numImages: 4,
        );

        $this->assertSame(4, $request->numImages);
    }

    // ── GenerationResponse ──────────────────────────────────────────

    public function test_generation_response(): void
    {
        $response = new GenerationResponse(
            externalRequestId: '67890',
            estimatedDurationSeconds: 30,
            cost: new Money(23),
        );

        $this->assertSame('67890', $response->externalRequestId);
        $this->assertSame(0.23, $response->cost->toDollars());
    }

    // ── GenerationStatusResponse ────────────────────────────────────

    public function test_generation_status_completed_with_images(): void
    {
        $status = new GenerationStatusResponse(
            status: GenerationStatus::COMPLETED,
            imageUrls: [
                'https://cdn.astria.ai/output/1.jpg',
                'https://cdn.astria.ai/output/2.jpg',
            ],
        );

        $this->assertSame(GenerationStatus::COMPLETED, $status->status);
        $this->assertCount(2, $status->imageUrls);
        $this->assertNull($status->errorMessage);
    }

    public function test_generation_status_pending(): void
    {
        $status = new GenerationStatusResponse(
            status: GenerationStatus::PENDING,
        );

        $this->assertSame(GenerationStatus::PENDING, $status->status);
        $this->assertSame([], $status->imageUrls);
    }

    // ── StorageResult ───────────────────────────────────────────────

    public function test_storage_result(): void
    {
        $result = new StorageResult(
            fileId: 'ik_abc123',
            url: 'https://ik.imagekit.io/demo/portrait.jpg',
            thumbnailUrl: 'https://ik.imagekit.io/demo/tr:n-ik_ml_thumbnail/portrait.jpg',
            fileSize: 94466,
        );

        $this->assertSame('ik_abc123', $result->fileId);
        $this->assertSame(94466, $result->fileSize);
        $this->assertSame([], $result->metadata);
    }
}
