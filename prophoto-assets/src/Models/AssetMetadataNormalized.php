<?php

namespace ProPhoto\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMetadataNormalized extends Model
{
    protected $table = 'asset_metadata_normalized';

    protected $fillable = [
        'asset_id',
        'schema_version',
        'media_kind',
        'normalized_at',
        'captured_at',
        'payload',
        'camera_make',
        'camera_model',
        'mime_type',
        'file_size',
        'lens',
        'color_profile',
        'rating',
        'page_count',
        'duration_seconds',
        'has_gps',
        'iso',
        'width',
        'height',
        'exif_orientation',
        'metadata',
    ];

    protected $casts = [
        'normalized_at' => 'datetime',
        'captured_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
        'file_size' => 'integer',
        'rating' => 'integer',
        'page_count' => 'integer',
        'duration_seconds' => 'float',
        'has_gps' => 'boolean',
        'iso' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'exif_orientation' => 'integer',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
