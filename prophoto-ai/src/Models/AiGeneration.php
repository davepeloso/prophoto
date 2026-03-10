<?php

namespace ProPhoto\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ProPhoto\Gallery\Models\Gallery;

class AiGeneration extends Model
{
    protected $fillable = [
        'gallery_id',
        'subject_user_id',
        'fine_tune_id',
        'training_image_count',
        'model_status',
        'fine_tune_cost',
        'model_created_at',
        'model_expires_at',
        'error_message',
    ];

    protected $casts = [
        'training_image_count' => 'integer',
        'fine_tune_cost' => 'decimal:2',
        'model_created_at' => 'datetime',
        'model_expires_at' => 'datetime',
    ];

    /**
     * Model status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_TRAINING = 'training';
    public const STATUS_TRAINED = 'trained';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';

    /**
     * Get the gallery that owns this AI generation.
     */
    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    /**
     * Get the subject user for this AI generation.
     */
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'subject_user_id');
    }

    /**
     * Get the generation requests for this AI generation.
     */
    public function requests(): HasMany
    {
        return $this->hasMany(AiGenerationRequest::class);
    }

    /**
     * Check if the model is trained and ready.
     */
    public function isReady(): bool
    {
        return $this->model_status === self::STATUS_TRAINED;
    }

    /**
     * Check if the model has expired.
     */
    public function isExpired(): bool
    {
        return $this->model_status === self::STATUS_EXPIRED
            || ($this->model_expires_at && $this->model_expires_at->isPast());
    }

    /**
     * Get remaining generations count (max 5).
     */
    public function getRemainingGenerationsAttribute(): int
    {
        return max(0, 5 - $this->requests()->where('status', 'completed')->count());
    }

    /**
     * Get total cost of all generations.
     */
    public function getTotalCostAttribute(): float
    {
        return $this->fine_tune_cost + $this->requests()->sum('generation_cost');
    }
}
