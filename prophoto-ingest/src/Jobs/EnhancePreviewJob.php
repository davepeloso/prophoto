<?php

namespace ProPhoto\Ingest\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Ingest\Models\ProxyImage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class EnhancePreviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds to wait before retrying.
     */
    public array $backoff = [5, 15];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $uuid,
        public int $targetWidth
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();

        if (!$proxy) {
            Log::warning('EnhancePreviewJob: ProxyImage not found', ['uuid' => $this->uuid]);
            return;
        }

        // Mark as processing
        $proxy->update([
            'enhancement_status' => 'processing',
            'enhancement_requested_at' => now(),
        ]);

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');

        try {
            $startTime = microtime(true);

            // Determine source for enhancement
            // Prefer existing preview, fallback to original file
            $sourcePath = null;
            if ($proxy->preview_path && Storage::disk($tempDisk)->exists($proxy->preview_path)) {
                $sourcePath = Storage::disk($tempDisk)->path($proxy->preview_path);
            } elseif ($proxy->temp_path && Storage::disk($tempDisk)->exists($proxy->temp_path)) {
                $sourcePath = Storage::disk($tempDisk)->path($proxy->temp_path);
            }

            if (!$sourcePath || !file_exists($sourcePath)) {
                throw new \Exception('Source file not found for enhancement');
            }

            // Generate enhanced preview using ImageMagick/GD
            $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
            $imageManager = new ImageManager($driver);

            $image = $imageManager->read($sourcePath);

            // Auto-orient based on EXIF
            $image->orient();

            // Get current dimensions
            $currentWidth = $image->width();
            $currentHeight = $image->height();

            // Scale to target width while preserving aspect ratio
            // (Don't crop - previews should show the full image)
            if ($currentWidth > $currentHeight) {
                $image->scale(width: $this->targetWidth);
            } else {
                $image->scale(height: $this->targetWidth);
            }

            // Encode as JPEG with high quality
            $quality = config('ingest.exif.preview.quality', 90);
            $encoded = $image->toJpeg($quality);

            // Store enhanced preview (overwrite existing)
            $previewPath = $tempPath . '/previews/' . $this->uuid . '.jpg';
            Storage::disk($tempDisk)->put($previewPath, $encoded);

            $duration = round((microtime(true) - $startTime) * 1000, 2);

            // Update record
            $proxy->update([
                'preview_path' => $previewPath,
                'preview_width' => $this->targetWidth,
                'enhancement_status' => 'ready',
            ]);

            Log::info('EnhancePreviewJob: Preview enhanced', [
                'uuid' => $this->uuid,
                'target_width' => $this->targetWidth,
                'actual_width' => $image->width(),
                'actual_height' => $image->height(),
                'duration_ms' => $duration,
                'size_bytes' => strlen($encoded),
            ]);

        } catch (\Exception $e) {
            Log::error('EnhancePreviewJob: Enhancement failed', [
                'uuid' => $this->uuid,
                'target_width' => $this->targetWidth,
                'error' => $e->getMessage(),
            ]);

            $proxy->update([
                'enhancement_status' => 'failed',
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $proxy = ProxyImage::where('uuid', $this->uuid)->first();

        if ($proxy) {
            $proxy->update([
                'enhancement_status' => 'failed',
            ]);
        }

        Log::error('EnhancePreviewJob: Job failed permanently', [
            'uuid' => $this->uuid,
            'target_width' => $this->targetWidth,
            'error' => $exception->getMessage(),
        ]);
    }
}
