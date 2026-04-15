<?php

namespace ProPhoto\AI\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProPhoto\AI\Models\AiGenerationRequest;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\AI\Services\AiOrchestrationService;
use ProPhoto\Contracts\DTOs\AI\GenerationStatusResponse;
use ProPhoto\Contracts\Enums\AI\GenerationStatus;
use Psr\Log\LoggerInterface;

/**
 * Poll the AI provider for generation completion.
 *
 * Uses exponential backoff: 15s → 30s → 60s → 60s...
 * Max poll duration: 2 hours (configurable).
 * After max duration: marks generation as failed with timeout error.
 *
 * On completion, calls handleGenerationComplete() which stores
 * each portrait in ImageKit and creates AiGeneratedPortrait records.
 *
 * Queue: ai
 */
class PollGenerationStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    /**
     * Backoff schedule in seconds.
     * Generation is faster than training: 15s → 30s → 60s → 60s...
     */
    private const BACKOFF_SCHEDULE = [15, 30, 60];

    public function __construct(
        public readonly int $requestId,
        public readonly Carbon $startedAt,
        public readonly int $pollCount = 0,
    ) {}

    public function handle(
        AiProviderRegistry $registry,
        AiOrchestrationService $orchestration,
        LoggerInterface $logger,
    ): void {
        $request = AiGenerationRequest::find($this->requestId);

        if (! $request) {
            $logger->warning('PollGenerationStatusJob: request not found — skipping', [
                'request_id' => $this->requestId,
            ]);
            return;
        }

        // Check if we've exceeded max poll duration
        $maxHours = config('ai.queue.max_generation_poll_hours', 2);
        if ($this->startedAt->diffInHours(now()) >= $maxHours) {
            $logger->error('PollGenerationStatusJob: max poll duration exceeded', [
                'request_id' => $this->requestId,
                'hours_elapsed' => $this->startedAt->diffInHours(now()),
            ]);

            $request->update([
                'status' => AiGenerationRequest::STATUS_FAILED,
                'error_message' => "Generation timed out after {$maxHours} hours.",
            ]);
            return;
        }

        $provider = $registry->resolve($request->provider_key);
        $status = $provider->getGenerationStatus($request->external_request_id);

        $logger->info('PollGenerationStatusJob: status check', [
            'request_id' => $this->requestId,
            'status' => $status->status->value,
            'image_count' => count($status->imageUrls),
            'poll_count' => $this->pollCount,
        ]);

        // Completed — store portraits and update records
        if ($status->status === GenerationStatus::COMPLETED) {
            $orchestration->handleGenerationComplete($request, $status);
            return;
        }

        // Failed — update status and stop polling
        if ($status->status === GenerationStatus::FAILED) {
            $request->update([
                'status' => AiGenerationRequest::STATUS_FAILED,
                'error_message' => $status->errorMessage ?? 'Generation failed at provider.',
                'provider_metadata' => $status->metadata,
            ]);
            return;
        }

        // Still processing — re-dispatch with backoff
        $delay = $this->getBackoffDelay();

        PollGenerationStatusJob::dispatch(
            $this->requestId,
            $this->startedAt,
            $this->pollCount + 1,
        )->onQueue(config('ai.queue.name', 'ai'))
         ->delay(now()->addSeconds($delay));

        $logger->debug('PollGenerationStatusJob: re-dispatching', [
            'request_id' => $this->requestId,
            'next_delay_seconds' => $delay,
            'poll_count' => $this->pollCount + 1,
        ]);
    }

    /**
     * Calculate the backoff delay for the current poll iteration.
     * Schedule: 15s → 30s → 60s → 60s...
     */
    private function getBackoffDelay(): int
    {
        $index = min($this->pollCount, count(self::BACKOFF_SCHEDULE) - 1);

        return self::BACKOFF_SCHEDULE[$index];
    }
}
