<?php

namespace ProPhoto\AI\Tests\Unit\Providers\Astria;

use PHPUnit\Framework\TestCase;
use ProPhoto\AI\Providers\Astria\AstriaApiClient;
use ProPhoto\AI\Providers\Astria\AstriaConfig;
use ProPhoto\AI\Providers\Astria\AstriaProvider;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\DTOs\AI\GenerationRequest;
use ProPhoto\Contracts\DTOs\AI\TrainingRequest;
use ProPhoto\Contracts\Enums\AI\GenerationStatus;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use ProPhoto\Contracts\Enums\AI\TrainingStatus;

class AstriaProviderTest extends TestCase
{
    private function makeProvider(?AstriaApiClient $client = null, ?AstriaConfig $config = null): AstriaProvider
    {
        $config ??= new AstriaConfig(
            apiKey: 'sd_test_key',
            trainingCostCents: 150,
            generationCostCents: 23,
            maxGenerationsPerModel: 5,
            modelExpiryDays: 30,
        );

        $client ??= $this->createMock(AstriaApiClient::class);

        return new AstriaProvider($client, $config);
    }

    // ── Identity ───────────────────────────────────────────────────

    public function test_implements_ai_provider_contract(): void
    {
        $provider = $this->makeProvider();

        $this->assertInstanceOf(AiProviderContract::class, $provider);
    }

    public function test_provider_key(): void
    {
        $this->assertSame('astria', $this->makeProvider()->providerKey());
    }

    public function test_display_name(): void
    {
        $this->assertSame('Astria', $this->makeProvider()->displayName());
    }

    public function test_provider_role(): void
    {
        $this->assertSame(ProviderRole::IDENTITY_GENERATION, $this->makeProvider()->providerRole());
    }

    // ── Capabilities ───────────────────────────────────────────────

    public function test_capabilities(): void
    {
        $caps = $this->makeProvider()->capabilities();

        $this->assertTrue($caps->supportsTraining);
        $this->assertTrue($caps->supportsGeneration);
        $this->assertFalse($caps->supportsVideo);
        $this->assertSame(8, $caps->minTrainingImages);
        $this->assertSame(20, $caps->maxTrainingImages);
        $this->assertSame(5, $caps->maxGenerationsPerModel);
    }

    // ── Configuration ──────────────────────────────────────────────

    public function test_validate_configuration_with_valid_key(): void
    {
        $this->assertTrue($this->makeProvider()->validateConfiguration());
    }

    public function test_validate_configuration_with_invalid_key(): void
    {
        $config = new AstriaConfig(apiKey: 'invalid');
        $provider = $this->makeProvider(config: $config);

        $this->assertFalse($provider->validateConfiguration());
    }

    // ── Training ───────────────────────────────────────────────────

    public function test_submit_training_maps_to_astria_tune(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->expects($this->once())
            ->method('createTune')
            ->with(
                ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
                'man',
                $this->stringStartsWith('prophoto_'),
                null,
            )
            ->willReturn([
                'id' => 12345,
                'title' => 'prophoto_abc',
                'eta' => null,
                'started_training_at' => null,
                'trained_at' => null,
            ]);

        $provider = $this->makeProvider(client: $client);

        $response = $provider->submitTraining(new TrainingRequest(
            providerKey: 'astria',
            imageUrls: ['https://example.com/1.jpg', 'https://example.com/2.jpg'],
            subjectName: 'man',
        ));

        $this->assertSame('12345', $response->externalModelId);
        $this->assertSame(150, $response->cost->amount);
        $this->assertSame(12345, $response->metadata['astria_tune_id']);
    }

    public function test_submit_training_with_callback(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->expects($this->once())
            ->method('createTune')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                'https://prophoto.test/webhooks/astria',
            )
            ->willReturn(['id' => 1, 'eta' => null]);

        $provider = $this->makeProvider(client: $client);

