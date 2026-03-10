<?php

namespace ProPhoto\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Gallery\Models\Gallery;

class Session extends Model
{
    use SoftDeletes;

    protected $table = 'photo_sessions';

    protected $fillable = [
        'studio_id',
        'organization_id',
        'subject_name',
        'session_type',
        'scheduled_at',
        'completed_at',
        'location',
        'status',
        'google_event_id',
        'rate',
        'notes',
        'created_by_user_id',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'rate' => 'decimal:2',
    ];

    /**
     * Session status constants.
     */
    public const STATUS_TENTATIVE = 'tentative';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the studio that owns this session.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the organization that owns this session.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who created this session.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by_user_id');
    }

    /**
     * Get the gallery associated with this session.
     */
    public function gallery(): HasOne
    {
        return $this->hasOne(Gallery::class);
    }

    /**
     * Check if the session is scheduled.
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Check if the session is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the session is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }
}
