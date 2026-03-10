<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Facades\Log;
use ProPhoto\Ingest\Events\PreviewExtractionAttempted;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * ExifToolService - Handles all ExifTool operations for metadata and preview extraction
 *
 * This service provides a robust interface to ExifTool for:
 * - Batch metadata extraction (JSON output)
 * - Embedded preview/thumbnail extraction
 * - Health checks for binary availability
 *
 * ExifTool Speed Flags:
 * - `-fast`: Skips some processing for speed (safe for most metadata)
 * - `-fast2`: More aggressive, may skip some fields (use for previews/quick scans)
 * - Default (no fast): Full extraction, slower but complete
 */
class ExifToolService
{
    protected string $binaryPath;
    protected int $timeout;
    protected array $defaultOptions;

    public function __construct()
    {
        // Use dedicated exiftool config (not ingest.exiftool)
        $this->binaryPath = config('exiftool.bin', 'exiftool');
        $this->timeout = config('ingest.exiftool.timeout', 30);
        $this->defaultOptions = config('ingest.exiftool.default_options', [
            '-j',                    // JSON output
            '-n',                    // Numeric values (no formatting)
            '-charset', 'filename=UTF8',
            '-api', 'QuickTimeUTC=1', // Consistent timezone handling for video
        ]);
    }

    /**
     * Extract metadata from one or more files
     *
     * @param array|string $paths Single path or array of file paths
     * @param array $options Additional ExifTool options
     * @return array Associative array keyed by FileName, or single file array if one path provided
     * @throws \RuntimeException If ExifTool fails or returns invalid JSON
     */
    public function extractMetadata(array|string $paths, array $options = []): array
    {
        $paths = (array) $paths;
        $isSingleFile = count($paths) === 1;

        // Validate and sanitize paths
        $validPaths = $this->validatePaths($paths);

        if (empty($validPaths)) {
            Log::warning('ExifTool: No valid paths provided for metadata extraction');
            return [];
        }

        // Build command arguments
        $args = $this->buildMetadataArgs($options);

        // Add file paths
        foreach ($validPaths as $path) {
            $args[] = $path;
        }

        // Execute and parse
        $startTime = microtime(true);
        $result = $this->execute($args);
        $duration = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('ExifTool metadata extraction completed', [
            'file_count' => count($validPaths),
            'duration_ms' => $duration,
            'avg_per_file_ms' => round($duration / count($validPaths), 2),
        ]);

        // Parse JSON response
        $parsed = $this->parseJsonResponse($result);

        if ($parsed === null) {
            Log::error('ExifTool returned invalid JSON', [
                'raw_output' => substr($result, 0, 500),
            ]);
            throw new \RuntimeException('ExifTool returned invalid JSON response');
        }

        // Index by FileName for batch access
        $indexed = [];
        foreach ($parsed as $item) {
            $fileName = $item['FileName'] ?? basename($item['SourceFile'] ?? '');
            $indexed[$fileName] = $item;
        }

        // Return single item directly if single file was requested
        if ($isSingleFile) {
            return reset($indexed) ?: [];
        }

        return $indexed;
    }

