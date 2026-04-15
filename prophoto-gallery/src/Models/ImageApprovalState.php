<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-image, per-share approval state.
 *
 * Each share-token holder gets an independent approval state for every image
 * in a proofing gallery. When gallery_share_id is null, the action was taken
 * by the studio photographer directly.
 *
 * Status vocabulary:
 *   unapproved      — default, no action taken
 *   approved         — client has approved the image
 *   approved_pending — approved with a pending request (retouch, color correction, etc.)
 *   cleared          — explicitly cleared / revoked previous approval
 *
 * @property int         $id
 * @property int         $gallery_id
 * @property int         $image_id
 * @property int|null    $gallery_share_id
 * @property string      $status
 * @property int|null    $pending_type_id
 * @property string|null $pending_note
 * @property string|null $actor_email
 * @property \Carbon\Carbon $set_at
 */
class ImageApprovalState extends Model
{
    public const STATUS_UNAPPROVED      = 'unapproved';
    public const STATUS_APPROVED         = 'approved';
    public const STATUS_APPROVED_PENDING = 'approved_pending';
    public const STATUS_CLEARED          = 'cleared';

    protected $table = 'image_approval_states';

    protected $fillable = [
        'gallery_id',
        'image_id',
        'gallery_share_id',
        'status',
        'pending_type_id',
        'pending_note',
        'actor_email',
        'set_at',
    ];

    protected $casts = [
        'set_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    public function share(): BelongsTo
    {
        return $this->belongsTo(GalleryShare::class, 'gallery_share_id');
    }

    public function pendingType(): BelongsTo
    {
        return $this->belongsTo(GalleryPendingType::class, 'pending_type_id');
    }

    // ── Status helpers ────────────────────────────────────────────────────

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED
            || $this->status === self::STATUS_APPROVED_PENDING;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_APPROVED_PENDING;
    }

    // ── Scopes ────────────────────────────────────────────────────────────

    public function scopeForShare($query, int $shareId)
    {
        return $query->where('gallery_share_id', $shareId);
    }

    public function scopeForImage($query, int $imageId)
    {
        return $query->where('image_id', $imageId);
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_APPROVED_PENDING]);
    }
}
