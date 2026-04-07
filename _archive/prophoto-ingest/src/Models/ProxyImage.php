<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class ProxyImage extends Model
{
    protected $table = 'ingest_proxy_images';

    protected $fillable = [
        'uuid',
        'user_id',
        'filename',
        'temp_path',
        'thumbnail_path',
        'preview_path',
        'preview_width',
        'enhancement_status',
        'enhancement_requested_at',
        'preview_status',
        'preview_attempted_at',
        'preview_error',
        'is_culled',
        'is_starred',
        'rating',
        'rotation',
        'order_index',
        'metadata',
        'metadata_raw',
        'metadata_error',
        'extraction_method',
        'tags_json',
    ];

    protected $casts = [
        'is_culled' => 'boolean',
        'is_starred' => 'boolean',
        'rating' => 'integer',
        'rotation' => 'integer',
        'order_index' => 'integer',
        'preview_width' => 'integer',
        'metadata' => 'array',
        'metadata_raw' => 'array',
        'tags_json' => 'array',
        'preview_attempted_at' => 'datetime',
        'enhancement_requested_at' => 'datetime',
    ];

    /**
     * Scope to only include non-culled images
     */
    public function scopeNotCulled(Builder $query): Builder
    {
        return $query->where('is_culled', false);
    }

    /**
     * Scope to only include starred images
     */
    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    /**
     * Scope to filter by user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to find abandoned uploads (older than TTL)
     */
    public function scopeAbandoned(Builder $query): Builder
    {
        $ttl = config('ingest.cleanup.proxy_ttl_hours', 48);
        return $query->where('created_at', '<', now()->subHours($ttl));
    }

    /**
     * Check if the preview is ready to display
     */
    public function isPreviewReady(): bool
    {
        return $this->preview_status === 'ready' && $this->preview_path !== null;
    }

    /**
     * Check if the preview is still being processed
     */
    public function isPreviewPending(): bool
    {
        return in_array($this->preview_status, ['pending', 'processing']);
    }

    /**
     * Get denormalized EXIF data for fast filtering
     *
     * Uses normalized fields from ExifTool when available,
     * falling back to legacy raw EXIF keys.
     */
    public function getExifAttribute(): array
    {
        $metadata = $this->metadata ?? [];
        $denormalized = [];

        // Map of normalized keys (from ExifTool) to legacy keys
        $keyMapping = [
            'date_taken' => 'DateTimeOriginal',
            'camera_make' => 'Make',
            'camera_model' => 'Model',
            'f_stop' => 'FNumber',
            'iso' => 'ISOSpeedRatings',
            'shutter_speed' => 'ExposureTime',
            'focal_length' => 'FocalLength',
            'lens' => 'LensModel',
            'gps_lat' => 'GPSLatitude',
            'gps_lng' => 'GPSLongitude',
        ];

        foreach ($keyMapping as $normalizedKey => $legacyKey) {
            // Prefer normalized key from ExifTool
            if (isset($metadata[$normalizedKey])) {
                $denormalized[$normalizedKey] = $metadata[$normalizedKey];
            } elseif (isset($metadata[$legacyKey])) {
                // Fall back to legacy key
                $denormalized[$normalizedKey] = $metadata[$legacyKey];
            } else {
                $denormalized[$normalizedKey] = null;
            }
        }

        return $denormalized;
    }

    /**
     * Check if this proxy has metadata extraction errors
     */
    public function hasMetadataError(): bool
    {
        return !empty($this->metadata_error);
    }

    /**
     * Check if metadata was extracted using ExifTool
     */
    public function usedExifTool(): bool
    {
        return $this->extraction_method === 'exiftool';
    }

    /**
     * Get tags associated with this proxy image
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ingest_proxy_image_tag');
    }

    /**
     * Get project tag for this proxy image
     */
    public function getProjectTag(): ?Tag
    {
        return $this->tags()->project()->first();
    }

    /**
     * Get filename tag for this proxy image
     */
    public function getFilenameTag(): ?Tag
    {
        return $this->tags()->filename()->first();
    }

    /**
     * Convert to array format expected by React frontend
     */
    public function toReactArray(): array
    {
        $exif = $this->exif;

        // Use preview_path for display (falls back to temp_path for backwards compatibility)
        $displayPath = $this->preview_path ?? $this->temp_path;

        return [
            'id' => $this->uuid,
            'filename' => $this->filename,
            'thumbnail' => $this->thumbnail_path ? Storage::disk(config('ingest.storage.temp_disk'))->url($this->thumbnail_path) : null,
            'fullSize' => $displayPath ? Storage::disk(config('ingest.storage.temp_disk'))->url($displayPath) : null,
            'previewStatus' => $this->preview_status ?? 'pending',
            'previewReady' => $this->isPreviewReady(),
            'dateTaken' => $exif['date_taken'] ?? $this->created_at->toISOString(),
            'camera' => trim(($exif['camera_make'] ?? '') . ' ' . ($exif['camera_model'] ?? '')),
            'lens' => $exif['lens'] ?? 'Unknown',
            'aperture' => $this->parseAperture($exif['f_stop'] ?? null),
            'shutterSpeed' => $this->formatShutterSpeed($exif['shutter_speed'] ?? null),
            'iso' => (int) ($exif['iso'] ?? 0),
            'focalLength' => $this->parseFocalLength($exif['focal_length'] ?? null),
            'dimensions' => $this->metadata['ImageWidth'] ?? 0 . ' × ' . $this->metadata['ImageHeight'] ?? 0,
            'fileSize' => $this->formatFileSize($this->metadata['FileSize'] ?? 0),
            'fileType' => $this->getFileType(),
            'tags' => $this->tags()->get()->map(function($tag) {
                return [
                    'id' => $tag->id,
                    'name' => $tag->name,
                    'slug' => $tag->slug,
                    'color' => $tag->color,
                    'tag_type' => $tag->tag_type ?? 'normal',
                ];
            })->toArray(),
            'starred' => $this->is_starred,
            'culled' => $this->is_culled,
            'rating' => $this->rating ?? 0,
            'rotation' => $this->rotation ?? 0,
            'userOrder' => $this->order_index ?? 0,
            'gps' => $this->getGpsData($exif),
        ];
    }

    protected function parseAperture($value): float
    {
        if (is_numeric($value)) return (float) $value;
        if (is_string($value) && str_contains($value, '/')) {
            [$num, $den] = explode('/', $value);
            return $den > 0 ? round($num / $den, 1) : 0;
        }
        return 0;
    }

    protected function parseFocalLength($value): int
    {
        if (is_numeric($value)) return (int) $value;
        if (is_string($value)) {
            preg_match('/(\d+)/', $value, $matches);
            return (int) ($matches[1] ?? 0);
        }
        return 0;
    }

    protected function formatShutterSpeed($value): string
    {
        if (!$value) return 'Unknown';
        if (is_string($value) && str_contains($value, '/')) {
            return $value . 's';
        }
        if (is_numeric($value)) {
            if ($value >= 1) return $value . 's';
            return '1/' . round(1 / $value) . 's';
        }
        return (string) $value;
    }

    protected function formatFileSize($bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 1) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }

    protected function getFileType(): string
    {
        $ext = strtoupper(pathinfo($this->filename, PATHINFO_EXTENSION));
        $rawExtensions = ['CR2', 'CR3', 'NEF', 'ARW', 'RAF', 'DNG', 'ORF', 'RW2'];
        return in_array($ext, $rawExtensions) ? 'RAW' : $ext;
    }

    protected function getGpsData(array $exif): ?array
    {
        if (empty($exif['gps_lat']) || empty($exif['gps_lng'])) {
            return null;
        }

        return [
            'lat' => (float) $exif['gps_lat'],
            'lng' => (float) $exif['gps_lng'],
            'location' => 'GPS Location', // Could be reverse geocoded
        ];
    }
}
