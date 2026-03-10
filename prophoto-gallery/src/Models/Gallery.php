<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use ProPhoto\Access\Models\Studio;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Booking\Models\Session;
use ProPhoto\Ai\Models\AiGeneration;

class Gallery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'studio_id',
        'organization_id',
        'session_id',
        'subject_name',
        'access_code',
        'magic_link_token',
        'magic_link_expires_at',
        'status',
        'ai_enabled',
        'ai_training_status',
        'image_count',
        'approved_count',
        'download_count',
        'last_activity_at',
        'delivered_at',
        'completed_at',
        'archived_at',
    ];

    protected $casts = [
        'magic_link_expires_at' => 'datetime',
        'ai_enabled' => 'boolean',
        'image_count' => 'integer',
        'approved_count' => 'integer',
        'download_count' => 'integer',
        'last_activity_at' => 'datetime',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    /**
     * Gallery status constants.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    /**
     * AI training status constants.
     */
    public const AI_STATUS_READY = 'ready';
    public const AI_STATUS_TRAINING = 'training';
    public const AI_STATUS_TRAINED = 'trained';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($gallery) {
            if (empty($gallery->access_code)) {
                $gallery->access_code = static::generateAccessCode($gallery->subject_name);
            }
            if (empty($gallery->magic_link_token)) {
                $gallery->magic_link_token = Str::random(64);
            }
            if (empty($gallery->magic_link_expires_at)) {
                $gallery->magic_link_expires_at = now()->addDays(30);
            }
        });
    }

    /**
     * Generate a unique access code.
     */
    public static function generateAccessCode(string $subjectName): string
    {
        $prefix = strtoupper(Str::slug(Str::words($subjectName, 2, ''), '-'));
        $year = date('Y');
        $random = strtoupper(Str::random(4));

        return "{$prefix}-{$year}-{$random}";
    }

    /**
     * Get the studio that owns this gallery.
     */
    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    /**
     * Get the organization that owns this gallery.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the session associated with this gallery.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(Session::class);
    }

    /**
     * Get the images in this gallery.
     */
    public function images(): HasMany
    {
        return $this->hasMany(Image::class);
    }

    /**
     * Get the AI generation for this gallery.
     */
    public function aiGeneration(): HasOne
    {
        return $this->hasOne(AiGeneration::class);
    }

    /**
     * Check if the gallery is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if the gallery is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the gallery is archived.
     */
    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Check if AI is enabled and model is trained.
     */
    public function canGenerateAiPortraits(): bool
    {
        return $this->ai_enabled && $this->ai_training_status === self::AI_STATUS_TRAINED;
    }

    /**
     * Update cached counts.
     */
    public function updateCounts(): void
    {
        $this->update([
            'image_count' => $this->images()->count(),
            'approved_count' => $this->images()
                ->whereHas('interactions', fn($q) => $q->where('approved_for_marketing', true))
                ->count(),
        ]);
    }

    /**
     * Record activity timestamp.
     */
    public function recordActivity(): void
    {
        $this->update(['last_activity_at' => now()]);
    }

    /**
     * Generate a fresh magic link token.
     */
    public function refreshMagicLink(int $expiresInDays = 30): void
    {
        $this->update([
            'magic_link_token' => Str::random(64),
            'magic_link_expires_at' => now()->addDays($expiresInDays),
        ]);
    }
}
