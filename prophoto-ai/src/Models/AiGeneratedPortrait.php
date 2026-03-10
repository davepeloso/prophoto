<?php

namespace ProPhoto\Ai\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGeneratedPortrait extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'ai_generation_request_id',
        'imagekit_file_id',
        'imagekit_url',
        'imagekit_thumbnail_url',
        'file_size',
        'sort_order',
        'downloaded_by_subject',
        'created_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'sort_order' => 'integer',
        'downloaded_by_subject' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Get the generation request that owns this portrait.
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(AiGenerationRequest::class, 'ai_generation_request_id');
    }

    /**
     * Mark as downloaded by subject.
     */
    public function markAsDownloaded(): void
    {
        $this->update(['downloaded_by_subject' => true]);
    }
}
