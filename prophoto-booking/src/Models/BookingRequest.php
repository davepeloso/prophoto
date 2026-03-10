<?php

namespace ProPhoto\Booking\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Access\Models\Organization;

class BookingRequest extends Model
{
    protected $fillable = [
        'studio_id',
        'organization_id',
        'client_user_id',
        'subject_name',
        'session_type',
        'requested_datetime',
        'duration_minutes',
        'location',
        'notes',
        'status',
        'session_id',
        'google_event_id',
        'denial_reason',
        'confirmed_at',
        'confirmed_by_user_id',
    ];

    protected $casts = [
        'requested_datetime' => 'datetime',
        'confirmed_at' => 'datetime',
        'duration_minutes' => 'integer',
    ];

    /**
     * Booking request status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_DENIED = 'denied';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the studio that owns this booking request.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the organization that owns this booking request.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the client user who made this booking request.
     */
    public function clientUser(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'client_user_id');
    }

    /**
     * Get the session created from this booking request.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the user who confirmed this booking request.
     */
    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'confirmed_by_user_id');
    }

    /**
     * Check if the booking request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the booking request is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Check if the booking request is denied.
     */
    public function isDenied(): bool
    {
        return $this->status === self::STATUS_DENIED;
    }
}
