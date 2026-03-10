<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ProPhoto\Interactions\Models\ImageInteraction;

class Image extends Model
{
    use SoftDeletes;

    protected ?array $assetRecordCache = null;
    protected bool $assetRecordLoaded = false;

    protected $fillable = [
        'gallery_id',
        'asset_id',
        'filename',
        'original_filename',
        'file_path',
        'thumbnail_path',
        'imagekit_file_id',
        'imagekit_url',
        'imagekit_thumbnail_url',
        'file_size',
        'mime_type',
        'hash',
        'width',
        'height',
        'metadata',
        'title',
        'caption',
        'alt_text',
        'is_featured',
        'is_client_favorite',
        'description',
        'is_marketing_approved',
        'ai_generated',
        'sort_order',
        'uploaded_at',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'asset_id' => 'integer',
        'metadata' => 'array',
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'is_featured' => 'boolean',
        'is_client_favorite' => 'boolean',
        'is_marketing_approved' => 'boolean',
        'ai_generated' => 'boolean',
        'uploaded_at' => 'datetime',
    ];

    /**
     * Get the gallery that owns this image.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the user who uploaded this image.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'uploaded_by_user_id');
    }

    /**
     * Get the versions of this image.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ImageVersion::class);
    }

    /**
     * Tags attached to this image.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ImageTag::class, 'image_tag')
            ->withTimestamps();
    }

    /**
     * Get the interactions for this image.
     */
    public function interactions(): HasMany
    {
        return $this->hasMany(ImageInteraction::class);
    }

    /**
     * Optional relation to canonical asset record.
     */
    public function asset(): BelongsTo
    {
        if (!class_exists(\ProPhoto\Assets\Models\Asset::class)) {
            return $this->belongsTo(self::class, 'asset_id')->whereRaw('1 = 0');
        }

        return $this->belongsTo(\ProPhoto\Assets\Models\Asset::class, 'asset_id');
    }

    /**
     * Resolve file path from asset spine when read switch is enabled.
     */
    public function getResolvedFilePathAttribute(): ?string
    {
        $asset = $this->getAssetRecord();
        if ($asset !== null) {
            return $asset['storage_key_original'] ?? null;
        }

        return $this->attributes['file_path'] ?? null;
    }

    /**
     * Resolve URL from asset spine when read switch is enabled.
     */
    public function getResolvedUrlAttribute(): ?string
    {
        $asset = $this->getAssetRecord();
        if ($asset !== null) {
            return $this->buildStorageUrl($asset['storage_driver'], $asset['storage_key_original']);
        }

        if (!empty($this->imagekit_url)) {
            return $this->imagekit_url;
        }

        $legacyPath = $this->attributes['file_path'] ?? null;
        if (is_string($legacyPath) && trim($legacyPath) !== '') {
            return asset('storage/' . ltrim($legacyPath, '/'));
        }

        return null;
    }

    /**
     * Resolve thumbnail URL from asset spine derivatives when available.
     */
    public function getResolvedThumbnailUrlAttribute(): ?string
    {
        $asset = $this->getAssetRecord();
        if ($asset !== null) {
            $thumbnailKey = $asset['thumbnail_key'] ?? null;
            if (is_string($thumbnailKey) && trim($thumbnailKey) !== '') {
                return $this->buildStorageUrl($asset['storage_driver'], $thumbnailKey);
            }
        }

        if (!empty($this->imagekit_thumbnail_url)) {
            return $this->imagekit_thumbnail_url;
        }

        $legacyThumbnailPath = $this->attributes['thumbnail_path'] ?? null;
        if (is_string($legacyThumbnailPath) && trim($legacyThumbnailPath) !== '') {
            return asset('storage/' . ltrim($legacyThumbnailPath, '/'));
        }

        return null;
    }

    /**
     * Resolve mime type from asset spine when read switch is enabled.
     */
    public function getResolvedMimeTypeAttribute(): ?string
    {
        $asset = $this->getAssetRecord();
        if ($asset !== null && !empty($asset['mime_type'])) {
            return (string) $asset['mime_type'];
        }

        return $this->mime_type;
    }

    /**
     * Resolve file size from asset spine when read switch is enabled.
     */
    public function getResolvedFileSizeAttribute(): ?int
    {
        $asset = $this->getAssetRecord();
        if ($asset !== null && isset($asset['bytes'])) {
            return (int) $asset['bytes'];
        }

        return $this->file_size;
    }

    /**
     * Get the latest version of this image.
     */
    public function latestVersion()
    {
        return $this->versions()->latest('version_number')->first();
    }

    /**
     * Get the average rating for this image.
     */
    public function getAverageRatingAttribute(): ?float
    {
        return $this->interactions()
            ->whereNotNull('rating')
            ->avg('rating');
    }

    /**
     * Check if this image is approved for marketing.
     */
    public function getIsApprovedAttribute(): bool
    {
        return $this->interactions()
            ->where('approved_for_marketing', true)
            ->exists();
    }

    /**
     * Check if this image has edit requests.
     */
    public function getHasEditRequestAttribute(): bool
    {
        return $this->interactions()
            ->where('edit_requested', true)
            ->exists();
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }

    /**
     * Get EXIF metadata value.
     */
    public function getExif(string $key, mixed $default = null): mixed
    {
        return data_get($this->metadata, $key, $default);
    }

    protected function getAssetRecord(): ?array
    {
        if ($this->assetRecordLoaded) {
            return $this->assetRecordCache;
        }

        $this->assetRecordLoaded = true;
        $this->assetRecordCache = null;

        if (!(bool) config('prophoto-gallery.asset_spine.read_switch', false)) {
            return null;
        }

        if (empty($this->asset_id)) {
            return null;
        }

        $assetsTable = (string) config('prophoto-assets.tables.assets', 'assets');
        $derivativesTable = (string) config('prophoto-assets.tables.asset_derivatives', 'asset_derivatives');

        try {
            $assetRow = DB::table($assetsTable)
                ->where('id', (int) $this->asset_id)
                ->first(['id', 'storage_driver', 'storage_key_original', 'mime_type', 'bytes']);
        } catch (\Throwable) {
            return null;
        }

        if ($assetRow === null) {
            return null;
        }

        $thumbnailKey = null;
        try {
            $thumbnailKey = DB::table($derivativesTable)
                ->where('asset_id', (int) $this->asset_id)
                ->where('type', 'preview')
                ->value('storage_key');

            if ($thumbnailKey === null) {
                $thumbnailKey = DB::table($derivativesTable)
                    ->where('asset_id', (int) $this->asset_id)
                    ->where('type', 'thumbnail')
                    ->value('storage_key');
            }
        } catch (\Throwable) {
            // Derivatives table is optional during rollout.
        }

        $this->assetRecordCache = [
            'id' => (int) $assetRow->id,
            'storage_driver' => (string) ($assetRow->storage_driver ?? config('filesystems.default', 'local')),
            'storage_key_original' => (string) $assetRow->storage_key_original,
            'mime_type' => $assetRow->mime_type,
            'bytes' => $assetRow->bytes,
            'thumbnail_key' => $thumbnailKey,
        ];

        return $this->assetRecordCache;
    }

    protected function buildStorageUrl(?string $disk, ?string $storageKey): ?string
    {
        if ($storageKey === null || trim($storageKey) === '') {
            return null;
        }

        try {
            return Storage::disk($disk ?: config('filesystems.default', 'local'))->url($storageKey);
        } catch (\Throwable) {
            return null;
        }
    }
}
