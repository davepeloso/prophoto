<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class GalleryTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'studio_id',
        'name',
        'description',
        'default_settings',
        'watermark_settings',
        'download_settings',
        'guest_permissions',
        'template_type',
        'is_default',
        'usage_count',
        'last_used_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'default_settings' => 'array',
        'watermark_settings' => 'array',
        'download_settings' => 'array',
        'guest_permissions' => 'array',
        'is_default' => 'boolean',
        'usage_count' => 'integer',
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user who created the template.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by_user_id');
    }

    /**
     * Scope to only include global templates.
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to only include user-specific templates.
     */
    public function scopeUserOnly($query)
    {
        return $query->where('is_default', false);
    }

    /**
     * Scope to templates accessible by a specific user.
     */
    public function scopeAccessibleBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('is_default', true)
                ->orWhere('created_by_user_id', $userId);
        });
    }

    /**
     * Apply this template's settings to a gallery.
     */
    public function applyToGallery($gallery): void
    {
        if ($this->default_settings && is_array($this->default_settings)) {
            $gallery->update([
                'settings' => array_merge($gallery->settings ?? [], $this->default_settings)
            ]);
        }
    }
}
