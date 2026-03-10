<?php

namespace ProPhoto\Assets\Services\Metadata;

use ProPhoto\Contracts\Contracts\Metadata\AssetMetadataNormalizerContract;
use ProPhoto\Contracts\DTOs\NormalizedAssetMetadata;
use ProPhoto\Contracts\DTOs\RawMetadataBundle;

class PassThroughAssetMetadataNormalizer implements AssetMetadataNormalizerContract
{
    public function normalize(RawMetadataBundle $rawBundle): NormalizedAssetMetadata
    {
        $schemaVersion = (string) config('prophoto-assets.metadata.normalizer_schema_version', 'v1');
        $normalizerVersion = (string) config('prophoto-assets.metadata.normalizer_version', 'assets-normalizer-v1');
        $normalizedAt = now()->toISOString();
        $payload = $rawBundle->payload;
        $mediaKind = $this->detectMediaKind($payload);

        $capturedAtCandidate = $this->firstValueWithKey($payload, [
            'user_captured_at',
            'DateTimeOriginal',
            'CreateDate',
            'MediaCreateDate',
            'creation_date',
            'date_taken',
            'FileModifyDate',
            'file_mtime',
            'ingested_at',
        ]);
        $capturedAt = $this->parseDate($capturedAtCandidate['value']);
        $capturedAtSource = $this->mapCapturedAtSource($capturedAtCandidate['key']);
        $timezoneSource = $this->resolveTimezoneSource($capturedAtCandidate['value'], $capturedAt);

        $width = $this->toInt($this->firstValue($payload, ['width', 'ImageWidth', 'ExifImageWidth']));
        $height = $this->toInt($this->firstValue($payload, ['height', 'ImageHeight', 'ExifImageHeight']));
        $iso = $this->toInt($this->firstValue($payload, ['iso', 'ISO', 'ISOSpeedRatings']));
        $cameraMake = $this->toString($this->firstValue($payload, ['camera_make', 'Make']));
        $cameraModel = $this->toString($this->firstValue($payload, ['camera_model', 'Model']));
        $lensModel = $this->toString($this->firstValue($payload, ['lens_model', 'LensModel', 'lens']));
        $mimeType = $this->toString($this->firstValue($payload, ['mime_type', 'MIMEType']));
        $fileSize = $this->toInt($this->firstValue($payload, ['file_size', 'FileSize']));
        $exifOrientation = $this->toInt($this->firstValue($payload, ['exif_orientation', 'orientation', 'Orientation']));
        $shutterSpeedDisplay = $this->toString($this->firstValue($payload, ['shutter_speed_display', 'shutter_speed', 'ExposureTime']));
        $shutterSpeedSeconds = $this->toFloat(
            $this->firstValue($payload, ['shutter_speed_seconds', 'ExposureTime', 'shutter_speed'])
        );
        $aperture = $this->toFloat($this->firstValue($payload, ['aperture', 'f_stop', 'FNumber']));
        $focalLength = $this->toFloat($this->firstValue($payload, ['focal_length', 'FocalLength']));
        $colorProfile = $this->toString($this->firstValue($payload, ['color_profile', 'ColorSpace', 'ICCProfileName']));
        $rating = $this->normalizeRating($this->toInt($this->firstValue($payload, ['rating', 'Rating'])));
        $gpsLat = $this->toFloat($this->firstValue($payload, ['gps_lat', 'GPSLatitude']));
        $gpsLng = $this->toFloat($this->firstValue($payload, ['gps_lng', 'GPSLongitude']));
        $keywords = $this->parseKeywords($this->firstValue($payload, ['keywords', 'Keywords', 'Subject', 'tags']));
        $gpsRedacted = $this->toBool($this->firstValue($payload, ['gps_redacted', 'is_gps_redacted'])) ?? false;
        $pageCount = $this->toInt($this->firstValue($payload, ['PageCount', 'pages', 'page_count']));
        $durationSeconds = $this->toFloat($this->firstValue($payload, ['Duration', 'duration_seconds']));
        $sourceRecordKind = $this->detectSourceRecordKind($payload, $rawBundle->source);
        $extractedAt = $this->parseDate($this->firstValue($payload, ['extracted_at', 'ExtractedAt']));
        // has_gps reflects coordinate presence, independent of presentation redaction.
        $hasGps = $gpsLat !== null && $gpsLng !== null;

        $normalizedPayload = [
            'media_kind' => $mediaKind,
            'captured_at' => $capturedAt,
            'captured_at_source' => $capturedAtSource,
            'timezone_source' => $timezoneSource,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'dimensions' => [
                'width' => $width,
                'height' => $height,
                'exif_orientation' => $exifOrientation,
            ],
            'camera' => [
                'make' => $cameraMake,
                'model' => $cameraModel,
                'lens_model' => $lensModel,
            ],
            'exposure' => [
                'iso' => $iso,
                'shutter_speed_display' => $shutterSpeedDisplay,
                'shutter_speed_seconds' => $shutterSpeedSeconds,
                'aperture' => $aperture,
                'focal_length' => $focalLength,
            ],
            'color_profile' => $colorProfile,
            'keywords' => $keywords,
            // 0-5 scale. null means no rating metadata.
            'rating' => $rating,
            'gps' => [
                'lat' => $gpsLat,
                'lng' => $gpsLng,
                'is_redacted' => $gpsRedacted,
            ],
            'document' => [
                'page_count' => $pageCount,
                'title' => $this->toString($this->firstValue($payload, ['Title', 'title'])),
                'author' => $this->toString($this->firstValue($payload, ['Author', 'author'])),
            ],
            'video' => [
                'duration_seconds' => $durationSeconds,
                'frame_rate' => $this->toFloat($this->firstValue($payload, ['VideoFrameRate', 'frame_rate'])),
                'codec' => $this->toString($this->firstValue($payload, ['CompressorName', 'VideoCodec', 'codec'])),
            ],
            'source' => [
                'extractor' => $rawBundle->source,
                'tool_version' => $rawBundle->toolVersion,
                'extracted_at' => $extractedAt,
                'source_record_kind' => $sourceRecordKind,
            ],
            'normalization' => [
                'schema_version' => $schemaVersion,
                'normalized_at' => $normalizedAt,
                'normalizer_version' => $normalizerVersion,
            ],
        ];

        $index = [
            'media_kind' => $mediaKind,
            'captured_at' => $capturedAt,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'width' => $width,
            'height' => $height,
            'exif_orientation' => $exifOrientation,
            'iso' => $iso,
            'camera_make' => $cameraMake,
            'camera_model' => $cameraModel,
            'lens' => $lensModel,
            'rating' => $rating,
            'color_profile' => $colorProfile,
            'page_count' => $pageCount,
            'duration_seconds' => $durationSeconds,
            'has_gps' => $hasGps,
        ];

        return new NormalizedAssetMetadata(
            schemaVersion: $schemaVersion,
            payload: $normalizedPayload,
            index: $index,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function detectMediaKind(array $payload): string
    {
        $mimeType = strtolower((string) ($this->firstValue($payload, ['mime_type', 'MIMEType']) ?? ''));
        $fileType = strtolower((string) ($this->firstValue($payload, ['file_type', 'FileType']) ?? ''));

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if ($mimeType === 'application/pdf' || $fileType === 'pdf') {
            return 'pdf';
        }

        return match ($fileType) {
            'jpg', 'jpeg', 'png', 'heic', 'heif', 'gif', 'webp' => 'image',
            'mp4', 'mov', 'm4v', 'avi', 'mkv', 'webm' => 'video',
            default => 'other',
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     */
    protected function firstValue(array $payload, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return $payload[$key];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $keys
     * @return array{key: ?string, value: mixed}
     */
    protected function firstValueWithKey(array $payload, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                return [
                    'key' => $key,
                    'value' => $payload[$key],
                ];
            }
        }

        return [
            'key' => null,
            'value' => null,
        ];
    }

    protected function toString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $normalized = trim((string) $value);

            return $normalized === '' ? null : $normalized;
        }

        return null;
    }

    protected function toInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || is_numeric($value)) {
            return (int) round((float) $value);
        }

