<?php

namespace ProPhoto\Contracts\Contracts\AI;

use ProPhoto\Contracts\DTOs\AI\AiProviderCapabilities;
use ProPhoto\Contracts\DTOs\AI\GenerationRequest;
use ProPhoto\Contracts\DTOs\AI\GenerationResponse;
use ProPhoto\Contracts\DTOs\AI\GenerationStatusResponse;
use ProPhoto\Contracts\DTOs\AI\Money;
use ProPhoto\Contracts\DTOs\AI\TrainingRequest;
use ProPhoto\Contracts\DTOs\AI\TrainingResponse;
use ProPhoto\Contracts\DTOs\AI\TrainingStatusResponse;
use ProPhoto\Contracts\Enums\AI\ProviderRole;

interface AiProviderContract
{
    /**
     * Unique provider slug (e.g., 'astria', 'fal', 'magnific', 'claid').
     */
    public function providerKey(): string;

    /**
     * Human-readable name for UI display.
     */
    public function displayName(): string;

    /**
     * The primary role this provider fills in the pipeline.
     */
    public function providerRole(): ProviderRole;

    /**
     * Declared capabilities: training support, generation limits, output formats.
     */
    public function capabilities(): AiProviderCapabilities;

    /**
     * Verify API key and connectivity.
     */
    public function validateConfiguration(): bool;

    /**
     * Submit images for model training (fine-tuning).
     * Providers that don't support training should return a TrainingResponse
     * with externalModelId = 'none' and cost = Money::zero().
     */
    public function submitTraining(TrainingRequest $request): TrainingResponse;

    /**
     * Poll training status. Maps provider-specific state to TrainingStatus enum.
     */
    public function getTrainingStatus(string $externalModelId): TrainingStatusResponse;

    /**
     * Submit a generation request against a trained model.
     */
    public function submitGeneration(GenerationRequest $request): GenerationResponse;

    /**
     * Poll generation status. Complete when image URLs are available.
     */
    public function getGenerationStatus(string $externalRequestId): GenerationStatusResponse;

    /**
     * Estimate training cost before committing.
     */
    public function estimateTrainingCost(int $imageCount): Money;

    /**
     * Estimate generation cost for a given number of images.
     */
    public function estimateGenerationCost(int $numImages): Money;
}
