<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Gallery\Models\Gallery;

class GalleryAccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'gallery_id',
        'user_id',
        'action',
        'resource_type',
        'resource_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Indicates if the model should be timestamped.
     * We only need created_at for logs.
     */
    public $timestamps = true;

    const UPDATED_AT = null;

    /**
     * Get the gallery that was accessed.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the user who accessed the gallery.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the share link used (if any).
     */
    public function share(): BelongsTo
    {
        return $this->belongsTo(GalleryShare::class, 'resource_id')
            ->where('resource_type', 'share');
    }

    /**
     * Scope to filter by action type.
     */
    public function scopeAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Scope to group by action for analytics.
     */
    public function scopeActionStats($query)
    {
        return $query->selectRaw('action, COUNT(*) as count')
            ->groupBy('action');
    }

    /**
     * Common action types.
     */
    const ACTION_VIEW = 'view';
    const ACTION_DOWNLOAD = 'download';
    const ACTION_SHARE = 'share';
    const ACTION_COMMENT = 'comment';
    const ACTION_RATE = 'rate';
}
