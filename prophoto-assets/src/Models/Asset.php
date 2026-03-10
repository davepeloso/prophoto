<?php

namespace ProPhoto\Assets\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';

    protected $fillable = [
        'studio_id',
        'organization_id',
        'type',
        'original_filename',
        'mime_type',
        'bytes',
        'checksum_sha256',
        'storage_driver',
        'storage_key_original',
        'logical_path',
        'status',
        'captured_at',
        'ingested_at',
        'metadata',
    ];

    protected $casts = [
        'bytes' => 'integer',
        'captured_at' => 'datetime',
        'ingested_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function derivatives(): HasMany
    {
        return $this->hasMany(AssetDerivative::class);
    }

    public function rawMetadata(): HasMany
    {
        return $this->hasMany(AssetMetadataRaw::class);
    }

    public function normalizedMetadata(): HasMany
    {
        return $this->hasMany(AssetMetadataNormalized::class);
    }
}
