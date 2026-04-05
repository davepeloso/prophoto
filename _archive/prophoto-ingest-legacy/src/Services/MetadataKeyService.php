<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use ProPhoto\Ingest\Models\Image;
use ProPhoto\Ingest\Models\ProxyImage;

class MetadataKeyService
{
    /**
     * Cache key for storing available metadata keys.
     */
    protected const CACHE_KEY = 'ingest_metadata_keys';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Common metadata keys that are typically available for chart display.
     * These are the normalized/friendly names used in the system.
     */
    protected const COMMON_CHART_KEYS = [
        'iso' => 'ISO',
        'aperture' => 'Aperture (f-stop)',
        'shutterSpeed' => 'Shutter Speed',
        'focalLength' => 'Focal Length',
        'camera' => 'Camera Model',
        'cameraMake' => 'Camera Make',
        'lens' => 'Lens',
        'dateTaken' => 'Date Taken',
    ];

    /**
     * Mapping from raw EXIF keys to friendly chart keys.
     */
    protected const EXIF_TO_CHART_MAP = [
        'ISO' => 'iso',
        'ISOSpeedRatings' => 'iso',
        'FNumber' => 'aperture',
        'ExposureTime' => 'shutterSpeed',
        'ShutterSpeedValue' => 'shutterSpeed',
        'FocalLength' => 'focalLength',
        'FocalLengthIn35mmFormat' => 'focalLength',
        'Model' => 'camera',
        'Make' => 'cameraMake',
        'LensModel' => 'lens',
        'LensInfo' => 'lens',
        'DateTimeOriginal' => 'dateTaken',
        'CreateDate' => 'dateTaken',
    ];

    /**
     * Get available metadata keys for chart field selection.
     * Returns an array of key => label pairs.
     *
     * @return array<string, string>
     */
    public function getAvailableChartKeys(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $keys = $this->extractKeysFromDatabase();

            // If no keys found in database, return common defaults
            if (empty($keys)) {
                return self::COMMON_CHART_KEYS;
            }

            return $keys;
        });
    }

    /**
     * Extract unique metadata keys from existing images in the database.
     *
     * @return array<string, string>
     */
    protected function extractKeysFromDatabase(): array
    {
        $keys = [];

        // Try to get keys from Image table first (permanent storage)
        try {
            $sampleImages = Image::whereNotNull('raw_metadata')
                ->limit(50)
                ->get(['raw_metadata']);

            foreach ($sampleImages as $image) {
                $metadata = $image->raw_metadata;
                if (is_array($metadata)) {
                    foreach (array_keys($metadata) as $rawKey) {
                        // Map EXIF key to friendly chart key if possible
                        if (isset(self::EXIF_TO_CHART_MAP[$rawKey])) {
                            $chartKey = self::EXIF_TO_CHART_MAP[$rawKey];
                            if (!isset($keys[$chartKey]) && isset(self::COMMON_CHART_KEYS[$chartKey])) {
                                $keys[$chartKey] = self::COMMON_CHART_KEYS[$chartKey];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet, fall through to defaults
        }

        // Also check ProxyImage table for recent uploads
        try {
            $sampleProxies = ProxyImage::whereNotNull('metadata')
                ->limit(50)
                ->get(['metadata']);

            foreach ($sampleProxies as $proxy) {
                $metadata = $proxy->metadata;
                if (is_array($metadata)) {
                    foreach (array_keys($metadata) as $rawKey) {
                        if (isset(self::EXIF_TO_CHART_MAP[$rawKey])) {
                            $chartKey = self::EXIF_TO_CHART_MAP[$rawKey];
                            if (!isset($keys[$chartKey]) && isset(self::COMMON_CHART_KEYS[$chartKey])) {
                                $keys[$chartKey] = self::COMMON_CHART_KEYS[$chartKey];
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Table might not exist yet, fall through to defaults
        }

        // Always include common chart keys even if not found in data
        return array_merge(self::COMMON_CHART_KEYS, $keys);
    }

    /**
     * Get a flat list of available chart field keys (without labels).
     *
     * @return array<string>
     */
    public function getChartFieldKeys(): array
    {
        return array_keys($this->getAvailableChartKeys());
    }

    /**
     * Clear the cached metadata keys.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
