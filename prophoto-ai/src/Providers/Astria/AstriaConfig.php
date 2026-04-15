<?php

namespace ProPhoto\AI\Providers\Astria;

use InvalidArgumentException;

/**
 * Typed configuration accessor for the Astria provider.
 *
 * Reads from the `ai.providers.astria` config namespace.
 * Validates API key presence and format on construction.
 */
readonly class AstriaConfig
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl = 'https://api.astria.ai',
        private int $trainingCostCents = 150,
        private int $generationCostCents = 23,
        private int $maxGenerationsPerModel = 5,
        private int $defaultPromptImageCount = 8,
        private int $modelExpiryDays = 30,
        private string $preset = 'flux-lora-portrait',
        private string $modelType = 'lora',
        private bool $faceCrop = true,
        private string $defaultNegativePrompt = 'double torso, totem pole, old, wrinkles, mole, blemish, (oversmoothed, 3d render), scar, sad, severe, 2d, sketch, painting, digital art, drawing, disfigured, elongated body, text, cropped, out of frame',
    ) {}

    public function apiKey(): string
    {
        return $this->apiKey;
    }

    public function baseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    public function trainingCostCents(): int
    {
        return $this->trainingCostCents;
    }

    public function generationCostCents(): int
    {
        return $this->generationCostCents;
    }

    public function maxGenerationsPerModel(): int
    {
        return $this->maxGenerationsPerModel;
    }

    public function defaultPromptImageCount(): int
    {
        return $this->defaultPromptImageCount;
    }

    public function modelExpiryDays(): int
    {
        return $this->modelExpiryDays;
    }

    public function preset(): string
    {
        return $this->preset;
    }

    public function modelType(): string
    {
        return $this->modelType;
    }

    public function faceCrop(): bool
    {
        return $this->faceCrop;
    }

    public function defaultNegativePrompt(): string
    {
        return $this->defaultNegativePrompt;
    }

    /**
     * Validate that the config has a valid API key.
     */
    public function validate(): bool
    {
        if (empty($this->apiKey)) {
            return false;
        }

        if (! str_starts_with($this->apiKey, 'sd_')) {
            return false;
        }

        return true;
    }

    /**
     * Build a callback URL for Astria webhooks.
     */
    public function callbackUrl(string $type, string|int $localId): string
    {
        $appUrl = rtrim(config('app.url', ''), '/');

        return "{$appUrl}/api/webhooks/astria?type={$type}&id={$localId}";
    }

    /**
     * Create an AstriaConfig from the Laravel config array.
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            apiKey: $config['api_key'] ?? '',
            baseUrl: $config['api_base_url'] ?? 'https://api.astria.ai',
            trainingCostCents: $config['training_cost_cents'] ?? 150,
            generationCostCents: $config['generation_cost_cents'] ?? 23,
            maxGenerationsPerModel: $config['max_generations_per_model'] ?? 5,
            defaultPromptImageCount: $config['default_images_per_prompt'] ?? 8,
            modelExpiryDays: $config['model_expiry_days'] ?? 30,
            preset: $config['preset'] ?? 'flux-lora-portrait',
            modelType: $config['model_type'] ?? 'lora',
            faceCrop: $config['face_crop'] ?? true,
            defaultNegativePrompt: $config['default_negative_prompt'] ?? 'double torso, totem pole, old, wrinkles, mole, blemish, (oversmoothed, 3d render), scar, sad, severe, 2d, sketch, painting, digital art, drawing, disfigured, elongated body, text, cropped, out of frame',
        );
    }
}
