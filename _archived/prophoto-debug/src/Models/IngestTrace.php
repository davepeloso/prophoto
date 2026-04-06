<?php

namespace ProPhoto\Debug\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IngestTrace extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'debug_ingest_traces';

    /**
     * Disable updated_at since we only have created_at.
     */
    public const UPDATED_AT = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'uuid',
        'session_id',
        'trace_type',
        'method_tried',
        'method_order',
        'success',
        'failure_reason',
        'result_info',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'method_order' => 'integer',
        'success' => 'boolean',
        'result_info' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Trace type constants.
     */
    public const TYPE_PREVIEW_EXTRACTION = 'preview_extraction';
    public const TYPE_METADATA_EXTRACTION = 'metadata_extraction';
    public const TYPE_THUMBNAIL_GENERATION = 'thumbnail_generation';
    public const TYPE_ENHANCEMENT = 'enhancement';

    /**
     * Get all available trace types.
     */
    public static function traceTypes(): array
    {
        return [
            self::TYPE_PREVIEW_EXTRACTION,
            self::TYPE_METADATA_EXTRACTION,
            self::TYPE_THUMBNAIL_GENERATION,
            self::TYPE_ENHANCEMENT,
        ];
    }

    /**
     * Scope to filter by UUID.
     */
    public function scopeForUuid(Builder $query, string $uuid): Builder
    {
        return $query->where('uuid', $uuid);
    }

    /**
     * Scope to filter by session ID.
     */
    public function scopeForSession(Builder $query, string $sessionId): Builder
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to filter by trace type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('trace_type', $type);
    }

    /**
     * Scope to filter only successful traces.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('success', true);
    }

    /**
     * Scope to filter only failed traces.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('success', false);
    }

    /**
     * Scope to filter traces older than retention period.
     */
    public function scopeExpired(Builder $query, ?int $days = null): Builder
    {
        $retentionDays = $days ?? config('debug.retention_days', 7);

        return $query->where('created_at', '<', now()->subDays($retentionDays));
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange(Builder $query, $start, $end): Builder
    {
        return $query->whereBetween('created_at', [$start, $end]);
    }

    /**
     * Get the duration in milliseconds from result_info.
     */
    public function getDurationMsAttribute(): ?int
    {
        return $this->result_info['duration_ms'] ?? null;
    }

    /**
     * Get the file size from result_info.
     */
    public function getSizeAttribute(): ?int
    {
        return $this->result_info['size'] ?? null;
    }

    /**
     * Get the dimensions from result_info.
     */
    public function getDimensionsAttribute(): ?string
    {
        return $this->result_info['dimensions'] ?? null;
    }

    /**
     * Check if this trace represents a successful operation.
     */
    public function isSuccess(): bool
    {
        return $this->success === true;
    }

    /**
     * Check if this trace represents a failed operation.
     */
    public function isFailed(): bool
    {
        return $this->success === false;
    }

    /**
     * Get a human-readable label for the trace type.
     */
    public function getTraceTypeLabel(): string
    {
        return match ($this->trace_type) {
            self::TYPE_PREVIEW_EXTRACTION => 'Preview Extraction',
            self::TYPE_METADATA_EXTRACTION => 'Metadata Extraction',
            self::TYPE_THUMBNAIL_GENERATION => 'Thumbnail Generation',
            self::TYPE_ENHANCEMENT => 'Enhancement',
            default => ucfirst(str_replace('_', ' ', $this->trace_type)),
        };
    }
}
