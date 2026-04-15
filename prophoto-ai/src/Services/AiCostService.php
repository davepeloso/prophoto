<?php

namespace ProPhoto\AI\Services;

use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Models\AiGenerationRequest;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\Contracts\DTOs\AI\Money;
use ProPhoto\Gallery\Models\Gallery;

class AiCostService
{
    public function __construct(
        private readonly AiProviderRegistry $registry,
    ) {}

    /**
     * Estimate training cost before committing.
     * Delegates to the provider's cost estimator.
     */
    public function estimateTrainingCost(string $providerKey, int $imageCount): Money
    {
        $provider = $this->registry->resolve($providerKey);

        return $provider->estimateTrainingCost($imageCount);
    }

    /**
     * Estimate generation cost before committing.
     * Delegates to the provider's cost estimator.
     */
    public function estimateGenerationCost(string $providerKey, int $numImages): Money
    {
        $provider = $this->registry->resolve($providerKey);

        return $provider->estimateGenerationCost($numImages);
    }

    /**
     * Total AI spend for a specific gallery.
     * Aggregates training cost + all generation request costs from the database.
     */
    public function totalSpentForGallery(Gallery $gallery): Money
    {
        $generation = $gallery->aiGeneration;

        if (! $generation) {
            return Money::zero();
        }

        // Training cost (stored as dollars in fine_tune_cost decimal column)
        $trainingCents = (int) round(($generation->fine_tune_cost ?? 0) * 100);

        // Generation costs (stored as dollars in generation_cost decimal column)
        $generationCents = (int) round(
            $generation->requests()->sum('generation_cost') * 100
        );

        return new Money($trainingCents + $generationCents);
    }

    /**
     * Total AI spend across all galleries for a studio.
     * Aggregates training + generation costs studio-wide.
     */
    public function totalSpentForStudio(int $studioId): Money
    {
        // Training costs across all galleries for this studio
        $trainingCents = (int) round(
            AiGeneration::whereHas('gallery', fn ($q) => $q->where('studio_id', $studioId))
                ->sum('fine_tune_cost') * 100
        );

        // Generation costs across all requests for this studio's galleries
        $generationCents = (int) round(
            AiGenerationRequest::whereHas('aiGeneration.gallery', fn ($q) => $q->where('studio_id', $studioId))
                ->sum('generation_cost') * 100
        );

        return new Money($trainingCents + $generationCents);
    }
}
