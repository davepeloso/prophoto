<?php

namespace ProPhoto\Debug\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConfigSnapshot extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'debug_config_snapshots';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'description',
        'config_data',
        'queue_config',
        'supervisor_config',
        'environment',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'config_data' => 'array',
        'queue_config' => 'array',
        'supervisor_config' => 'array',
        'environment' => 'array',
    ];

    /**
     * Scope to search by name.
     */
    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', 'like', "%{$name}%");
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Get the thumbnail quality setting from config_data.
     */
    public function getThumbnailQualityAttribute(): ?int
    {
        return $this->config_data['exif']['thumbnail']['quality'] ?? null;
    }

    /**
     * Get the preview quality setting from config_data.
     */
    public function getPreviewQualityAttribute(): ?int
    {
        return $this->config_data['exif']['preview']['quality'] ?? null;
    }

    /**
     * Get the thumbnail dimensions from config_data.
     */
    public function getThumbnailDimensionsAttribute(): ?string
    {
        $width = $this->config_data['exif']['thumbnail']['width'] ?? null;
        $height = $this->config_data['exif']['thumbnail']['height'] ?? null;

        return $width && $height ? "{$width}x{$height}" : null;
    }

    /**
     * Get the preview max dimension from config_data.
     */
    public function getPreviewMaxDimensionAttribute(): ?int
    {
        return $this->config_data['exif']['preview']['max_dimension'] ?? null;
    }

    /**
     * Get the exiftool binary path from config_data.
     */
    public function getExiftoolBinaryAttribute(): ?string
    {
        return $this->config_data['exiftool']['binary'] ?? null;
    }

    /**
     * Get the exiftool speed mode from config_data.
     */
    public function getExiftoolSpeedModeAttribute(): ?string
    {
        return $this->config_data['exiftool']['speed_mode'] ?? null;
    }

    /**
     * Get the queue connection from queue_config.
     */
    public function getQueueConnectionAttribute(): ?string
    {
        return $this->queue_config['default'] ?? null;
    }

    /**
     * Get worker count from supervisor_config.
     */
    public function getWorkerCountAttribute(): ?int
    {
        $total = 0;
        if (is_array($this->supervisor_config)) {
            foreach ($this->supervisor_config as $program) {
                $total += $program['numprocs'] ?? 0;
            }
        }

        return $total > 0 ? $total : null;
    }

    /**
     * Get a summary of the configuration for display.
     */
    public function getSummary(): array
    {
        return [
            'thumbnail' => [
                'quality' => $this->thumbnail_quality,
                'dimensions' => $this->thumbnail_dimensions,
            ],
            'preview' => [
                'quality' => $this->preview_quality,
                'max_dimension' => $this->preview_max_dimension,
            ],
            'exiftool' => [
                'binary' => $this->exiftool_binary,
                'speed_mode' => $this->exiftool_speed_mode,
            ],
            'queue' => [
                'connection' => $this->queue_connection,
            ],
            'workers' => $this->worker_count,
        ];
    }

    /**
     * Compare this snapshot with another and return differences.
     */
    public function diff(ConfigSnapshot $other): array
    {
        $differences = [];

        // Compare config_data
        $differences['config_data'] = $this->arrayDiff(
            $this->config_data ?? [],
            $other->config_data ?? []
        );

        // Compare queue_config
        $differences['queue_config'] = $this->arrayDiff(
            $this->queue_config ?? [],
            $other->queue_config ?? []
        );

        // Compare supervisor_config
        $differences['supervisor_config'] = $this->arrayDiff(
            $this->supervisor_config ?? [],
            $other->supervisor_config ?? []
        );

        // Compare environment
        $differences['environment'] = $this->arrayDiff(
            $this->environment ?? [],
            $other->environment ?? []
        );

        return array_filter($differences);
    }

    /**
     * Recursively compare two arrays and return differences.
     */
    protected function arrayDiff(array $a, array $b): array
    {
        $diff = [];

        foreach ($a as $key => $value) {
            if (! array_key_exists($key, $b)) {
                $diff[$key] = ['current' => $value, 'other' => null];
            } elseif (is_array($value) && is_array($b[$key])) {
                $nested = $this->arrayDiff($value, $b[$key]);
                if (! empty($nested)) {
                    $diff[$key] = $nested;
                }
            } elseif ($value !== $b[$key]) {
                $diff[$key] = ['current' => $value, 'other' => $b[$key]];
            }
        }

        // Check for keys in $b that are not in $a
        foreach ($b as $key => $value) {
            if (! array_key_exists($key, $a)) {
                $diff[$key] = ['current' => null, 'other' => $value];
            }
        }

        return $diff;
    }
}
