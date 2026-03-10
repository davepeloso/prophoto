<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;
use ProPhoto\Ingest\Events\MetadataExtractionCompleted;
use ProPhoto\Ingest\Events\ThumbnailGenerationCompleted;

/**
 * MetadataExtractor - Unified metadata and preview extraction service
 *
 * This service coordinates metadata extraction using ExifTool (primary)
 * with PHP exif functions as a fallback. It handles:
 * - Metadata extraction (single and batch)
 * - Preview/thumbnail generation
 * - Normalization of metadata to application schema
 */
class MetadataExtractor
{
    protected ImageManager $imageManager;
    protected ExifToolService $exifToolService;
    protected bool $exifToolAvailable;

    public function __construct(
        protected array $denormalizeKeys = []
    ) {
        // Prefer Imagick for RAW support, fall back to GD
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();
        $this->imageManager = new ImageManager($driver);

        // Initialize ExifTool service
        $this->exifToolService = new ExifToolService();
        $this->exifToolAvailable = $this->exifToolService->healthCheck();

        if (!$this->exifToolAvailable) {
            Log::warning('ExifTool not available, falling back to PHP exif functions');
        }
    }

    /**
     * Fast metadata extraction - optimized for speed during upload
     *
     * Uses ExifTool with -fast2 flag which skips:
     * - Maker notes (camera-specific proprietary data)
     * - Large binary data
     * - Slower parsing operations
     *
     * This is ~2-3x faster than full extraction while still getting
     * all the metadata we need for the UI (ISO, aperture, shutter, etc.)
     *
     * @param string $filePath Absolute path to the image file
     * @return array Extraction result with metadata, raw data, and status
     */
    public function extractFast(string $filePath): array
    {
        $result = [
            'metadata' => [],
            'metadata_raw' => null,
            'extraction_method' => 'none',
            'error' => null,
        ];

        // Basic file info (always available)
        $result['metadata']['FileSize'] = @filesize($filePath) ?: null;
        $result['metadata']['FileName'] = basename($filePath);

        if ($this->exifToolAvailable) {
            try {
                // Use fast2 mode for speed - skips maker notes
                $rawMetadata = $this->exifToolService->extractMetadata($filePath, [
                    'speed_mode' => 'fast2',
                ]);

                if ($rawMetadata) {
                    $result['metadata_raw'] = $rawMetadata;
                    $result['extraction_method'] = 'exiftool_fast';

                    // Normalize to application schema
                    $normalized = $this->exifToolService->normalizeMetadata($rawMetadata);
                    $result['metadata'] = array_merge($result['metadata'], $normalized);
                    $result['metadata'] = array_merge($result['metadata'], $this->extractAdditionalFields($rawMetadata));
                }
            } catch (\Exception $e) {
                Log::warning('Fast ExifTool extraction failed', [
                    'file' => basename($filePath),
                    'error' => $e->getMessage(),
                ]);
                $result['error'] = $e->getMessage();
            }
        }

        // Fallback to PHP exif if ExifTool failed
        if ($result['extraction_method'] === 'none') {
            $result = $this->extractWithPhpExif($filePath, $result);
        }

        return $result;
    }

