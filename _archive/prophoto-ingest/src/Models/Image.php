<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class Image extends Model
{
    protected $table = 'ingest_images';

    protected $fillable = [
        'file_name',
        'file_path',
        'disk',
        'size',
        'alt_text',
        'date_taken',
        'camera_make',
        'camera_model',
        'lens',
        'f_stop',
        'iso',
        'shutter_speed',
        'focal_length',
        'gps_lat',
        'gps_lng',
        'raw_metadata',
        'imageable_id',
        'imageable_type',
    ];

    protected $casts = [
        'date_taken' => 'datetime',
        'f_stop' => 'decimal:2',
        'iso' => 'integer',
        'focal_length' => 'integer',
        'size' => 'integer',
        'gps_lat' => 'decimal:8',
        'gps_lng' => 'decimal:8',
        'raw_metadata' => 'array',
    ];

    /**
     * Get the parent imageable model (Shoot, Project, etc.)
     */
    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get tags associated with this image
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'ingest_image_tag');
    }

    /**
     * Get the full URL to the image
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->file_path);
    }

    /**
     * Get the camera display name
     */
    public function getCameraAttribute(): string
    {
        return trim($this->camera_make . ' ' . $this->camera_model);
    }

    /**
     * Get formatted shutter speed for display
     */
    public function getShutterSpeedDisplayAttribute(): string
    {
        $speed = $this->shutter_speed;
        if (!$speed) return 'N/A';

        if ($speed >= 1) {
            return $speed . 's';
        }

        return '1/' . round(1 / $speed) . 's';
    }

    /**
     * Scope by camera make
     */
    public function scopeByCamera($query, string $make)
    {
        return $query->where('camera_make', $make);
    }

    /**
     * Scope by date range
     */
    public function scopeBetweenDates($query, $start, $end)
    {
        return $query->whereBetween('date_taken', [$start, $end]);
    }

    /**
     * Scope by aperture range
     */
    public function scopeApertureRange($query, float $min, float $max)
    {
        return $query->whereBetween('f_stop', [$min, $max]);
    }

    /**
     * Scope by ISO range
     */
    public function scopeIsoRange($query, int $min, int $max)
    {
        return $query->whereBetween('iso', [$min, $max]);
    }
}
