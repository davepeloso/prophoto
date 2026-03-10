<?php

namespace ProPhoto\Interactions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Gallery\Models\Image;

class ImageInteraction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'image_id',
        'user_id',
        'interaction_type',
        'rating',
        'note',
        'approved_for_marketing',
        'edit_requested',
        'edit_notes',
        'downloaded_at',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'approved_for_marketing' => 'boolean',
        'edit_requested' => 'boolean',
        'downloaded_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Interaction type constants.
     */
    public const TYPE_RATING = 'rating';
    public const TYPE_NOTE = 'note';
    public const TYPE_APPROVAL = 'approval';
    public const TYPE_DOWNLOAD = 'download';
    public const TYPE_EDIT_REQUEST = 'edit_request';

    /**
     * Get the image that owns this interaction.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    /**
     * Get the user who made this interaction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }
}
