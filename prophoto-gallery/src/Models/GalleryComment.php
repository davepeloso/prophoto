<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use ProPhoto\Access\Models\User;

class GalleryComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'commentable_type',
        'commentable_id',
        'user_id',
        'parent_id',
        'comment',
        'is_internal',
        'is_resolved',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'is_resolved' => 'boolean',
    ];

    /**
     * Get the commentable entity (Gallery or Image).
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who created the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent comment (for threaded replies).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(GalleryComment::class, 'parent_id');
    }

    /**
     * Get the child comments (replies).
     */
    public function replies()
    {
        return $this->hasMany(GalleryComment::class, 'parent_id');
    }

    /**
     * Scope to only include internal comments.
     */
    public function scopeInternal($query)
    {
        return $query->where('is_internal', true);
    }

    /**
     * Scope to only include public comments.
     */
    public function scopePublic($query)
    {
        return $query->where('is_internal', false);
    }

    /**
     * Scope to only include unresolved comments.
     */
    public function scopeUnresolved($query)
    {
        return $query->where('is_resolved', false);
    }

    /**
     * Scope to only include top-level comments (no parent).
     */
    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }
}