    /**
     * Extract all available metadata from an image file
     *
     * Returns an array with:
     * - 'metadata': Normalized metadata for application use
     * - 'metadata_raw': Raw ExifTool output (or PHP exif output)
     * - 'extraction_method': 'exiftool', 'php_exif', or 'none'
     * - 'error': Error message if extraction failed
     *
     * @param string $filePath Absolute path to the image file
     * @return array Extraction result with metadata, raw data, and status
     */
    public function extract(string $filePath): array
    {
        $result = [
            'metadata' => [],
            'metadata_raw' => null,
            'extraction_method' => 'none',
            'error' => null,
        ];

        // Basic file info (always available)
        $result['metadata']['FileSize'] = @filesize($filePath) ?: null;
        $result['metadata']['FileName'] = basename($filePath);

        // Try ExifTool first
        if ($this->exifToolAvailable) {
            try {
                $rawMetadata = $this->exifToolService->extractMetadata($filePath);

                if (!empty($rawMetadata)) {
                    $result['metadata_raw'] = $rawMetadata;
                    $result['extraction_method'] = 'exiftool';

                    // Normalize to application schema
                    $normalized = $this->exifToolService->normalizeMetadata($rawMetadata);
                    $result['metadata'] = array_merge($result['metadata'], $normalized);

                    // Also include raw fields that might be useful
                    $result['metadata'] = array_merge($result['metadata'], $this->extractAdditionalFields($rawMetadata));

                    Log::debug('ExifTool extraction successful', [
                        'file' => basename($filePath),
                        'fields_extracted' => count($rawMetadata),
                    ]);

                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('ExifTool extraction failed, falling back to PHP', [
                    'file' => basename($filePath),
                    'error' => $e->getMessage(),
                ]);
                $result['error'] = 'ExifTool failed: ' . $e->getMessage();
            }
        }

        // Fallback to PHP exif functions
        if (config('ingest.exiftool.fallback_to_php', true)) {
            $result = $this->extractWithPhpExif($filePath, $result);
        }

        // Try to get image dimensions if not already set
        if (empty($result['metadata']['ImageWidth'])) {
            $dimensions = $this->getImageDimensions($filePath);
            if ($dimensions) {
                $result['metadata']['ImageWidth'] = $dimensions['width'];
                $result['metadata']['ImageHeight'] = $dimensions['height'];
            }
        }

        return $result;
    }

    /**
     * Extract metadata from multiple files in a single ExifTool call
     *
     * More efficient than calling extract() multiple times for large batches.
     *
     * @param array $filePaths Array of absolute file paths
     * @return array Associative array keyed by filename with extraction results
     */
    public function extractBatch(array $filePaths): array
    {
        $results = [];

        if (empty($filePaths)) {
            return $results;
        }

        // If ExifTool available, use batch extraction
        if ($this->exifToolAvailable && count($filePaths) > 1) {
            try {
                $startTime = microtime(true);
                $batchResults = $this->exifToolService->extractMetadata($filePaths);
                $duration = round((microtime(true) - $startTime) * 1000, 2);

                Log::info('Batch metadata extraction completed', [
                    'file_count' => count($filePaths),
                    'duration_ms' => $duration,
                    'avg_per_file_ms' => round($duration / count($filePaths), 2),
                ]);

                foreach ($filePaths as $path) {
                    $fileName = basename($path);
                    $rawMetadata = $batchResults[$fileName] ?? null;

                    $result = [
                        'metadata' => [
                            'FileSize' => @filesize($path) ?: null,
                            'FileName' => $fileName,
                        ],
                        'metadata_raw' => $rawMetadata,
                        'extraction_method' => $rawMetadata ? 'exiftool' : 'none',
                        'error' => null,
                    ];

                    if ($rawMetadata) {
                        $normalized = $this->exifToolService->normalizeMetadata($rawMetadata);
                        $result['metadata'] = array_merge($result['metadata'], $normalized);
                        $result['metadata'] = array_merge($result['metadata'], $this->extractAdditionalFields($rawMetadata));
                    }

                    $results[$fileName] = $result;
                }

                return $results;
            } catch (\Exception $e) {
                Log::warning('Batch ExifTool extraction failed, falling back to individual extraction', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Fallback: extract individually
        foreach ($filePaths as $path) {
            $results[basename($path)] = $this->extract($path);
        }

        return $results;
    }

    /**
     * Extract embedded preview from image file using ExifTool
     *
     * @param string $filePath Source file path
     * @param string $uuid UUID for output filename
     * @param string|null $sessionId Optional session ID for trace events
     * @return string|null Path to extracted preview or null if failed
     */
    public function extractEmbeddedPreview(string $filePath, string $uuid, ?string $sessionId = null): ?string
    {
        if (!$this->exifToolAvailable) {
            return null;
        }

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $previewDir = Storage::disk($tempDisk)->path($tempPath . '/previews');

        // Ensure directory exists
        if (!is_dir($previewDir)) {
            mkdir($previewDir, 0755, true);
        }

        $outputPath = $previewDir . '/' . $uuid . '.jpg';

        try {
            // Pass uuid and sessionId for trace events
            $result = $this->exifToolService->extractPreview($filePath, $outputPath, null, $uuid, $sessionId);

            if ($result !== false && file_exists($outputPath)) {
                // Check if the extracted preview is large enough to be useful
                // Tiny EXIF thumbnails (~160px) should be rejected so we fall back
                // to generatePreviewFromSource() which creates a proper preview
                $minDimension = 800; // Minimum acceptable preview dimension

                try {
                    $image = $this->imageManager->read($outputPath);
                    $width = $image->width();
                    $height = $image->height();
                    $maxSide = max($width, $height);

                    if ($maxSide < $minDimension) {
                        Log::debug('Extracted preview too small, rejecting for fallback', [
                            'uuid' => $uuid,
                            'dimensions' => "{$width}x{$height}",
                            'min_required' => $minDimension,
                        ]);
                        // Delete the tiny preview file
                        @unlink($outputPath);
                        return null;
                    }
                } catch (\Exception $e) {
                    Log::debug('Failed to check preview dimensions', [
                        'uuid' => $uuid,
                        'error' => $e->getMessage(),
                    ]);
                    @unlink($outputPath);
                    return null;
                }

                // Preview is large enough - normalize to configured max_dimension
                $this->normalizePreview($outputPath);

                return $tempPath . '/previews/' . $uuid . '.jpg';
            }
        } catch (\Exception $e) {
            Log::debug('Failed to extract embedded preview', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate a thumbnail for the proxy display
     *
     * Uses embedded preview if available, otherwise generates from source.
     */
    public function generateThumbnail(string $sourcePath, string $uuid): ?string
    {
        $config = config('ingest.exif.thumbnail', []);
        $width = $config['width'] ?? 400;
        $height = $config['height'] ?? 400;
        $quality = $config['quality'] ?? 80;

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $thumbnailPath = $tempPath . '/thumbs/' . $uuid . '.jpg';

        try {
            $image = $this->imageManager->read($sourcePath);

            // Auto-orient based on EXIF FIRST (before any cropping)
            $image->orient();

            // Cover crop to square - explicitly specify center position
            $image->cover($width, $height, 'center');

            // Encode as JPEG
            $encoded = $image->toJpeg($quality);

            // Store
            Storage::disk($tempDisk)->put($thumbnailPath, $encoded);

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::debug('Thumbnail generation failed', [
                'file' => basename($sourcePath),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Extract ONLY the small embedded EXIF thumbnail (~160px)
     *
     * This is NOT the same as PreviewImage (which is large, ~1-2MB).
     * The ThumbnailImage tag contains a tiny ~160x120px JPEG that's
     * embedded in virtually all camera files. It's perfect for:
     * - Instant display during upload
     * - Low bandwidth
     * - Fast extraction (~20-50ms)
     *
     * We'll replace this with a better thumbnail later in the background job.
     *
     * @param string $filePath Source file path
     * @param string $uuid UUID for output filename
     * @return string|null Path to extracted thumbnail or null if not found
     */
    public function extractEmbeddedThumbnail(string $filePath, string $uuid): ?string
    {
        if (!$this->exifToolAvailable) {
            return null;
        }

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $thumbnailDir = Storage::disk($tempDisk)->path($tempPath . '/thumbs');

        // Ensure directory exists
        if (!is_dir($thumbnailDir)) {
            mkdir($thumbnailDir, 0755, true);
        }

        $outputPath = $thumbnailDir . '/' . $uuid . '.jpg';

        try {
            // Force ThumbnailImage tag only (the tiny ~160px one)
            $result = $this->exifToolService->extractPreview($filePath, $outputPath, 'ThumbnailImage');

            if ($result !== false && file_exists($outputPath) && filesize($outputPath) > 0) {
                Log::debug('Extracted embedded thumbnail', [
                    'file' => basename($filePath),
                    'size' => filesize($outputPath),
                ]);
                return $tempPath . '/thumbs/' . $uuid . '.jpg';
            }
        } catch (\Exception $e) {
            Log::debug('No embedded thumbnail found', [
                'file' => basename($filePath),
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Generate a quality thumbnail from an already-extracted preview image
     *
     * This is MUCH faster than generating from the original RAW file because:
     * - Preview is already a JPEG (no RAW decoding)
     * - Preview is already ~2048px (less data to process)
     * - No Imagick RAW delegate overhead
     *
     * @param string $previewPath Path to the preview image
     * @param string $uuid UUID for output filename
     * @return string|null Path to generated thumbnail or null on failure
     */
    public function generateThumbnailFromPreview(string $previewPath, string $uuid): ?string
    {
        $config = config('ingest.exif.thumbnail', []);
        $width = $config['width'] ?? 400;
        $height = $config['height'] ?? 400;
        $quality = $config['quality'] ?? 80;

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $thumbnailPath = $tempPath . '/thumbs/' . $uuid . '.jpg';

        // Get full path to preview
        $fullPreviewPath = Storage::disk($tempDisk)->path($previewPath);

        if (!file_exists($fullPreviewPath)) {
            Log::warning('Preview file not found for thumbnail generation', [
                'preview_path' => $previewPath,
            ]);
            return null;
        }

        try {
            $image = $this->imageManager->read($fullPreviewPath);

            // Log preview dimensions BEFORE any processing
            $previewWidth = $image->width();
            $previewHeight = $image->height();
            Log::debug('generateThumbnailFromPreview: Reading preview', [
                'uuid' => $uuid,
                'preview_dimensions' => "{$previewWidth}x{$previewHeight}",
                'preview_path' => $previewPath,
            ]);

            // DO NOT call orient() here - the preview is already visually correct
            // (orientation was applied when preview was generated from source)
            // Calling orient() again would double-rotate based on stale EXIF data

            // Cover crop to square - MUST specify 'center' position explicitly
            // Without position arg, cover() may crop from a corner instead of center
            $image->cover($width, $height, 'center');

            // Encode as JPEG
            $encoded = $image->toJpeg($quality);

            // Store
            Storage::disk($tempDisk)->put($thumbnailPath, $encoded);

            Log::debug('Generated thumbnail from preview', [
                'uuid' => $uuid,
                'size' => strlen($encoded),
                'dimensions' => $image->width() . 'x' . $image->height(),
            ]);

            return $thumbnailPath;
        } catch (\Exception $e) {
            Log::warning('Thumbnail generation from preview failed', [
                'uuid' => $uuid,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate a preview image for the preview panel
     *
     * Prefers embedded preview from ExifTool, falls back to ImageMagick.
     *
     * @param string $sourcePath Source file path
     * @param string $uuid UUID for output filename
     * @param string|null $sessionId Optional session ID for trace events
     * @return string|null Path to generated preview or null on failure
     */
    public function generatePreview(string $sourcePath, string $uuid, ?string $sessionId = null): ?string
    {
        $config = config('ingest.exif.preview', []);

        if (!($config['enabled'] ?? true)) {
            return null;
        }

        // Check if file is a standard image format (JPG, PNG, etc.)
        // These files don't have useful embedded previews - only tiny EXIF thumbnails
        // RAW files (CR2, NEF, ARW, etc.) have high-quality embedded previews worth extracting
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $standardImageFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tiff', 'tif'];
        $isStandardImage = in_array($extension, $standardImageFormats);

        if ($isStandardImage) {
            // For standard images: generate directly from source (fast and high quality)
            Log::debug('Standard image detected, generating preview from source', [
                'file' => basename($sourcePath),
                'extension' => $extension,
            ]);
            return $this->generatePreviewFromSource($sourcePath, $uuid, $config, $sessionId);
        }

        // For RAW files: try to extract embedded preview first (much faster than decoding RAW)
        $embeddedPreview = $this->extractEmbeddedPreview($sourcePath, $uuid, $sessionId);
        if ($embeddedPreview !== null) {
            Log::debug('Using embedded preview from RAW file', ['file' => basename($sourcePath)]);
            return $embeddedPreview;
        }

        // Fall back to generating preview with ImageMagick/GD (for RAW files without embedded previews)
        return $this->generatePreviewFromSource($sourcePath, $uuid, $config, $sessionId);
    }

    /**
     * Generate preview by reading and resizing the source file
     */
    protected function generatePreviewFromSource(string $sourcePath, string $uuid, array $config, ?string $sessionId = null): ?string
    {
        $maxDimension = $config['max_dimension'] ?? 2048;
        $quality = $config['quality'] ?? 85;

        $tempDisk = config('ingest.storage.temp_disk', 'local');
        $tempPath = config('ingest.storage.temp_path', 'ingest-temp');
        $previewPath = $tempPath . '/previews/' . $uuid . '.jpg';

        $startTime = microtime(true);

        try {
            $image = $this->imageManager->read($sourcePath);

            // Auto-orient based on EXIF first
            $image->orient();

            // Scale down if either dimension exceeds max
            $width = $image->width();
            $height = $image->height();

            if ($width > $maxDimension || $height > $maxDimension) {
                if ($width > $height) {
                    $image->scale(width: $maxDimension);
                } else {
                    $image->scale(height: $maxDimension);
                }
            }

            // Encode as JPEG
            $encoded = $image->toJpeg($quality);

            // Store
            Storage::disk($tempDisk)->put($previewPath, $encoded);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            // Dispatch trace event for fallback preview generation
            if ($sessionId) {
                \ProPhoto\Ingest\Events\PreviewExtractionAttempted::dispatch(
                    $uuid,
                    $sessionId,
                    'ImageManager_fallback',
                    99, // High order number to indicate fallback
                    true,
                    null,
                    ['duration_ms' => $durationMs, 'size' => strlen($encoded), 'dimensions' => $image->width() . 'x' . $image->height()]
                );
            }

            return $previewPath;
        } catch (\Exception $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            Log::debug('Preview generation from source failed', [
                'file' => basename($sourcePath),
                'error' => $e->getMessage(),
            ]);

            // Dispatch trace event for failed fallback
            if ($sessionId) {
                \ProPhoto\Ingest\Events\PreviewExtractionAttempted::dispatch(
                    $uuid,
                    $sessionId,
                    'ImageManager_fallback',
                    99,
                    false,
                    $e->getMessage(),
                    ['duration_ms' => $durationMs]
                );
            }

            return null;
        }
    }

    /**
     * Normalize preview to configured max dimensions and quality
     *
     * This ensures ALL previews (embedded or generated) conform to config settings.
     * Always checks pixel dimensions, not just file size.
     *
     * @param string $previewPath Path to the preview file
     * @return bool True if normalization was successful or not needed
     */
    protected function normalizePreview(string $previewPath): bool
    {
        $config = config('ingest.exif.preview', []);
        $maxDimension = $config['max_dimension'] ?? 2048;
        $quality = $config['quality'] ?? 85;

        try {
            $image = $this->imageManager->read($previewPath);

            $width = $image->width();
            $height = $image->height();
            $needsResize = ($width > $maxDimension || $height > $maxDimension);

            if ($needsResize) {
                Log::debug('Normalizing preview dimensions', [
                    'original' => "{$width}x{$height}",
                    'max_dimension' => $maxDimension,
                ]);

                // Auto-orient based on EXIF first
                $image->orient();

                // Scale to max dimension
                if ($width > $height) {
                    $image->scale(width: $maxDimension);
                } else {
                    $image->scale(height: $maxDimension);
                }

                // Overwrite with normalized version
                $encoded = $image->toJpeg($quality);
                file_put_contents($previewPath, $encoded);

                Log::info('Preview normalized', [
                    'path' => basename($previewPath),
                    'new_dimensions' => $image->width() . 'x' . $image->height(),
                    'new_size' => strlen($encoded),
                ]);
            }

            return true;
        } catch (\Exception $e) {
            Log::warning('Failed to normalize preview', [
                'path' => $previewPath,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * @deprecated Use normalizePreview() instead
     */
    protected function downscalePreview(string $previewPath): void
    {
        $this->normalizePreview($previewPath);
    }

    /**
     * Extract using PHP exif functions (fallback)
     */
    protected function extractWithPhpExif(string $filePath, array $result = []): array
    {
        if (!function_exists('exif_read_data')) {
            return $result;
        }

        try {
            $exif = @exif_read_data($filePath, 'ANY_TAG', true);
            if ($exif) {
                $result['metadata_raw'] = $exif;
                $result['extraction_method'] = 'php_exif';
                $result['metadata'] = array_merge($result['metadata'], $this->flattenExif($exif));
                $result['error'] = null; // Clear any previous error
            }
            return $result;
        } catch (\Exception $e) {
            return $result;
        }
    }

    /**
     * Get image dimensions using ImageManager or getimagesize
     */
    protected function getImageDimensions(string $filePath): ?array
    {
        try {
            $image = $this->imageManager->read($filePath);
            return [
                'width' => $image->width(),
                'height' => $image->height(),
            ];
        } catch (\Exception $e) {
            // Fallback to getimagesize
            $size = @getimagesize($filePath);
            if ($size) {
                return [
                    'width' => $size[0],
                    'height' => $size[1],
                ];
            }
        }

        return null;
    }

    /**
     * Extract additional useful fields from raw ExifTool output
     */
    protected function extractAdditionalFields(array $raw): array
    {
        $additional = [];

        // Keep image dimensions in standard format
        if (isset($raw['ImageWidth'])) {
            $additional['ImageWidth'] = (int) $raw['ImageWidth'];
        }
        if (isset($raw['ImageHeight'])) {
            $additional['ImageHeight'] = (int) $raw['ImageHeight'];
        }
        if (isset($raw['ExifImageWidth'])) {
            $additional['ImageWidth'] = (int) $raw['ExifImageWidth'];
        }
        if (isset($raw['ExifImageHeight'])) {
            $additional['ImageHeight'] = (int) $raw['ExifImageHeight'];
        }

        // Keep original Make/Model for backwards compatibility
        if (isset($raw['Make'])) {
            $additional['Make'] = $raw['Make'];
        }
        if (isset($raw['Model'])) {
            $additional['Model'] = $raw['Model'];
        }
        if (isset($raw['LensModel'])) {
            $additional['LensModel'] = $raw['LensModel'];
        }

        // Date fields for backwards compatibility
        if (isset($raw['DateTimeOriginal'])) {
            $additional['DateTimeOriginal'] = $raw['DateTimeOriginal'];
        }

        // Exposure values in original format
        if (isset($raw['FNumber'])) {
            $additional['FNumber'] = $raw['FNumber'];
        }
        if (isset($raw['ISO'])) {
            $additional['ISOSpeedRatings'] = $raw['ISO'];
        }
        if (isset($raw['ExposureTime'])) {
            $additional['ExposureTime'] = $raw['ExposureTime'];
        }
        if (isset($raw['FocalLength'])) {
            $additional['FocalLength'] = $raw['FocalLength'];
        }

        // GPS for backwards compatibility
        if (isset($raw['GPSLatitude'])) {
            $additional['GPSLatitude'] = $raw['GPSLatitude'];
        }
        if (isset($raw['GPSLongitude'])) {
            $additional['GPSLongitude'] = $raw['GPSLongitude'];
        }
        if (isset($raw['GPSLatitudeRef'])) {
            $additional['GPSLatitudeRef'] = $raw['GPSLatitudeRef'];
        }
        if (isset($raw['GPSLongitudeRef'])) {
            $additional['GPSLongitudeRef'] = $raw['GPSLongitudeRef'];
        }

        return $additional;
    }

    /**
     * Flatten nested EXIF array into dot notation (for PHP exif fallback)
     */
    protected function flattenExif(array $exif, string $prefix = ''): array
    {
        $result = [];

        foreach ($exif as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !$this->isSequentialArray($value)) {
                $result = array_merge($result, $this->flattenExif($value, $newKey));
            } else {
                // Clean up the value
                $result[$key] = $this->cleanExifValue($value);
            }
        }

        return $result;
    }

    /**
     * Check if array is sequential (list) vs associative
     */
    protected function isSequentialArray(array $arr): bool
    {
        return array_keys($arr) === range(0, count($arr) - 1);
    }

    /**
     * Clean up EXIF values for storage
     */
    protected function cleanExifValue($value)
    {
        if (is_array($value)) {
            return array_map([$this, 'cleanExifValue'], $value);
        }

        // Convert binary data to null
        if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        return $value;
    }

    /**
     * Parse GPS coordinates from EXIF (for PHP exif fallback compatibility)
     */
    public function parseGpsCoordinates(array $metadata): ?array
    {
        // Check for pre-normalized coordinates from ExifTool
        if (isset($metadata['gps_lat']) && isset($metadata['gps_lng'])) {
            return [
                'lat' => (float) $metadata['gps_lat'],
                'lng' => (float) $metadata['gps_lng'],
            ];
        }

        // Handle raw EXIF format
        if (empty($metadata['GPSLatitude']) || empty($metadata['GPSLongitude'])) {
            return null;
        }

        $lat = $this->gpsToDecimal(
            $metadata['GPSLatitude'],
            $metadata['GPSLatitudeRef'] ?? 'N'
        );

        $lng = $this->gpsToDecimal(
            $metadata['GPSLongitude'],
            $metadata['GPSLongitudeRef'] ?? 'E'
        );

        if ($lat === null || $lng === null) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }

    /**
     * Convert GPS EXIF format to decimal degrees
     */
    protected function gpsToDecimal($coordinate, string $hemisphere): ?float
    {
        // Already a decimal number (from ExifTool -n)
        if (is_numeric($coordinate)) {
            $decimal = (float) $coordinate;
            if (in_array($hemisphere, ['S', 'W'])) {
                $decimal = -abs($decimal);
            }
            return round($decimal, 8);
        }

        // Array format from PHP exif
        if (is_array($coordinate) && count($coordinate) >= 3) {
            $degrees = $this->evalFraction($coordinate[0]);
            $minutes = $this->evalFraction($coordinate[1]);
            $seconds = $this->evalFraction($coordinate[2]);

            $decimal = $degrees + ($minutes / 60) + ($seconds / 3600);

            if (in_array($hemisphere, ['S', 'W'])) {
                $decimal *= -1;
            }

            return round($decimal, 8);
        }

        return null;
    }

    /**
     * Evaluate EXIF fraction string (e.g., "1/250")
     */
    protected function evalFraction($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value) && str_contains($value, '/')) {
            [$numerator, $denominator] = explode('/', $value);
            return $denominator > 0 ? (float) $numerator / (float) $denominator : 0;
        }

        return 0;
    }

    /**
     * Check if ExifTool is available
     */
    public function isExifToolAvailable(): bool
    {
        return $this->exifToolAvailable;
    }

    /**
     * Get the ExifTool service instance
     */
    public function getExifToolService(): ExifToolService
    {
        return $this->exifToolService;
    }
}
