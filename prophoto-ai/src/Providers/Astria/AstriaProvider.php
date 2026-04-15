<?php

namespace ProPhoto\AI\Providers\Astria;

use Carbon\Carbon;
use ProPhoto\Contracts\Contracts\AI\AiProviderContract;
use ProPhoto\Contracts\DTOs\AI\AiProviderCapabilities;
use ProPhoto\Contracts\DTOs\AI\GenerationRequest;
use ProPhoto\Contracts\DTOs\AI\GenerationResponse;
use ProPhoto\Contracts\DTOs\AI\GenerationStatusResponse;
use ProPhoto\Contracts\DTOs\AI\Money;
use ProPhoto\Contracts\DTOs\AI\TrainingRequest;
use ProPhoto\Contracts\DTOs\AI\TrainingResponse;
use ProPhoto\Contracts\DTOs\AI\TrainingStatusResponse;
use ProPhoto\Contracts\Enums\AI\GenerationStatus;
use ProPhoto\Contracts\Enums\AI\ProviderRole;
use ProPhoto\Contracts\Enums\AI\TrainingStatus;

/**
 * Astria.ai provider implementation.
 *
 * Maps Astria's API concepts to the provider-agnostic contract:
 *   - Astria "tune"   → our "training" (fine-tuning a model on subject images)
 *   - Astria "prompt"  → our "generation" (generating portraits from a trained model)
 *
 * Astria uses timestamps for status tracking (trained_at, started_training_at)
 * rather than explicit status strings. This provider maps those to our enum-based
 * TrainingStatus and GenerationStatus.
 */
class AstriaProvider implements AiProviderContract
{
    public function __construct(
        private readonly AstriaApiClient $client,
        private readonly AstriaConfig $config,
    ) {}

    public function providerKey(): string
    {
        return 'astria';
    }

    public function displayName(): string
    {
        return 'Astria';
    }

    public function providerRole(): ProviderRole
    {
        return ProviderRole::IDENTITY_GENERATION;
    }

    public function capabilities(): AiProviderCapabilities
    {
        return new AiProviderCapabilities(
            supportsTraining: true,
            supportsGeneration: true,
            supportsVideo: false,
            minTrainingImages: 8,
            maxTrainingImages: 20,
            maxGenerationsPerModel: $this->config->maxGenerationsPerModel(),
            supportedOutputFormats: ['png', 'jpg'],
        );
    }

    public function validateConfiguration(): bool
    {
        return $this->config->validate();
    }

    /**
     * Submit images for model training (creates an Astria tune).
     *
     * Maps TrainingRequest → Astria createTune API call.
     * The subjectName becomes the Astria "name" (class: man/woman/person).
     * We use the providerKey + a unique identifier as the title for idempotency.
     */
    public function submitTraining(TrainingRequest $request): TrainingResponse
    {
        $callbackUrl = null;
        if ($request->callbackUrl !== null) {
            $callbackUrl = $request->callbackUrl;
        }

        // Use metadata title if provided, otherwise generate one
        $title = $request->metadata['title'] ?? ('prophoto_' . uniqid());

        $response = $this->client->createTune(
            imageUrls: $request->imageUrls,
            className: $request->subjectName,
            title: $title,
            callbackUrl: $callbackUrl,
        );

        // Astria returns eta as a timestamp string — parse to seconds from now
        $estimatedSeconds = null;
        if (! empty($response['eta'])) {
            $eta = Carbon::parse($response['eta']);
            $estimatedSeconds = max(0, (int) $eta->diffInSeconds(now()));
        }

        return new TrainingResponse(
            externalModelId: (string) $response['id'],
            estimatedDurationSeconds: $estimatedSeconds,
            cost: new Money($this->config->trainingCostCents()),
            metadata: [
                'astria_tune_id' => $response['id'],
                'title' => $response['title'] ?? $title,
            ],
        );
    }