    /**
     * Extract embedded preview image from a file
     *
     * Tries multiple preview tags in order of preference:
     * 1. PreviewImage - High quality embedded preview (Sony ARW, Nikon NEF)
     * 2. JpgFromRaw - RAW file embedded JPEG (Canon CR2)
     * 3. ThumbnailImage - Smaller thumbnail fallback
     *
     * @param string $path Source file path
     * @param string|null $outputPath If provided, writes preview to this path instead of returning bytes
     * @param string|null $previewTag Specific tag to extract (overrides auto-detection)
     * @param string|null $uuid Optional UUID for trace events
     * @param string|null $sessionId Optional session ID for trace events
     * @return string|false Raw JPEG bytes, output path if $outputPath provided, or false on failure
     */
    public function extractPreview(string $path, ?string $outputPath = null, ?string $previewTag = null, ?string $uuid = null, ?string $sessionId = null): string|false
    {
        if (!$this->isValidPath($path)) {
            Log::warning('ExifTool: Invalid path for preview extraction', ['path' => $path]);
            return false;
        }

        // Preview tags to try in order of preference
        $previewTags = $previewTag
            ? [$previewTag]
            : config('ingest.exiftool.preview_tags', [
                'PreviewImage',
                'JpgFromRaw',
                'ThumbnailImage',
            ]);

        $order = 0;
        foreach ($previewTags as $tag) {
            $order++;
            $startTime = microtime(true);
            $result = $this->tryExtractPreviewTag($path, $tag, $outputPath);
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            if ($result !== false) {
                $size = is_string($result) && !str_starts_with($result, '/') ? strlen($result) : (file_exists($outputPath) ? filesize($outputPath) : null);

                Log::debug('ExifTool preview extracted', [
                    'path' => $path,
                    'tag' => $tag,
                    'size' => $size ?? 'file',
                ]);

                // Dispatch success event for tracing
                if ($uuid && $sessionId) {
                    PreviewExtractionAttempted::dispatch(
                        $uuid,
                        $sessionId,
                        $tag,
                        $order,
                        true,
                        null,
                        ['duration_ms' => $durationMs, 'size' => $size]
                    );
                }

                return $result;
            }

            // Dispatch failure event for tracing
            if ($uuid && $sessionId) {
                PreviewExtractionAttempted::dispatch(
                    $uuid,
                    $sessionId,
                    $tag,
                    $order,
                    false,
                    'Tag not found or extraction failed',
                    ['duration_ms' => $durationMs]
                );
            }
        }

        Log::debug('ExifTool: No embedded preview found', [
            'path' => $path,
            'tried_tags' => $previewTags,
        ]);

        return false;
    }

