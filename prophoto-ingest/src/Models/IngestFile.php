<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * IngestFile
 *
 * Represents a single file within an UploadSession.
 * Tracks per-file upload status, EXIF data, and cull decisions.
 *
 * Story 1a.5 — Sprint 1
 */
class IngestFile extends Model
{
    protected $table = 'ingest_files';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'upload_session_id',
        'asset_id',
        'original_filename',
        'file_size_bytes',
        'file_type',
        'mime_type',
        'exif_data',
        'upload_status',
        'uploaded_at',
        'culled',
        'is_culled',      // batch update alias — maps to 'culled' via setAttribute
        'storage_path',
        'rating',
    ];

    protected $casts = [
        'exif_data'       => 'array',
        'file_size_bytes' => 'integer',
        'culled'          => 'boolean',
        'rating'          => 'integer',
        'uploaded_at'     => 'datetime',
    ];

    public const STATUS_PENDING   = 'pending';
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $file): void {
            if (empty($file->id)) {
                $file->id = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function session(): BelongsTo
    {
        return $this->belongsTo(UploadSession::class, 'upload_session_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(IngestImageTag::class, 'ingest_file_id');
    }

    // ─── Attribute Aliases ────────────────────────────────────────────────────

    /**
     * Allow batch updates to pass 'is_culled' which maps to the 'culled' column.
     */
    public function setIsCulledAttribute(bool $value): void
    {
        $this->attributes['culled'] = $value;
    }

    /**
     * Convenience accessor: returns original_filename as 'filename'.
     */
    public function getFilenameAttribute(): string
    {
        return $this->original_filename;
    }

    /**
     * Allow setting 'filename' as an alias for 'original_filename'.
     */
    public function setFilenameAttribute(string $value): void
    {
        $this->attributes['original_filename'] = $value;
    }

    // ─── EXIF Accessors ───────────────────────────────────────────────────────

    public function getIsoAttribute(): ?int
    {
        return $this->exif_data['iso'] ?? null;
    }

    public function getApertureAttribute(): ?float
    {
        return $this->exif_data['aperture'] ?? null;
    }

    public function getShutterAttribute(): ?string
    {
        return $this->exif_data['shutter'] ?? null;
    }

    public function getFocalLengthAttribute(): ?int
    {
        return $this->exif_data['focalLength'] ?? null;
    }

    public function getCameraAttribute(): ?string
    {
        return $this->exif_data['camera'] ?? null;
    }

    public function getCaptureTimestampAttribute(): ?string
    {
        return $this->exif_data['timestamp'] ?? null;
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isUploaded(): bool
    {
        return $this->upload_status === self::STATUS_COMPLETED;
    }

    public function isCulled(): bool
    {
        return $this->culled === true;
    }
}
