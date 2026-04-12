<?php

namespace ProPhoto\Ingest\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Assets\Models\Asset;
use ProPhoto\Contracts\DTOs\AssetId;
use ProPhoto\Contracts\Enums\DerivativeType;
use ProPhoto\Contracts\Events\Asset\AssetDerivativesGenerated;

/**
 * GenerateAssetThumbnail
 *
 * Queued job that generates a JPEG thumbnail for a newly created Asset.
 * Dispatched by IngestSessionConfirmedListener after each asset is created.
 *
 * The thumbnail is stored alongside the original in the asset storage driver
 * under the key: `{assetId}/thumb_{width}x{height}.jpg`
 *
 * On completion, dispatches AssetDerivativesGenerated to inform the rest of
 * the system that preview imagery is ready.
 *
 * Sprint 5 — Story 1c.3
 *
 * Queue: prophoto-thumbnails (falls back to 'default')
 * Max attempts: 3
 * Backoff: 30s, 60s, 120s
 */
class GenerateAssetThumbnail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries     = 3;
    public int $timeout   = 120;

    /** @var int[] Exponential backoff in seconds */
    public array $backoff = [30, 60, 120];

    /** Thumbnail dimensions */
    private const THUMB_WIDTH  = 400;
    private const THUMB_HEIGHT = 300;

    public function __construct(
        public readonly int|string $assetId,
        public readonly string     $sourcePath,
    ) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);

        if (! $asset) {
            Log::warning('GenerateAssetThumbnail: asset not found — skipping', [
                'asset_id' => $this->assetId,
            ]);
            return;
        }

        Log::info('GenerateAssetThumbnail: starting thumbnail generation', [
            'asset_id'    => $this->assetId,
            'source_path' => $this->sourcePath,
        ]);

        $absolutePath = Storage::disk('local')->path($this->sourcePath);

        if (! is_file($absolutePath)) {
            Log::warning('GenerateAssetThumbnail: source file not found — skipping', [
                'asset_id'    => $this->assetId,
                'source_path' => $this->sourcePath,
            ]);
            return;
        }

        try {
            $thumbPath = $this->generateThumbnail($absolutePath, $this->assetId);

            // Record thumb storage key on the asset metadata
            $meta = $asset->metadata ?? [];
            $meta['storage_key_thumb'] = $thumbPath;
            $asset->forceFill(['metadata' => $meta])->save();

            event(new AssetDerivativesGenerated(
                assetId: AssetId::from($asset->id),
                derivativeTypes: [DerivativeType::THUMBNAIL],
                occurredAt: now()->toISOString(),
            ));

            Log::info('GenerateAssetThumbnail: thumbnail generated', [
                'asset_id'   => $this->assetId,
                'thumb_path' => $thumbPath,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateAssetThumbnail: thumbnail generation failed', [
                'asset_id' => $this->assetId,
                'error'    => $e->getMessage(),
            ]);
            throw $e; // Let the queue driver handle retries
        }
    }

    /**
     * Generate a thumbnail JPEG using GD (bundled with PHP).
     * Falls back gracefully if the source is not a supported image type.
     *
     * Returns the storage key relative to the local disk root.
     */
    private function generateThumbnail(string $absolutePath, int|string $assetId): string
    {
        $imageInfo = @getimagesize($absolutePath);

        if (! $imageInfo) {
            // Non-image binary — store a placeholder flag and return early
            $thumbKey = "thumbnails/{$assetId}/no_preview.txt";
            Storage::disk('local')->put($thumbKey, 'no_preview');
            return $thumbKey;
        }

        [$srcWidth, $srcHeight, $imageType] = $imageInfo;

        $src = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($absolutePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($absolutePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($absolutePath),
            default        => false,
        };

        if (! $src) {
            // Unsupported image type (e.g. RAW/HEIC) — placeholder
            $thumbKey = "thumbnails/{$assetId}/no_preview.txt";
            Storage::disk('local')->put($thumbKey, 'no_preview');
            return $thumbKey;
        }

        // Compute aspect-ratio-preserving crop to fill THUMB_WIDTH × THUMB_HEIGHT
        $srcAspect   = $srcWidth / max($srcHeight, 1);
        $thumbAspect = self::THUMB_WIDTH / self::THUMB_HEIGHT;

        if ($srcAspect > $thumbAspect) {
            // Source is wider — crop width
            $cropH = $srcHeight;
            $cropW = (int) round($srcHeight * $thumbAspect);
            $cropX = (int) round(($srcWidth - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source is taller — crop height
            $cropW = $srcWidth;
            $cropH = (int) round($srcWidth / $thumbAspect);
            $cropX = 0;
            $cropY = (int) round(($srcHeight - $cropH) / 2);
        }

        $thumb = imagecreatetruecolor(self::THUMB_WIDTH, self::THUMB_HEIGHT);
        imagecopyresampled(
            $thumb, $src,
            0, 0, $cropX, $cropY,
            self::THUMB_WIDTH, self::THUMB_HEIGHT,
            $cropW, $cropH,
        );

        // Capture output to in-memory buffer, then write to storage
        ob_start();
        imagejpeg($thumb, null, 85);
        $jpegData = ob_get_clean();

        imagedestroy($src);
        imagedestroy($thumb);

        $thumbKey = "thumbnails/{$assetId}/thumb_" . self::THUMB_WIDTH . 'x' . self::THUMB_HEIGHT . '.jpg';
        Storage::disk('local')->put($thumbKey, $jpegData);

        return $thumbKey;
    }

    /**
     * Handle a job failure after all retries are exhausted.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GenerateAssetThumbnail: job failed after all retries', [
            'asset_id' => $this->assetId,
            'error'    => $exception->getMessage(),
        ]);
    }
}
