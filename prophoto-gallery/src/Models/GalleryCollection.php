<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Gallery\Models\Gallery;

class GalleryCollection extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'created_by_user_id',
        'organization_id',
        'studio_id',
        'cover_image_id',
        'is_public',
        'sort_order',
        'settings',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'sort_order' => 'integer',
        'settings' => 'array',
    ];

    /**
     * Get the user that owns the collection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by_user_id');
    }

    /**
     * Get the organization that owns the collection.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the galleries in this collection.
     */
    public function galleries(): BelongsToMany
    {
        return $this->belongsToMany(Gallery::class, 'collection_gallery')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('collection_gallery.sort_order');
    }

    /**
     * Scope to only include public collections.
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    /**
     * Scope to collections owned by a specific organization.
     */
    public function scopeForOrganization($query, int $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }
}
