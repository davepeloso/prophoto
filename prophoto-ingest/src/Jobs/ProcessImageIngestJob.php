<?php

namespace ProPhoto\Ingest\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use ProPhoto\Ingest\Models\ProxyImage;
use ProPhoto\Ingest\Services\IngestProcessor;

/**
 * ProcessImageIngestJob - Handles final image processing and storage
 *
 * This job processes proxy images into final storage with:
 * - Retry logic with exponential backoff
 * - Graceful failure handling (marks proxy with error, doesn't crash worker)
 * - Performance logging and metrics
 */
class ProcessImageIngestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times to retry the job
     */
    public int $tries = 3;

    /**
     * Maximum execution time in seconds
     */
    public int $timeout = 300; // 5 minutes per image

    /**
     * Backoff intervals in seconds (exponential)
     */
    public array $backoff = [10, 30, 60];

    /**
     * Delete the job if its models no longer exist
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(
        public ProxyImage $proxy,
        public int $sequence,
        public ?array $association = null
    ) {}

    /**
     * Execute the job
     */
    public function handle(IngestProcessor $processor): void
    {
        $startTime = microtime(true);

        Log::info('Starting image ingest job', [
            'proxy_uuid' => $this->proxy->uuid,
            'sequence' => $this->sequence,
            'attempt' => $this->attempts(),
            'extraction_method' => $this->proxy->extraction_method ?? 'unknown',
            'has_metadata_error' => !empty($this->proxy->metadata_error),
        ]);

        try {
            // Check if proxy has metadata extraction errors
            if ($this->proxy->metadata_error) {
                Log::warning('Processing proxy with metadata extraction error', [
                    'proxy_uuid' => $this->proxy->uuid,
                    'error' => $this->proxy->metadata_error,
                ]);
            }

            $image = $processor->process(
                $this->proxy,
                $this->sequence,
                $this->association
            );

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::info('Image ingest job completed successfully', [
                'proxy_uuid' => $this->proxy->uuid,
                'image_id' => $image->id,
                'duration_ms' => $duration,
                'file_path' => $image->file_path,
            ]);

        } catch (\Exception $e) {
            $duration = round((microtime(true) - $startTime) * 1000, 2);

            Log::error('Image ingest job failed', [
                'proxy_uuid' => $this->proxy->uuid,
                'attempt' => $this->attempts(),
                'max_attempts' => $this->tries,
                'duration_ms' => $duration,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            // Mark proxy with processing error if this is the final attempt
            if ($this->attempts() >= $this->tries) {
                $this->markProxyAsFailedProcessing($e);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Image ingest job permanently failed', [
            'proxy_uuid' => $this->proxy->uuid,
            'sequence' => $this->sequence,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark the proxy with error but don't delete it so user can retry
        $this->markProxyAsFailedProcessing($exception);

        report($exception);
    }

    /**
     * Mark the proxy image as having failed processing
     */
    protected function markProxyAsFailedProcessing(\Throwable $exception): void
    {
        try {
            $this->proxy->update([
                'metadata_error' => 'Processing failed: ' . substr($exception->getMessage(), 0, 200),
            ]);

            Log::info('Marked proxy as failed processing', [
                'proxy_uuid' => $this->proxy->uuid,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update proxy error status', [
                'proxy_uuid' => $this->proxy->uuid,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job
     */
    public function tags(): array
    {
        return [
            'ingest',
            'proxy:' . $this->proxy->uuid,
            'user:' . $this->proxy->user_id,
        ];
    }

    /**
     * Determine the time at which the job should timeout
     */
    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }
}
