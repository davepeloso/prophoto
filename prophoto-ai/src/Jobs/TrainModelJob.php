<?php

namespace ProPhoto\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\Contracts\DTOs\AI\TrainingRequest;
use Psr\Log\LoggerInterface;

/**
 * Submit training images to the AI provider.
 *
 * Resolves the provider from the registry, calls submitTraining(),
 * stores the external model ID, then dispatches PollTrainingStatusJob
 * with a 30-second delay.
 *
 * Queue: ai
 * Max attempts: 3
 * Backoff: 30s, 60s, 120s
 */
class TrainModelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $generationId,
        public readonly array $imageUrls,
    ) {}

    public function handle(AiProviderRegistry $registry, LoggerInterface $logger): void
    {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation) {
            $logger->warning('TrainModelJob: generation not found — skipping', [
                'generation_id' => $this->generationId,
            ]);
            return;
        }

        $logger->info('TrainModelJob: submitting training', [
            'generation_id' => $this->generationId,
            'provider_key' => $generation->provider_key,
            'image_count' => count($this->imageUrls),
        ]);

        $provider = $registry->resolve($generation->provider_key);

        $gallery = $generation->gallery;
        $subjectName = $gallery ? $gallery->subject_name : 'subject';

        $trainingRequest = new TrainingRequest(
            providerKey: $generation->provider_key,
            imageUrls: $this->imageUrls,
            subjectName: $subjectName,
            metadata: ['generation_id' => $generation->id],
        );

        $response = $provider->submitTraining($trainingRequest);

        // Store external model ID and update status
        $generation->update([
            'external_model_id' => $response->externalModelId,
            'model_status' => AiGeneration::STATUS_TRAINING,
            'provider_metadata' => $response->metadata,
        ]);

        // Dispatch polling job with 30s initial delay
        PollTrainingStatusJob::dispatch($this->generationId, now())
            ->onQueue(config('ai.queue.name', 'ai'))
            ->delay(now()->addSeconds(30));

        $logger->info('TrainModelJob: training submitted, polling scheduled', [
            'generation_id' => $this->generationId,
            'external_model_id' => $response->externalModelId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $generation = AiGeneration::find($this->generationId);

        if ($generation) {
            $generation->update([
                'model_status' => AiGeneration::STATUS_FAILED,
                'error_message' => 'Training submission failed: ' . $exception->getMessage(),
            ]);

            $gallery = $generation->gallery;
            if ($gallery) {
                $gallery->update(['ai_training_status' => 'ready']);
            }
        }
    }
}
