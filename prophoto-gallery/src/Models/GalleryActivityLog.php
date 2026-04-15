<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Story 3.5 — Read-only Eloquent model for the gallery_activity_log table.
 *
 * The single write path remains GalleryActivityLogger::log().
 * This model exists for Filament relation managers and queries.
 *
 * Append-only: no updated_at column.
 */
class GalleryActivityLog extends Model
{
    protected $table = 'gallery_activity_log';

    public $timestamps = false;

    protected $casts = [
        'metadata'    => 'array',
        'occurred_at' => 'datetime',
        'created_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(GalleryShare::class, 'gallery_share_id');
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }
}
