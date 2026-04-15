<?php

namespace ProPhoto\AI\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiOrchestrationService;
use ProPhoto\Contracts\DTOs\AI\TrainingStatusResponse;
use ProPhoto\Contracts\Enums\AI\TrainingStatus;
use Psr\Log\LoggerInterface;

/**
 * Poll the AI provider for training completion.
 *
 * Uses exponential backoff: 30s → 60s → 120s → 120s...
 * Max poll duration: 24 hours (configurable).
 * After max duration: marks training as failed with timeout error.
 *
 * Queue: ai
 */
class PollTrainingStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 60;

    /**
     * Backoff schedule in seconds.
     * Index 0 = first poll, 1 = second, 2+ = cap at 120s.
     */
    private const BACKOFF_SCHEDULE = [30, 60, 120];

    public function __construct(
        public readonly int $generationId,
        public readonly Carbon $startedAt,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(
        AiProviderRegistry $registry,
        AiOrchestrationService $orchestration,
        LoggerInterface $logger,
    ): void {
        $generation = AiGeneration::find($this->generationId);

        if (! $generation) {
            $logger->warning('PollTrainingStatusJob: generation not found — skipping', [
                'generation_id' => $this->generationId,
            ]);
            return;
        }

        // Check if we've exceeded max poll duration
        $maxHours = config('ai.queue.max_training_poll_hours', 24);
        if ($this->startedAt->diffInHours(now()) >= $maxHours) {
            $logger->error('PollTrainingStatusJob: max poll duration exceeded', [
                'generation_id' => $this->generationId,
                'hours_elapsed' => $this->startedAt->diffInHours(now()),
            ]);

            $orchestration->handleTrainingComplete($generation, new TrainingStatusResponse(
                status: TrainingStatus::FAILED,
                externalModelId: $generation->external_model_id ?? '',
                errorMessage: "Training timed out after {$maxHours} hours.",
            ));
            return;
        }

        $provider = $registry->resolve($generation->provider_key);
        $status = $provider->getTrainingStatus($generation->external_model_id);

        $logger->info('PollTrainingStatusJob: status check', [
            'generation_id' => $this->generationId,
            'status' => $status->status->value,
            'poll_count' => $this->pollCount,
        ]);

        // Terminal states: trained or failed
        if (in_array($status->status, [TrainingStatus::TRAINED, TrainingStatus::FAILED])) {
            $orchestration->handleTrainingComplete($generation, $status);
            return;
        }

        // Still training — re-dispatch with backoff
        $delay = $this->getBackoffDelay();

        PollTrainingStatusJob::dispatch(
            $this->generationId,
            $this->startedAt,
            $this->pollCount + 1,
        )->onQueue(config('ai.queue.name', 'ai'))
         ->delay(now()->addSeconds($delay));

        $logger->debug('PollTrainingStatusJob: re-dispatching', [
            'generation_id' => $this->generationId,
            'next_delay_seconds' => $delay,
            'poll_count' => $this->pollCount + 1,
        ]);
    }

    /**
     * Calculate the backoff delay for the current poll iteration.
     * Schedule: 30s → 60s → 120s → 120s...
     */
    private function getBackoffDelay(): int
    {
        $index = min($this->pollCount, count(self::BACKOFF_SCHEDULE) - 1);

        return self::BACKOFF_SCHEDULE[$index];
    }
}
