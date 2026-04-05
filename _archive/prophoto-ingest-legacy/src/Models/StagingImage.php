<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Gallery\Models\Gallery;

class StagingImage extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'batch_id',
        'filename',
        'original_path',
        'thumbnail_path',
        'file_size',
        'mime_type',
        'metadata',
        'assigned_to_gallery_id',
        'assigned_at',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
        'assigned_at' => 'datetime',
    ];

    /**
     * Get the studio that owns this staging image.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the gallery this image is assigned to.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class, 'assigned_to_gallery_id');
    }

    /**
     * Get the user who uploaded this image.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'uploaded_by_user_id');
    }

    /**
     * Check if the image is assigned to a gallery.
     */
    public function isAssigned(): bool
    {
        return !is_null($this->assigned_to_gallery_id);
    }

    /**
     * Get EXIF metadata value.
     */
    public function getExif(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }
}