    /**
     * Try to extract a specific preview tag
     */
    protected function tryExtractPreviewTag(string $path, string $tag, ?string $outputPath): string|false
    {
        $args = ['-b', "-{$tag}", $path];

        if ($outputPath) {
            // Write directly to file
            $args = ['-b', "-{$tag}", '-w', '%0f_preview.jpg', '-W', $outputPath, $path];

            // Alternative approach: extract to stdout, write to file
            try {
                $result = $this->execute(['-b', "-{$tag}", $path], binary: true);

                if (empty($result) || strlen($result) < 100) {
                    return false;
                }

                // Check for max preview size (8MB limit)
                $maxSize = config('ingest.exiftool.max_preview_size', 8 * 1024 * 1024);
                if (strlen($result) > $maxSize) {
                    Log::warning('ExifTool preview exceeds max size, will downscale', [
                        'size' => strlen($result),
                        'max' => $maxSize,
                    ]);
                }

                // Write to output path
                $written = file_put_contents($outputPath, $result);
                if ($written === false) {
                    Log::error('Failed to write preview file', ['output_path' => $outputPath]);
                    return false;
                }

                return $outputPath;
            } catch (\Exception $e) {
                return false;
            }
        }

        // Return raw bytes
        try {
            $result = $this->execute(['-b', "-{$tag}", $path], binary: true);

            if (empty($result) || strlen($result) < 100) {
                return false;
            }

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if ExifTool binary is available and responsive
     *
     * @return bool True if ExifTool is available and working
     */
    public function healthCheck(): bool
    {
        try {
            $result = $this->execute(['-ver'], timeout: 5);
            $version = trim($result);

            if (preg_match('/^\d+\.\d+/', $version)) {
                Log::debug('ExifTool health check passed', ['version' => $version]);
                return true;
            }

            Log::warning('ExifTool health check: unexpected version format', ['output' => $version]);
            return false;
        } catch (\Exception $e) {
            Log::error('ExifTool health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get ExifTool version string
     *
     * @return string|null Version string or null if unavailable
     */
    public function getVersion(): ?string
    {
        try {
            $result = $this->execute(['-ver'], timeout: 5);
            return trim($result) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Build command arguments for metadata extraction
     *
     * @param array $options Options including 'speed_mode' (fast, fast2, full)
     * @return array Command arguments
     */
    protected function buildMetadataArgs(array $options): array
    {
        $args = $this->defaultOptions;

        // Add speed flag based on configuration or option
        $speedMode = $options['speed_mode'] ?? $options['speed'] ?? config('ingest.exiftool.speed_mode', 'fast');

        if ($speedMode === 'fast2') {
            // Fastest - skips maker notes and some metadata groups
            $args[] = '-fast2';
        } elseif ($speedMode === 'fast') {
            // Fast - skips maker notes
            $args[] = '-fast';
        }
        // 'full' mode: no speed flag, complete extraction

        // Add group names if requested
        if ($options['groups'] ?? config('ingest.exiftool.include_groups', false)) {
            $args[] = '-G';
        }

        // Add any custom arguments
        if (!empty($options['args'])) {
            $args = array_merge($args, (array) $options['args']);
        }

        return $args;
    }

    /**
     * Execute ExifTool command
     *
     * @param array $args Command arguments
     * @param int|null $timeout Custom timeout in seconds
     * @param bool $binary Whether to expect binary output (don't trim/decode)
     * @return string Command output
     * @throws \RuntimeException If command fails
     */
    protected function execute(array $args, ?int $timeout = null, bool $binary = false): string
    {
        $command = array_merge([$this->binaryPath], $args);

        // Build augmented environment with PATH prefix if configured
        $env = $this->buildEnvironment();

        // Log command execution details
        $this->logCommandExecution($command, $env);

        $process = new Process($command, null, $env);
        $process->setTimeout($timeout ?? $this->timeout);

        try {
            $process->run();

            // Check for process errors
            if (!$process->isSuccessful()) {
                $exitCode = $process->getExitCode();
                $errorOutput = $process->getErrorOutput();

                // ExifTool exit code 1 with output usually means "file(s) not recognized" but still returns data
                // Exit code 1 with no output is an error
                if ($exitCode === 1 && !empty($process->getOutput())) {
                    // Partial success - some files may have had issues
                    Log::debug('ExifTool completed with warnings', [
                        'stderr' => trim($errorOutput),
                    ]);
                    return $binary ? $process->getOutput() : trim($process->getOutput());
                }

                // Log the full error for debugging
                Log::error('ExifTool command failed', [
                    'exit_code' => $exitCode,
                    'binary' => $this->binaryPath,
                    'command' => implode(' ', $command),
                    'error_output' => substr(trim($errorOutput), 0, 1000),
                ]);

                throw new \RuntimeException(
                    "ExifTool failed (exit code {$exitCode}): " . substr(trim($errorOutput), 0, 1000)
                );
            }

            return $binary ? $process->getOutput() : trim($process->getOutput());
        } catch (ProcessFailedException $e) {
            Log::error('ExifTool process failed', [
                'command' => implode(' ', $command),
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException('ExifTool process failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Build environment array with PATH augmentation if configured
     *
     * @return array|null Environment variables or null to inherit
     */
    protected function buildEnvironment(): ?array
    {
        $pathPrefix = config('exiftool.path_prefix');

        if (empty($pathPrefix)) {
            return null;
        }

        $currentPath = getenv('PATH') ?: '';
        $augmentedPath = rtrim($pathPrefix, ':') . ($currentPath ? (':' . $currentPath) : '');

        return ['PATH' => $augmentedPath];
    }

    /**
     * Log command execution details for diagnostics
     *
     * @param array $command Full command array
     * @param array|null $env Environment variables
     * @return void
     */
    protected function logCommandExecution(array $command, ?array $env): void
    {
        $logData = [
            'binary' => $this->binaryPath,
            'command_line' => implode(' ', $command),
        ];

        if ($env && isset($env['PATH'])) {
            $logData['path_prefix'] = substr($env['PATH'], 0, 200);
        }

        Log::debug('Executing ExifTool command', $logData);
    }

    /**
     * Parse JSON response from ExifTool
     *
     * @param string $json Raw JSON string
     * @return array|null Parsed array or null on failure
     */
    protected function parseJsonResponse(string $json): ?array
    {
        if (empty($json)) {
            return null;
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : null;
        } catch (\JsonException $e) {
            Log::debug('ExifTool JSON parse error', [
                'error' => $e->getMessage(),
                'raw_length' => strlen($json),
            ]);
            return null;
        }
    }

    /**
     * Validate file paths for safety and existence
     *
     * @param array $paths File paths to validate
     * @return array Valid paths only
     */
    protected function validatePaths(array $paths): array
    {
        return array_filter($paths, fn($path) => $this->isValidPath($path));
    }

    /**
     * Check if a single path is valid and safe
     */
    protected function isValidPath(string $path): bool
    {
        // Must be absolute or resolvable path
        if (empty($path)) {
            return false;
        }

        // Block path traversal attempts
        if (str_contains($path, '..') || str_contains($path, "\0")) {
            Log::warning('ExifTool: Blocked suspicious path', ['path' => $path]);
            return false;
        }

        // Check file exists and is readable
        if (!file_exists($path) || !is_readable($path)) {
            Log::debug('ExifTool: File not found or not readable', ['path' => $path]);
            return false;
        }

        return true;
    }

    /**
     * Normalize ExifTool metadata to application schema
     *
     * Maps ExifTool field names to standardized application field names
     * and converts values to appropriate types.
     *
     * @param array $raw Raw ExifTool metadata
     * @return array Normalized metadata following application schema
     */
    public function normalizeMetadata(array $raw): array
    {
        $normalized = [];

        // Date/time fields (with timezone handling)
        $normalized['date_taken'] = $this->parseDateTaken($raw);

        // Camera info
        $normalized['camera_make'] = $this->cleanString($raw['Make'] ?? null);
        $normalized['camera_model'] = $this->cleanString($raw['Model'] ?? null);
        $normalized['camera'] = $this->buildCameraSlug($normalized['camera_make'], $normalized['camera_model']);

        // Lens info
        $normalized['lens'] = $this->cleanString($raw['LensModel'] ?? $raw['Lens'] ?? null);

        // Exposure settings
        $normalized['f_stop'] = $this->parseNumericValue($raw['FNumber'] ?? $raw['Aperture'] ?? null);
        $normalized['shutter_speed'] = $this->parseShutterSpeed($raw['ExposureTime'] ?? $raw['ShutterSpeed'] ?? null);
        $normalized['shutter_speed_display'] = $this->formatShutterSpeedDisplay($raw['ExposureTime'] ?? $raw['ShutterSpeed'] ?? null);
        $normalized['iso'] = $this->parseIntValue($raw['ISO'] ?? $raw['ISOSpeedRatings'] ?? null);

        // Focal length
        $normalized['focal_length'] = $this->parseFocalLength($raw['FocalLength'] ?? null);

        // GPS coordinates (ExifTool with -n returns decimal degrees directly)
        $normalized['gps_lat'] = $this->parseGpsCoordinate($raw, 'Latitude');
        $normalized['gps_lng'] = $this->parseGpsCoordinate($raw, 'Longitude');

        // Image dimensions
        $normalized['width'] = $this->parseIntValue($raw['ImageWidth'] ?? $raw['ExifImageWidth'] ?? null);
        $normalized['height'] = $this->parseIntValue($raw['ImageHeight'] ?? $raw['ExifImageHeight'] ?? null);

        // File info
        $normalized['file_type'] = $raw['FileType'] ?? null;
        $normalized['mime_type'] = $raw['MIMEType'] ?? null;
        $normalized['file_size'] = $this->parseIntValue($raw['FileSize'] ?? null);

        // Additional useful fields
        $normalized['orientation'] = $this->parseIntValue($raw['Orientation'] ?? null);
        $normalized['color_space'] = $raw['ColorSpace'] ?? null;
        $normalized['software'] = $raw['Software'] ?? null;

        // Filter out null values
        return array_filter($normalized, fn($v) => $v !== null);
    }

    /**
     * Parse date taken from various ExifTool fields
     */
    protected function parseDateTaken(array $raw): ?string
    {
        // Priority order for date fields
        $dateFields = [
            'DateTimeOriginal',
            'CreateDate',
            'DateTimeDigitized',
            'ModifyDate',
            'FileModifyDate',
        ];

        // Timezone offset fields
        $offsetFields = [
            'OffsetTimeOriginal',
            'OffsetTime',
            'OffsetTimeDigitized',
        ];

        $dateValue = null;
        foreach ($dateFields as $field) {
            if (!empty($raw[$field])) {
                $dateValue = $raw[$field];
                break;
            }
        }

        if (!$dateValue) {
            return null;
        }

        // Try to get timezone offset
        $offset = null;
        foreach ($offsetFields as $field) {
            if (!empty($raw[$field])) {
                $offset = $raw[$field];
                break;
            }
        }

        // Parse the date string (format: "2025:10:23 12:21:28")
        try {
            // Replace EXIF date format with standard format
            $dateValue = str_replace(':', '-', substr($dateValue, 0, 10)) . substr($dateValue, 10);

            if ($offset) {
                $dateValue .= $offset;
            }

            $dt = new \DateTimeImmutable($dateValue);
            return $dt->format('c'); // ISO 8601 format
        } catch (\Exception $e) {
            Log::debug('ExifTool: Failed to parse date', [
                'value' => $dateValue,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse GPS coordinate from ExifTool data
     *
     * With -n flag, ExifTool returns decimal degrees directly.
     * GPSLatitude/GPSLongitude will be positive numbers.
     * GPSLatitudeRef/GPSLongitudeRef indicate N/S and E/W.
     */
    protected function parseGpsCoordinate(array $raw, string $type): ?float
    {
        $value = $raw["GPS{$type}"] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        $coord = (float) $value;

        // Apply hemisphere reference for sign
        $ref = $raw["GPS{$type}Ref"] ?? null;
        if ($ref === 'S' || $ref === 'W') {
            $coord = -abs($coord);
        }

        return round($coord, 8);
    }

    /**
     * Parse shutter speed to decimal seconds
     */
    protected function parseShutterSpeed($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already numeric (from -n flag)
        if (is_numeric($value)) {
            return (float) $value;
        }

        // Fraction string like "1/250"
        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            return $den > 0 ? (float) $num / (float) $den : null;
        }

        return null;
    }

    /**
     * Format shutter speed for display (e.g., "1/250s" or "2s")
     */
    protected function formatShutterSpeedDisplay($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Already a fraction string
        if (is_string($value) && str_contains($value, '/')) {
            return $value . 's';
        }

        // Numeric value
        if (is_numeric($value)) {
            $val = (float) $value;
            if ($val >= 1) {
                return round($val, 1) . 's';
            }
            // Convert to fraction
            $denominator = round(1 / $val);
            return "1/{$denominator}s";
        }

        return null;
    }

    /**
     * Parse focal length, handling "35 mm" format
     */
    protected function parseFocalLength($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) round((float) $value);
        }

        // Extract numeric portion from string like "35 mm" or "35mm"
        if (is_string($value) && preg_match('/(\d+(?:\.\d+)?)/', $value, $matches)) {
            return (int) round((float) $matches[1]);
        }

        return null;
    }

    /**
     * Parse numeric float value
     */
    protected function parseNumericValue($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        // Handle fraction strings
        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            return $den > 0 ? round((float) $num / (float) $den, 2) : null;
        }

        return null;
    }

    /**
     * Parse integer value
     */
    protected function parseIntValue($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        // Extract first number from string
        if (is_string($value) && preg_match('/(\d+)/', $value, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Clean string value (trim, remove null bytes)
     */
    protected function cleanString($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $cleaned = trim(str_replace("\0", '', (string) $value));
        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Build slugified camera identifier
     */
    protected function buildCameraSlug(?string $make, ?string $model): ?string
    {
        $parts = array_filter([$make, $model]);
        if (empty($parts)) {
            return null;
        }

        return \Illuminate\Support\Str::slug(implode(' ', $parts));
    }
}
