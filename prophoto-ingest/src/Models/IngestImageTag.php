<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * IngestImageTag
 *
 * A single tag applied to an IngestFile during the ingest workflow.
 * Tags can be derived from EXIF metadata, calendar context, or entered
 * manually by the photographer.
 *
 * Story 1a.5 — Sprint 1
 */
class IngestImageTag extends Model
{
    protected $table = 'ingest_image_tags';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ingest_file_id',
        'tag',
        'tag_type',
    ];

    public const TYPE_METADATA = 'metadata';
    public const TYPE_CALENDAR = 'calendar';
    public const TYPE_USER     = 'user';

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $tag): void {
            if (empty($tag->id)) {
                $tag->id = (string) Str::uuid();
            }
            $tag->created_at = now();
        });
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(IngestFile::class, 'ingest_file_id');
    }
}
