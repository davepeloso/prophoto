<?php

namespace ProPhoto\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiGenerationRequest extends Model
{
    protected $fillable = [
        'ai_generation_id',
        'request_number',
        'custom_prompt',
        'used_default_prompt',
        'generated_portrait_count',
        'generation_cost',
        'background_removal',
        'super_resolution',
        'status',
        'error_message',
        'liability_accepted_at',
        'requested_by_user_id',
    ];

    protected $casts = [
        'request_number' => 'integer',
        'used_default_prompt' => 'boolean',
        'generated_portrait_count' => 'integer',
        'generation_cost' => 'decimal:2',
        'background_removal' => 'boolean',
        'super_resolution' => 'boolean',
        'liability_accepted_at' => 'datetime',
    ];

    /**
     * Request status constants.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    /**
     * Get the AI generation that owns this request.
     */
    public function aiGeneration(): BelongsTo
    {
        return $this->belongsTo(AiGeneration::class);
    }

    /**
     * Get the user who made this request.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'requested_by_user_id');
    }

    /**
     * Get the generated portraits for this request.
     */
    public function portraits(): HasMany
    {
        return $this->hasMany(AiGeneratedPortrait::class);
    }

    /**
     * Check if the request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the request is processing.
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Calculate the generation cost based on options.
     */
    public function calculateCost(): float
    {
        $baseCost = 0.23;

        if ($this->background_removal) {
            $baseCost += 0.08;
        }

        if ($this->super_resolution) {
            $baseCost += 0.10;
        }

        return $baseCost;
    }
}
