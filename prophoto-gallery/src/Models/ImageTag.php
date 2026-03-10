<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use ProPhoto\Gallery\Models\Image;

class ImageTag extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'user_id',
        'color',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->slug)) {
                $model->slug = Str::slug($model->name);
            }
        });
    }

    /**
     * Get the user who created the tag.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'));
    }

    /**
     * Get the images with this tag.
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'image_tag')
            ->withTimestamps();
    }

    /**
     * Scope to only include system tags.
     */
    public function scopeSystem($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope to only include user-created tags.
     */
    public function scopeUserCreated($query)
    {
        return $query->where('is_system', false);
    }

    /**
     * Scope to tags created by a specific user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get the count of images with this tag.
     */
    public function getImageCountAttribute(): int
    {
        return $this->images()->count();
    }
}
