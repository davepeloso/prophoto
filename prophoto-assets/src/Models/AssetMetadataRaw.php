<?php

namespace ProPhoto\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMetadataRaw extends Model
{
    protected $table = 'asset_metadata_raw';

    protected $fillable = [
        'asset_id',
        'source',
        'tool_version',
        'extracted_at',
        'payload',
        'payload_hash',
        'metadata',
    ];

    protected $casts = [
        'extracted_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