        $provider->submitTraining(new TrainingRequest(
            providerKey: 'astria',
            imageUrls: ['https://example.com/1.jpg'],
            subjectName: 'woman',
            callbackUrl: 'https://prophoto.test/webhooks/astria',
        ));
    }

    // ── Training Status ────────────────────────────────────────────

    public function test_training_status_pending(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getTune')->willReturn([
            'id' => 12345,
            'started_training_at' => null,
            'trained_at' => null,
        ]);

        $status = $this->makeProvider(client: $client)->getTrainingStatus('12345');

        $this->assertSame(TrainingStatus::PENDING, $status->status);
        $this->assertSame('12345', $status->externalModelId);
        $this->assertNull($status->completedAt);
        $this->assertNull($status->expiresAt);
    }

    public function test_training_status_training(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getTune')->willReturn([
            'id' => 12345,
            'started_training_at' => '2026-04-15T12:00:00Z',
            'trained_at' => null,
        ]);

        $status = $this->makeProvider(client: $client)->getTrainingStatus('12345');

        $this->assertSame(TrainingStatus::TRAINING, $status->status);
    }

    public function test_training_status_trained_with_expiry(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getTune')->willReturn([
            'id' => 12345,
            'started_training_at' => '2026-04-15T12:00:00Z',
            'trained_at' => '2026-04-15T14:00:00Z',
        ]);

        $status = $this->makeProvider(client: $client)->getTrainingStatus('12345');

        $this->assertSame(TrainingStatus::TRAINED, $status->status);
        $this->assertSame('2026-04-15T14:00:00Z', $status->completedAt);
        $this->assertNotNull($status->expiresAt);
        // Expiry should be 30 days after trained_at
        $this->assertStringContainsString('2026-05-15', $status->expiresAt);
    }

    public function test_training_status_failed_with_error(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getTune')->willReturn([
            'id' => 12345,
            'started_training_at' => '2026-04-15T12:00:00Z',
            'trained_at' => null,
            'error_message' => 'Insufficient training images',
        ]);

        $status = $this->makeProvider(client: $client)->getTrainingStatus('12345');

        $this->assertSame(TrainingStatus::FAILED, $status->status);
        $this->assertSame('Insufficient training images', $status->errorMessage);
    }

    // ── Generation ─────────────────────────────────────────────────

    public function test_submit_generation_maps_to_astria_prompt(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->expects($this->once())
            ->method('createPrompt')
            ->with(
                12345,
                'professional headshot, studio lighting',
                $this->stringContains('double torso'), // default negative prompt
                8,
                null,
            )
            ->willReturn([
                'id' => 67890,
                'images' => [],
            ]);

        $provider = $this->makeProvider(client: $client);

        $response = $provider->submitGeneration(new GenerationRequest(
            externalModelId: '12345',
            prompt: 'professional headshot, studio lighting',
        ));

        $this->assertSame('12345:67890', $response->externalRequestId);
        $this->assertSame(23, $response->cost->amount);
        $this->assertSame(67890, $response->metadata['astria_prompt_id']);
        $this->assertSame(12345, $response->metadata['astria_tune_id']);
    }

    public function test_submit_generation_with_custom_negative_prompt(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->expects($this->once())
            ->method('createPrompt')
            ->with(
                12345,
                'headshot',
                'custom negative',
                4,
                null,
            )
            ->willReturn(['id' => 1, 'images' => []]);

        $provider = $this->makeProvider(client: $client);

        $provider->submitGeneration(new GenerationRequest(
            externalModelId: '12345',
            prompt: 'headshot',
            numImages: 4,
            metadata: ['negative_prompt' => 'custom negative'],
        ));
    }

    // ── Generation Status ──────────────────────────────────────────

    public function test_generation_status_processing(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getPrompt')->willReturn([
            'id' => 67890,
            'images' => [],
        ]);

        $status = $this->makeProvider(client: $client)->getGenerationStatus('12345:67890');

        $this->assertSame(GenerationStatus::PROCESSING, $status->status);
        $this->assertSame([], $status->imageUrls);
    }

    public function test_generation_status_completed_with_images(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getPrompt')->willReturn([
            'id' => 67890,
            'images' => [
                'https://cdn.astria.ai/output/1.jpg',
                'https://cdn.astria.ai/output/2.jpg',
            ],
        ]);

        $status = $this->makeProvider(client: $client)->getGenerationStatus('12345:67890');

        $this->assertSame(GenerationStatus::COMPLETED, $status->status);
        $this->assertCount(2, $status->imageUrls);
    }

    public function test_generation_status_failed(): void
    {
        $client = $this->createMock(AstriaApiClient::class);
        $client->method('getPrompt')->willReturn([
            'id' => 67890,
            'images' => [],
            'error_message' => 'Generation failed: NSFW content detected',
        ]);

        $status = $this->makeProvider(client: $client)->getGenerationStatus('12345:67890');

        $this->assertSame(GenerationStatus::FAILED, $status->status);
        $this->assertSame('Generation failed: NSFW content detected', $status->errorMessage);
    }

    public function test_generation_status_throws_for_invalid_id_format(): void
    {
        $provider = $this->makeProvider();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/tuneId:promptId/');

        $provider->getGenerationStatus('12345');
    }

    // ── Cost Estimation ────────────────────────────────────────────

    public function test_estimate_training_cost(): void
    {
        $cost = $this->makeProvider()->estimateTrainingCost(10);

        $this->assertSame(150, $cost->amount);
        $this->assertSame('USD', $cost->currency);
        $this->assertSame(1.5, $cost->toDollars());
    }

    public function test_estimate_generation_cost(): void
    {
        $cost = $this->makeProvider()->estimateGenerationCost(8);

        $this->assertSame(23, $cost->amount);
        $this->assertSame('USD', $cost->currency);
        $this->assertSame(0.23, $cost->toDollars());
    }

    public function test_estimate_generation_cost_flat_regardless_of_count(): void
    {
        $provider = $this->makeProvider();

        // Astria charges per prompt, not per image
        $this->assertSame(23, $provider->estimateGenerationCost(1)->amount);
        $this->assertSame(23, $provider->estimateGenerationCost(4)->amount);
        $this->assertSame(23, $provider->estimateGenerationCost(8)->amount);
    }
}