        if (is_string($value)) {
            if (preg_match('/-?\d+/', $value, $matches) === 1) {
                return (int) $matches[0];
            }
        }

        return null;
    }

    protected function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) || is_int($value) || is_numeric($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^\d+\/\d+$/', $trimmed) === 1) {
            [$num, $den] = explode('/', $trimmed, 2);
            if ((float) $den === 0.0) {
                return null;
            }

            return (float) $num / (float) $den;
        }

        if (preg_match('/-?\d+(\.\d+)?/', $trimmed, $matches) === 1) {
            return (float) $matches[0];
        }

        return null;
    }

    protected function toBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no'], true)) {
                return false;
            }
        }

        return null;
    }

    protected function parseDate(mixed $value): ?string
    {
        $raw = $this->toString($value);
        if ($raw === null) {
            return null;
        }

        $candidate = preg_replace('/^(\d{4}):(\d{2}):(\d{2})(.*)$/', '$1-$2-$3$4', $raw) ?? $raw;
        if (preg_match('/^\d{4}-\d{2}-\d{2}\s/', $candidate) === 1) {
            $candidate = preg_replace('/^(\d{4}-\d{2}-\d{2})\s+/', '$1T', $candidate);
        }

        try {
            return (new \DateTimeImmutable($candidate))->format(\DateTimeInterface::ATOM);
        } catch (\Throwable) {
            return null;
        }
    }

    protected function mapCapturedAtSource(?string $key): ?string
    {
        return match ($key) {
            'DateTimeOriginal' => 'exif',
            'CreateDate', 'creation_date' => 'pdf_info',
            'FileModifyDate', 'file_mtime' => 'file_mtime',
            'user_captured_at' => 'user_override',
            'date_taken' => 'exif',
            'ingested_at' => 'ingested_at',
            default => null,
        };
    }

    protected function resolveTimezoneSource(mixed $capturedAtRaw, ?string $capturedAt): ?string
    {
        $raw = $this->toString($capturedAtRaw);
        if ($capturedAt === null) {
            return null;
        }

        if ($raw !== null && preg_match('/(?:Z|[+\-]\d{2}:?\d{2})$/', $raw) === 1) {
            return 'embedded';
        }

        if ($raw !== null) {
            return 'inferred';
        }

        return 'unknown';
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function detectSourceRecordKind(array $payload, string $source): string
    {
        $sourceLower = strtolower(trim($source));
        if ($sourceLower !== '') {
            if (str_contains($sourceLower, 'xmp')) {
                return 'xmp';
            }
            if (str_contains($sourceLower, 'iptc')) {
                return 'iptc';
            }
            if (str_contains($sourceLower, 'pdf')) {
                return 'pdfinfo';
            }
            if (str_contains($sourceLower, 'ffprobe')) {
                return 'ffprobe';
            }
            if (str_contains($sourceLower, 'exif')) {
                return 'exif';
            }
        }

        $hasExif = $this->firstValue($payload, ['DateTimeOriginal', 'Make', 'Model', 'LensModel']) !== null;
        $hasXmp = $this->firstValue($payload, ['XMPToolkit', 'XMP:CreateDate', 'XMP:Rating']) !== null;
        $hasIptc = $this->firstValue($payload, ['IPTC:Keywords', 'IPTC:ObjectName']) !== null;
        $hasPdf = $this->firstValue($payload, ['PageCount', 'Title', 'Author']) !== null;
        $hasVideo = $this->firstValue($payload, ['Duration', 'VideoFrameRate', 'CompressorName']) !== null;

        $detected = [];
        if ($hasExif) {
            $detected[] = 'exif';
        }
        if ($hasXmp) {
            $detected[] = 'xmp';
        }
        if ($hasIptc) {
            $detected[] = 'iptc';
        }
        if ($hasPdf) {
            $detected[] = 'pdfinfo';
        }
        if ($hasVideo) {
            $detected[] = 'ffprobe';
        }

        return match (count($detected)) {
            0 => 'unknown',
            1 => $detected[0],
            default => 'mixed',
        };
    }

    protected function normalizeRating(?int $rating): ?int
    {
        if ($rating === null) {
            return null;
        }

        return max(0, min(5, $rating));
    }

    /**
     * @return list<string>
     */
    protected function parseKeywords(mixed $value): array
    {
        if ($value === null) {
            return [];
        }

        $items = [];
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $items = preg_split('/[,;|]/', $value) ?: [];
        } else {
            return [];
        }

        $normalized = [];
        $seen = [];
        foreach ($items as $item) {
            $keyword = trim((string) $item);
            if ($keyword !== '') {
                $dedupeKey = strtolower($keyword);
                if (isset($seen[$dedupeKey])) {
                    continue;
                }
                $seen[$dedupeKey] = true;
                $normalized[] = $keyword;
            }
        }

        return $normalized;
    }
}
