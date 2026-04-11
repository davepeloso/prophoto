<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * UploadSession
 *
 * Represents a single batch upload initiated by a photographer.
 * Tracks the full lifecycle from metadata extraction through
 * calendar matching, file upload, tagging, and final confirmation.
 *
 * Story 1a.5 — Sprint 1
 */
class UploadSession extends Model
{
    protected $table = 'upload_sessions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'studio_id',
        'user_id',
        'calendar_event_id',
        'calendar_provider',
        'calendar_match_confidence',
        'calendar_match_evidence',
        'file_count',
        'completed_file_count',
        'total_size_bytes',
        'status',
        'gallery_id',
        'upload_started_at',
        'upload_completed_at',
        'confirmed_at',
    ];

    protected $casts = [
        'calendar_match_confidence' => 'float',
        'calendar_match_evidence'   => 'array',
        'file_count'                => 'integer',
        'completed_file_count'      => 'integer',
        'total_size_bytes'          => 'integer',
        'culled'                    => 'boolean',
        'upload_started_at'         => 'datetime',
        'upload_completed_at'       => 'datetime',
        'confirmed_at'              => 'datetime',
    ];

    // ─── Status Constants ─────────────────────────────────────────────────────

    public const STATUS_INITIATED  = 'initiated';
    public const STATUS_MATCHING   = 'matching';
    public const STATUS_UPLOADING  = 'uploading';
    public const STATUS_TAGGING    = 'tagging';
    public const STATUS_CONFIRMED  = 'confirmed';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_CANCELLED  = 'cancelled';

    // ─── Boot ─────────────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $session): void {
            if (empty($session->id)) {
                $session->id = (string) Str::uuid();
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function files(): HasMany
    {
        return $this->hasMany(IngestFile::class, 'upload_session_id');
    }

    public function completedFiles(): HasMany
    {
        return $this->hasMany(IngestFile::class, 'upload_session_id')
            ->where('upload_status', 'completed');
    }

    public function pendingFiles(): HasMany
    {
        return $this->hasMany(IngestFile::class, 'upload_session_id')
            ->where('upload_status', 'pending');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isUploading(): bool
    {
        return $this->status === self::STATUS_UPLOADING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function hasCalendarMatch(): bool
    {
        return ! empty($this->calendar_event_id);
    }

    // ─── Computed Attributes ──────────────────────────────────────────────────

    public function getPercentCompleteAttribute(): int
    {
        if ($this->file_count === 0) {
            return 0;
        }

        return (int) round(($this->completed_file_count / $this->file_count) * 100);
    }
}
