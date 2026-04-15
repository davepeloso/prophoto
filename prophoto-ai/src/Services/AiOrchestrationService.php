<?php

namespace ProPhoto\AI\Services;

use Illuminate\Support\Collection;
use ProPhoto\AI\Events\AiGenerationCompleted;
use ProPhoto\AI\Events\AiModelTrained;
use ProPhoto\AI\Jobs\GeneratePortraitsJob;
use ProPhoto\AI\Jobs\TrainModelJob;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Models\AiGeneratedPortrait;
use ProPhoto\AI\Models\AiGenerationRequest;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\Contracts\Contracts\AI\AiStorageContract;
use ProPhoto\Contracts\DTOs\AI\GenerationStatusResponse;
use ProPhoto\Contracts\DTOs\AI\TrainingStatusResponse;
use ProPhoto\Contracts\Enums\AI\TrainingStatus;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Services\GalleryActivityLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class AiOrchestrationService
{
    public function __construct(
        private readonly AiProviderRegistry $registry,
        private readonly AiStorageContract $storage,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {}

    /**
     * Initiate model training for a gallery.
     *
     * Validates gallery state and image count, creates the AiGeneration record,
     * resolves image URLs, and dispatches the training job.
     *
     * @param Gallery    $gallery  The gallery to train on
     * @param Collection $imageUrls Collection of publicly accessible image URLs
     * @param int|null   $userId   The user initiating training
     *
     * @throws \InvalidArgumentException If gallery is not AI-enabled or image count is invalid
     */
    public function initiateTraining(Gallery $gallery, Collection $imageUrls, ?int $userId = null): AiGeneration
    {
        // Validate gallery is AI-enabled
        if (! $gallery->ai_enabled) {
            throw new \InvalidArgumentException('Gallery does not have AI generation enabled.');
        }

        // Validate no existing active training
        $existing = $gallery->aiGeneration;
        if ($existing && in_array($existing->model_status, [AiGeneration::STATUS_PENDING, AiGeneration::STATUS_TRAINING])) {
            throw new \InvalidArgumentException('Gallery already has an active training in progress.');
        }

        // Resolve the default provider and validate image count
        $providerKey = $this->registry->default()->providerKey();
        $descriptor = $this->registry->descriptor($providerKey);
        $capabilities = $descriptor->capabilities;

        $imageCount = $imageUrls->count();

        if ($imageCount < $capabilities->minTrainingImages) {
            throw new \InvalidArgumentException(
                "Minimum {$capabilities->minTrainingImages} images required for training, got {$imageCount}."
            );
        }

        if ($imageCount > $capabilities->maxTrainingImages) {
            throw new \InvalidArgumentException(
                "Maximum {$capabilities->maxTrainingImages} images allowed for training, got {$imageCount}."
            );
        }

        // Estimate cost
        $provider = $this->registry->resolve($providerKey);
        $cost = $provider->estimateTrainingCost($imageCount);

        // Create AiGeneration record
        $generation = AiGeneration::create([
            'gallery_id' => $gallery->id,
            'subject_user_id' => $userId,
            'provider_key' => $providerKey,
            'training_image_count' => $imageCount,
            'model_status' => AiGeneration::STATUS_PENDING,
            'fine_tune_cost' => $cost->toDollars(),
        ]);

        // Update gallery training status
        $gallery->update(['ai_training_status' => Gallery::AI_STATUS_TRAINING]);

        // Dispatch training job on the AI queue
        TrainModelJob::dispatch($generation->id, $imageUrls->toArray())
            ->onQueue(config('ai.queue.name', 'ai'));

        // Log to activity ledger
        GalleryActivityLogger::log(
            gallery: $gallery,
            actionType: 'ai_training_started',
            actorType: 'studio_user',
            metadata: [
                'generation_id' => $generation->id,
                'provider_key' => $providerKey,
                'image_count' => $imageCount,
                'estimated_cost' => $cost->toDollars(),
            ],
        );

        $this->logger->info('AI training initiated', [
            'gallery_id' => $gallery->id,
            'generation_id' => $generation->id,
            'provider_key' => $providerKey,
            'image_count' => $imageCount,
        ]);

        return $generation;
    }

    /**
     * Initiate portrait generation against a trained model.
     *
     * Validates the model is trained and quota is available, creates the
     * AiGenerationRequest record, and dispatches the generation job.
     *
     * @param AiGeneration $generation The trained AI generation
     * @param string|null  $prompt     Custom prompt (null uses provider default)
     * @param int          $numImages  Number of images to generate (1-8)
     * @param int|null     $userId     The user initiating generation
     *
     * @throws \InvalidArgumentException If model not trained or quota exceeded
     */
    public function initiateGeneration(
        AiGeneration $generation,
        ?string $prompt = null,
        int $numImages = 8,
        ?int $userId = null,
    ): AiGenerationRequest {
        // Validate model is trained
        if (! $generation->isReady()) {
            throw new \InvalidArgumentException('AI model is not trained yet. Current status: ' . $generation->model_status);
        }

        // Validate model hasn't expired
        if ($generation->isExpired()) {
            throw new \InvalidArgumentException('AI model has expired and can no longer generate portraits.');
        }

        // Validate generation quota
        $remaining = $generation->remaining_generations;
        if ($remaining <= 0) {
            throw new \InvalidArgumentException('Generation quota exhausted. No more generations available for this model.');
        }

        // Resolve provider and estimate cost
        $provider = $this->registry->resolve($generation->provider_key);
        $cost = $provider->estimateGenerationCost($numImages);

        // Get next request number
        $requestNumber = $generation->requests()->count() + 1;

        // Create AiGenerationRequest record
        $request = AiGenerationRequest::create([
            'ai_generation_id' => $generation->id,
            'provider_key' => $generation->provider_key,
            'request_number' => $requestNumber,
            'custom_prompt' => $prompt,
            'used_default_prompt' => $prompt === null,
            'generated_portrait_count' => 0,
            'generation_cost' => $cost->toDollars(),
            'status' => AiGenerationRequest::STATUS_PENDING,
            'requested_by_user_id' => $userId,
        ]);

        // Dispatch generation job on the AI queue
        GeneratePortraitsJob::dispatch($request->id)
            ->onQueue(config('ai.queue.name', 'ai'));

        // Log to activity ledger
        $gallery = $generation->gallery;
        if ($gallery) {
            GalleryActivityLogger::log(
                gallery: $gallery,
                actionType: 'ai_generation_started',
                actorType: 'studio_user',
                metadata: [
                    'generation_id' => $generation->id,
                    'request_id' => $request->id,
                    'request_number' => $requestNumber,
                    'num_images' => $numImages,
                    'estimated_cost' => $cost->toDollars(),
                ],
            );
        }

        $this->logger->info('AI generation initiated', [
            'generation_id' => $generation->id,
            'request_id' => $request->id,
            'provider_key' => $generation->provider_key,
            'num_images' => $numImages,
        ]);

        return $request;
    }

    /**
     * Handle training completion (called by PollTrainingStatusJob).
     *
     * Updates the AiGeneration and Gallery records, then dispatches
     * the AiModelTrained event.
     */
    public function handleTrainingComplete(AiGeneration $generation, TrainingStatusResponse $status): void
    {
        $isTrained = $status->status === TrainingStatus::TRAINED;

        $generation->update([
            'model_status' => $isTrained ? AiGeneration::STATUS_TRAINED : AiGeneration::STATUS_FAILED,
            'external_model_id' => $status->externalModelId,
            'model_created_at' => $status->completedAt,
            'model_expires_at' => $status->expiresAt,
            'error_message' => $status->errorMessage,
            'provider_metadata' => $status->metadata,
        ]);

        // Update gallery training status
        $gallery = $generation->gallery;
        if ($gallery) {
            $gallery->update([
                'ai_training_status' => $isTrained ? Gallery::AI_STATUS_TRAINED : Gallery::AI_STATUS_READY,
            ]);

            GalleryActivityLogger::log(
                gallery: $gallery,
                actionType: $isTrained ? 'ai_training_completed' : 'ai_training_failed',
                actorType: 'system',
                metadata: [
                    'generation_id' => $generation->id,
                    'provider_key' => $generation->provider_key,
                    'model_status' => $generation->model_status,
                    'error_message' => $status->errorMessage,
                ],
            );
        }

        // Dispatch event
        AiModelTrained::dispatch(
            galleryId: $generation->gallery_id,
            generationId: $generation->id,
            providerKey: $generation->provider_key,
            modelStatus: $generation->model_status,
            trainedAt: $status->completedAt?->toIso8601String(),
        );

        $this->logger->info('AI training completed', [
            'generation_id' => $generation->id,
            'status' => $generation->model_status,
        ]);
    }

    /**
     * Handle generation completion (called by PollGenerationStatusJob).
     *
     * Stores each generated portrait in ImageKit, creates AiGeneratedPortrait
     * records, updates the request, and dispatches AiGenerationCompleted.
     */
    public function handleGenerationComplete(AiGenerationRequest $request, GenerationStatusResponse $status): void
    {
        $generation = $request->aiGeneration;
        $portraitCount = 0;

        foreach ($status->imageUrls as $index => $providerUrl) {
            try {
                // Store in ImageKit — it fetches from the provider URL
                $storageResult = $this->storage->upload(
                    sourceUrl: $providerUrl,
                    fileName: "portrait_{$request->id}_{$index}.png",
                    folder: "/ai-portraits/gallery-{$generation->gallery_id}/request-{$request->id}",
                    tags: ['ai-generated', "gallery-{$generation->gallery_id}", "request-{$request->id}"],
                );

                // Create portrait record with ImageKit URLs
                AiGeneratedPortrait::create([
                    'ai_generation_request_id' => $request->id,
                    'storage_driver' => 'imagekit',
                    'imagekit_file_id' => $storageResult->fileId,
                    'imagekit_url' => $storageResult->url,
                    'imagekit_thumbnail_url' => $storageResult->thumbnailUrl,
                    'original_provider_url' => $providerUrl,
                    'file_size' => $storageResult->fileSize,
                    'sort_order' => $index,
                    'created_at' => now(),
                ]);

                $portraitCount++;
            } catch (\Throwable $e) {
                $this->logger->error('Failed to store AI portrait', [
                    'request_id' => $request->id,
                    'index' => $index,
                    'provider_url' => $providerUrl,
                    'error' => $e->getMessage(),
                ]);
                // Continue with remaining portraits — don't fail the entire batch
            }
        }

        // Update request status and portrait count
        $request->update([
            'status' => AiGenerationRequest::STATUS_COMPLETED,
            'generated_portrait_count' => $portraitCount,
            'provider_metadata' => $status->metadata,
        ]);

        // Update gallery counts
        $gallery = $generation->gallery;
        if ($gallery) {
            $gallery->recordActivity();

            GalleryActivityLogger::log(
                gallery: $gallery,
                actionType: 'ai_generation_completed',
                actorType: 'system',
                metadata: [
                    'generation_id' => $generation->id,
                    'request_id' => $request->id,
                    'portrait_count' => $portraitCount,
                    'provider_key' => $request->provider_key,
                ],
            );
        }

        // Dispatch event
        AiGenerationCompleted::dispatch(
            galleryId: $generation->gallery_id,
            generationId: $generation->id,
            requestId: $request->id,
            portraitCount: $portraitCount,
            providerKey: $request->provider_key,
        );

        $this->logger->info('AI generation completed', [
            'request_id' => $request->id,
            'portrait_count' => $portraitCount,
        ]);
    }
}
