<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use ProPhoto\Gallery\Models\Gallery;

class GalleryShare extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'gallery_id',
        'shared_by_user_id',
        'shared_with_email',
        'shared_with_user_id',
        'share_token',
        'can_view',
        'can_download',
        'can_approve',
        'can_comment',
        'can_share',
        'expires_at',
        'accessed_at',
        'last_accessed_at',
        'access_count',
        'password_hash',
        'ip_whitelist',
        'max_downloads',
        'download_count',
        'revoked_at',
        'revoked_by_user_id',
        'message',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accessed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'ip_whitelist' => 'array',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
        'revoked_at' => 'datetime',
        'can_view' => 'boolean',
        'can_download' => 'boolean',
        'can_approve' => 'boolean',
        'can_comment' => 'boolean',
        'can_share' => 'boolean',
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->share_token)) {
                $model->share_token = Str::random(32);
            }
        });
    }

    /**
     * Get the gallery being shared.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the user who created the share link.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'shared_by_user_id');
    }

    /**
     * Get the access logs for this share.
     */
    public function accessLogs()
    {
        return $this->hasMany(GalleryAccessLog::class, 'resource_id')
            ->where('resource_type', 'share');
    }

    /**
     * Check if the share link is expired.
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if the share link has reached max views.
     */
    public function hasReachedMaxViews(): bool
    {
        if (!$this->max_downloads) {
            return false;
        }

        return $this->download_count >= $this->max_downloads;
    }

    /**
     * Check if the share link is valid.
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && $this->revoked_at === null;
    }

    /**
     * Increment the view count.
     */
    public function incrementViewCount(): void
    {
        if ($this->accessed_at === null) {
            $this->accessed_at = now();
        }

        $this->last_accessed_at = now();
        $this->access_count = (int) $this->access_count + 1;
        $this->save();
    }

    /**
     * Scope to only include active shares.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        })->whereNull('revoked_at');
    }
}
