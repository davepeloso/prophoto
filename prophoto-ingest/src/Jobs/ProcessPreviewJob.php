<?php

namespace ProPhoto\Ingest\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ProPhoto\Ingest\Models\ProxyImage;
use ProPhoto\Ingest\Services\MetadataExtractor;
use ProPhoto\Ingest\Events\TraceSessionStarted;
use ProPhoto\Ingest\Events\TraceSessionEnded;
use ProPhoto\Ingest\Events\ThumbnailGenerationCompleted;

class ProcessPreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $uuid
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MetadataExtractor $extractor): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();

        if (!$proxy) {
            Log::warning('ProcessPreviewJob: ProxyImage not found', ['uuid' => $this->uuid]);
            return;
        }

        // Skip if already processed
        if ($proxy->preview_status === 'ready') {
            Log::debug('ProcessPreviewJob: Preview already ready', ['uuid' => $this->uuid]);
            return;
        }

        // Start debug trace session if debug is enabled
        $sessionId = null;
        if (config('debug.enabled', false)) {
            $sessionId = Str::uuid()->toString();
            TraceSessionStarted::dispatch($this->uuid, $sessionId, $proxy->filename ?? 'unknown');
        }

        // Mark as processing
        $proxy->update([
            'preview_status' => 'processing',
            'preview_attempted_at' => now(),
        ]);

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $fullPath = Storage::disk($tempDisk)->path($proxy->temp_path);

        if (!file_exists($fullPath)) {
            $this->markFailed($proxy, 'Source file not found');
            if ($sessionId) {
                TraceSessionEnded::dispatch($this->uuid, $sessionId, false, 'Source file not found');
            }
            return;
        }

        try {
            $startTime = microtime(true);

            // Step 1: Generate high-quality preview (pass sessionId for tracing)
            $previewPath = $extractor->generatePreview($fullPath, $this->uuid, $sessionId);

            // Step 2: If we got a preview, generate a proper thumbnail from it
            $thumbnailPath = $proxy->thumbnail_path; // Keep existing tiny thumbnail as fallback
            $thumbnailUpgraded = false;

            if ($previewPath) {
                $thumbnailStartTime = microtime(true);
                $newThumbnailPath = $extractor->generateThumbnailFromPreview($previewPath, $this->uuid);
                $thumbnailDuration = round((microtime(true) - $thumbnailStartTime) * 1000, 2);

                if ($newThumbnailPath) {
                    $thumbnailPath = $newThumbnailPath;
                    $thumbnailUpgraded = true;

                    // Dispatch thumbnail generation trace event
                    if ($sessionId) {
                        ThumbnailGenerationCompleted::dispatch(
                            $this->uuid,
                            $sessionId,
                            'from_preview',
                            true,
                            null,
                            ['duration_ms' => $thumbnailDuration]
                        );
                    }
                }
            }

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Verify files exist before marking ready (prevents race condition)
            $filesVerified = $this->verifyFilesExist($previewPath, $thumbnailPath, $tempDisk);

            if (!$filesVerified) {
                Log::warning('ProcessPreviewJob: Files written but not yet visible', [
                    'uuid' => $this->uuid,
                    'preview_path' => $previewPath,
                    'thumbnail_path' => $thumbnailPath,
                ]);
                // Retry after a short delay - files should be visible soon
                $this->release(2); // Release back to queue, retry in 2 seconds
                return;
            }

            // Update record - only now that files are verified to exist
            $proxy->update([
                'preview_path' => $previewPath,
                'thumbnail_path' => $thumbnailPath,
                'preview_status' => 'ready',
                'preview_error' => null,
            ]);

            Log::info('ProcessPreviewJob: Preview generated', [
                'uuid' => $this->uuid,
                'duration_ms' => $duration,
                'has_preview' => $previewPath !== null,
                'thumbnail_upgraded' => $thumbnailUpgraded,
            ]);

            // End trace session on success
            if ($sessionId) {
                TraceSessionEnded::dispatch($this->uuid, $sessionId, true);
            }

            // Optional: Broadcast event for real-time UI update
            // event(new PreviewReadyEvent($proxy));

        } catch (\Exception $e) {
            Log::error('ProcessPreviewJob: Preview generation failed', [
                'uuid' => $this->uuid,
                'error' => $e->getMessage(),
            ]);

            $this->markFailed($proxy, $e->getMessage());

            // End trace session on failure
            if ($sessionId) {
                TraceSessionEnded::dispatch($this->uuid, $sessionId, false, $e->getMessage());
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Verify that generated files exist and are readable.
     *
     * This prevents a race condition where the database is updated
     * before the filesystem has finished syncing the new files,
     * causing the frontend to receive URLs for non-existent files.
     */
    protected function verifyFilesExist(?string $previewPath, ?string $thumbnailPath, string $disk): bool
    {
        // Clear PHP's stat cache to ensure we see freshly written files
        clearstatcache();

        $storage = Storage::disk($disk);

        // Verify preview file if we have one
        if ($previewPath && !$storage->exists($previewPath)) {
            return false;
        }

        // Verify thumbnail file if we have one
        if ($thumbnailPath && !$storage->exists($thumbnailPath)) {
            return false;
        }

        // Additional check: verify files are readable and have content
        if ($previewPath && $storage->size($previewPath) === 0) {
            return false;
        }

        if ($thumbnailPath && $storage->size($thumbnailPath) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Mark the preview as failed
     */
    protected function markFailed(ProxyImage $proxy, string $error): void
    {
        $proxy->update([
            'preview_status' => 'failed',
            'preview_error' => substr($error, 0, 255),
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();

        if ($proxy) {
            $proxy->update([
                'preview_status' => 'failed',
                'preview_error' => 'Max retries exceeded: ' . substr($exception->getMessage(), 0, 200),
            ]);
        }

        Log::error('ProcessPreviewJob: Job failed permanently', [
            'uuid' => $this->uuid,
            'error' => $exception->getMessage(),
        ]);
    }
}
