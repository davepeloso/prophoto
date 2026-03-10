<?php

namespace ProPhoto\Assets\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDerivative extends Model
{
    protected $table = 'asset_derivatives';

    protected $fillable = [
        'asset_id',
        'type',
        'storage_key',
        'mime_type',
        'bytes',
        'width',
        'height',
        'metadata',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'metadata' => 'array',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
