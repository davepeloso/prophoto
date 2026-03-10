<?php

namespace ProPhoto\Access\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrganizationDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'uploaded_by_user_id',
        'title',
        'type',
        'description',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'expires_at',
        'requires_renewal',
        'reminded_at',
        'is_required',
        'client_visible',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'date',
        'reminded_at' => 'date',
        'requires_renewal' => 'boolean',
        'is_required' => 'boolean',
        'client_visible' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the organization that owns this document.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the user who uploaded this document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'uploaded_by_user_id');
    }

    /**
     * Get the download URL for this document.
     */
    public function getDownloadUrlAttribute(): string
    {
        // If using ImageKit or S3, return direct URL
        if (str_starts_with($this->file_path, 'http')) {
            return $this->file_path;
        }

        // If local storage, generate signed URL
        return Storage::temporaryUrl(
            $this->file_path,
            now()->addMinutes(30)
        );
    }

    /**
     * Check if the document is expired.
     */
    public function getIsExpiredAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the document is expiring soon (within 30 days).
     */
    public function getIsExpiringSoonAttribute(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return $this->expires_at->isBetween(now(), now()->addDays(30));
    }

    /**
     * Get human-readable file size.
     */
    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;

        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' bytes';
    }
}
