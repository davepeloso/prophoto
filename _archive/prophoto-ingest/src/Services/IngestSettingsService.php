<?php

namespace ProPhoto\Ingest\Services;

use Illuminate\Support\Facades\Cache;
use ProPhoto\Ingest\Models\IngestSetting;

class IngestSettingsService
{
    /**
     * Cache key for storing all settings.
     */
    protected const CACHE_KEY = 'ingest_settings_all';

    /**
     * Cache TTL in seconds (1 hour).
     */
    protected const CACHE_TTL = 3600;

    /**
     * Get all settings as a keyed array with automatic type casting.
     *
     * @return array<string, mixed>
     */
    public function getAll(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return IngestSetting::all()
                ->pluck('casted_value', 'key')
                ->toArray();
        });
    }

    /**
     * Get a single setting value by key with optional default.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $settings = $this->getAll();
        return $settings[$key] ?? $default;
    }

    /**
     * Set a setting value (creates or updates).
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return IngestSetting
     */
    public function set(string $key, $value): IngestSetting
    {
        $setting = IngestSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );

        $this->clearCache();

        return $setting;
    }

    /**
     * Set multiple settings at once.
     *
     * @param  array<string, mixed>  $settings
     * @return void
     */
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            IngestSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }

        $this->clearCache();
    }

    /**
     * Delete a setting by key.
     *
     * @param  string  $key
     * @return bool
     */
    public function delete(string $key): bool
    {
        $deleted = IngestSetting::where('key', $key)->delete() > 0;

        if ($deleted) {
            $this->clearCache();
        }

        return $deleted;
    }

    /**
     * Check if a setting exists.
     *
     * @param  string  $key
     * @return bool
     */
    public function has(string $key): bool
    {
        $settings = $this->getAll();
        return array_key_exists($key, $settings);
    }

    /**
     * Clear the settings cache.
     *
     * @return void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Reset all settings to defaults (deletes all custom settings).
     *
     * @return void
     */
    public function resetAll(): void
    {
        IngestSetting::truncate();
        $this->clearCache();
    }
}
