<?php

namespace ProPhoto\Notifications\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Gallery\Models\Gallery;
use ProPhoto\Gallery\Models\Image;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'sender_user_id',
        'recipient_user_id',
        'gallery_id',
        'image_id',
        'subject',
        'body',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * Get the studio that owns this message.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the sender of this message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'sender_user_id');
    }

    /**
     * Get the recipient of this message.
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'recipient_user_id');
    }

    /**
     * Get the gallery this message is associated with.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the image this message is associated with.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    /**
     * Check if the message is read.
     */
    public function isRead(): bool
    {
        return !is_null($this->read_at);
    }

    /**
     * Mark the message as read.
     */
    public function markAsRead(): void
    {
        if (!$this->isRead()) {
            $this->update(['read_at' => now()]);
        }
    }

    /**
     * Mark the message as unread.
     */
    public function markAsUnread(): void
    {
        $this->update(['read_at' => null]);
    }
}
