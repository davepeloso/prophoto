<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'image_id',
        'version_number',
        'imagekit_file_id',
        'imagekit_url',
        'file_size',
        'notes',
        'created_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size' => 'integer',
        'created_at' => 'datetime',
    ];

    /**
     * Get the image that owns this version.
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(Image::class);
    }

    /**
     * Get the user who created this version.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by_user_id');
    }
}
