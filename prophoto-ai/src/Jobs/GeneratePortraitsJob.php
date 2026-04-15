<?php

namespace ProPhoto\AI\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use ProPhoto\AI\Models\AiGeneration;
use ProPhoto\AI\Models\AiGenerationRequest;
use ProPhoto\AI\Registry\AiProviderRegistry;
use ProPhoto\Contracts\DTOs\AI\GenerationRequest;
use Psr\Log\LoggerInterface;

/**
 * Submit a generation request to the AI provider.
 *
 * Resolves the provider, calls submitGeneration() with the trained model,
 * stores the external request ID, then dispatches PollGenerationStatusJob
 * with a 15-second delay.
 *
 * Queue: ai
 * Max attempts: 3
 * Backoff: 30s, 60s, 120s
 */
class GeneratePortraitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly int $requestId,
    ) {}

    public function handle(AiProviderRegistry $registry, LoggerInterface $logger): void
    {
        $request = AiGenerationRequest::find($this->requestId);

        if (! $request) {
            $logger->warning('GeneratePortraitsJob: request not found — skipping', [
                'request_id' => $this->requestId,
            ]);
            return;
        }

        $generation = $request->aiGeneration;

        if (! $generation) {
            $logger->warning('GeneratePortraitsJob: generation not found — skipping', [
                'request_id' => $this->requestId,
            ]);
            return;
        }

        $logger->info('GeneratePortraitsJob: submitting generation', [
            'request_id' => $this->requestId,
            'generation_id' => $generation->id,
            'provider_key' => $request->provider_key,
        ]);

        $provider = $registry->resolve($request->provider_key);

        // Build the prompt — use custom prompt or provider's default negative prompt
        $prompt = $request->custom_prompt ?? $this->buildDefaultPrompt($generation);

        $generationRequest = new GenerationRequest(
            externalModelId: $generation->external_model_id,
            prompt: $prompt,
            numImages: 8,
            metadata: [
                'request_id' => $request->id,
                'generation_id' => $generation->id,
            ],
        );

        $response = $provider->submitGeneration($generationRequest);

        // Store external request ID and update status
        $request->update([
            'external_request_id' => $response->externalRequestId,
            'status' => AiGenerationRequest::STATUS_PROCESSING,
            'provider_metadata' => $response->metadata,
        ]);

        // Dispatch polling job with 15s initial delay
        PollGenerationStatusJob::dispatch($this->requestId, now())
            ->onQueue(config('ai.queue.name', 'ai'))
            ->delay(now()->addSeconds(15));

        $logger->info('GeneratePortraitsJob: generation submitted, polling scheduled', [
            'request_id' => $this->requestId,
            'external_request_id' => $response->externalRequestId,
        ]);
    }

    /**
     * Build a default prompt for generation.
     * Uses the gallery subject name for identity-based generation.
     */
    private function buildDefaultPrompt(AiGeneration $generation): string
    {
        $gallery = $generation->gallery;
        $subjectName = $gallery ? $gallery->subject_name : 'the subject';

        return "A professional portrait photo of {$subjectName}, high quality, natural lighting, sharp focus";
    }

    public function failed(\Throwable $exception): void
    {
        $request = AiGenerationRequest::find($this->requestId);

        if ($request) {
            $request->update([
                'status' => AiGenerationRequest::STATUS_FAILED,
                'error_message' => 'Generation submission failed: ' . $exception->getMessage(),
            ]);
        }
    }
}