    /**
     * Poll training status by checking the Astria tune's timestamp fields.
     *
     * Astria status mapping:
     *   - trained_at non-null       → TrainingStatus::TRAINED
     *   - started_training_at non-null → TrainingStatus::TRAINING
     *   - otherwise                  → TrainingStatus::PENDING
     *   - Webhook status 'failed'   → TrainingStatus::FAILED (handled by caller via webhook)
     */
    public function getTrainingStatus(string $externalModelId): TrainingStatusResponse
    {
        $tune = $this->client->getTune((int) $externalModelId);

        $status = $this->mapTrainingStatus($tune);
        $completedAt = $tune['trained_at'] ?? null;

        // Calculate expiry: trained_at + model_expiry_days
        $expiresAt = null;
        if ($completedAt !== null) {
            $expiresAt = Carbon::parse($completedAt)
                ->addDays($this->config->modelExpiryDays())
                ->toIso8601String();
        }

        // Check for error info in the tune response
        $errorMessage = $tune['error_message'] ?? $tune['error'] ?? null;
        if ($errorMessage !== null && $status !== TrainingStatus::FAILED) {
            $status = TrainingStatus::FAILED;
        }

        return new TrainingStatusResponse(
            status: $status,
            externalModelId: $externalModelId,
            errorMessage: $errorMessage,
            completedAt: $completedAt,
            expiresAt: $expiresAt,
            metadata: [
                'astria_tune_id' => (int) $externalModelId,
                'started_training_at' => $tune['started_training_at'] ?? null,
            ],
        );
    }

    /**
     * Submit a generation request (creates an Astria prompt against a trained tune).
     */
    public function submitGeneration(GenerationRequest $request): GenerationResponse
    {
        $callbackUrl = $request->metadata['callback_url'] ?? null;
        $negativePrompt = $request->metadata['negative_prompt']
            ?? $this->config->defaultNegativePrompt();

        $response = $this->client->createPrompt(
            tuneId: (int) $request->externalModelId,
            prompt: $request->prompt,
            negativePrompt: $negativePrompt,
            numImages: $request->numImages,
            callbackUrl: $callbackUrl,
        );

        // Return composite ID (tuneId:promptId) so getGenerationStatus() can parse both
        $compositeId = $request->externalModelId . ':' . $response['id'];

        return new GenerationResponse(
            externalRequestId: $compositeId,
            estimatedDurationSeconds: null,
            cost: new Money($this->config->generationCostCents()),
            metadata: [
                'astria_prompt_id' => $response['id'],
                'astria_tune_id' => (int) $request->externalModelId,
            ],
        );
    }

    /**
     * Poll generation status by checking whether the Astria prompt has images.
     *
     * Astria status mapping:
     *   - images array populated → GenerationStatus::COMPLETED
     *   - images array empty     → GenerationStatus::PROCESSING
     */
    public function getGenerationStatus(string $externalRequestId): GenerationStatusResponse
    {
        // The externalRequestId format is "{tuneId}:{promptId}" or just "{promptId}"
        // with tuneId stored in metadata. For polling we need both.
        $parts = explode(':', $externalRequestId);
        if (count($parts) === 2) {
            $tuneId = (int) $parts[0];
            $promptId = (int) $parts[1];
        } else {
            // Fallback — caller must pass composite ID
            throw new \InvalidArgumentException(
                "externalRequestId must be in format 'tuneId:promptId', got: {$externalRequestId}"
            );
        }

        $prompt = $this->client->getPrompt($tuneId, $promptId);

        $images = $prompt['images'] ?? [];
        $errorMessage = $prompt['error_message'] ?? $prompt['error'] ?? null;

        if ($errorMessage !== null) {
            return new GenerationStatusResponse(
                status: GenerationStatus::FAILED,
                imageUrls: [],
                errorMessage: $errorMessage,
                metadata: ['astria_prompt_id' => $promptId],
            );
        }

        if (! empty($images)) {
            return new GenerationStatusResponse(
                status: GenerationStatus::COMPLETED,
                imageUrls: $images,
                metadata: ['astria_prompt_id' => $promptId],
            );
        }

        return new GenerationStatusResponse(
            status: GenerationStatus::PROCESSING,
            imageUrls: [],
            metadata: ['astria_prompt_id' => $promptId],
        );
    }

    /**
     * Estimate training cost — flat rate per model from config.
     */
    public function estimateTrainingCost(int $imageCount): Money
    {
        return new Money($this->config->trainingCostCents());
    }

    /**
     * Estimate generation cost — flat rate per prompt from config.
     * Astria charges per prompt (not per image), covering up to 8 images.
     */
    public function estimateGenerationCost(int $numImages): Money
    {
        return new Money($this->config->generationCostCents());
    }

    /**
     * Map Astria tune timestamps to our TrainingStatus enum.
     */
    private function mapTrainingStatus(array $tune): TrainingStatus
    {
        if (! empty($tune['trained_at'])) {
            return TrainingStatus::TRAINED;
        }

        if (! empty($tune['started_training_at'])) {
            return TrainingStatus::TRAINING;
        }

        return TrainingStatus::PENDING;
    }
}
