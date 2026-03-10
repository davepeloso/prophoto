<?php

namespace ProPhoto\Ingest\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Tag extends Model
{
    const TYPE_NORMAL = 'normal';
    const TYPE_PROJECT = 'project';
    const TYPE_FILENAME = 'filename';

    protected $table = 'ingest_tags';

    protected $fillable = [
        'name',
        'slug',
        'color',
        'tag_type',
    ];

    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (Tag $tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    /**
     * Get images with this tag
     */
    public function images(): BelongsToMany
    {
        return $this->belongsToMany(Image::class, 'ingest_image_tag');
    }

    /**
     * Get proxy images with this tag
     */
    public function proxyImages(): BelongsToMany
    {
        return $this->belongsToMany(ProxyImage::class, 'ingest_proxy_image_tag');
    }

    /**
     * Scope to filter by tag type
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('tag_type', $type);
    }

    /**
     * Scope to get only project tags
     */
    public function scopeProject(Builder $query): Builder
    {
        return $query->where('tag_type', self::TYPE_PROJECT);
    }

    /**
     * Scope to get only filename tags
     */
    public function scopeFilename(Builder $query): Builder
    {
        return $query->where('tag_type', self::TYPE_FILENAME);
    }

    /**
     * Scope to get only normal tags
     */
    public function scopeNormal(Builder $query): Builder
    {
        return $query->where('tag_type', self::TYPE_NORMAL);
    }

    /**
     * Check if this is a project tag
     */
    public function isProject(): bool
    {
        return $this->tag_type === self::TYPE_PROJECT;
    }

    /**
     * Check if this is a filename tag
     */
    public function isFilename(): bool
    {
        return $this->tag_type === self::TYPE_FILENAME;
    }

    /**
     * Check if this is a normal tag
     */
    public function isNormal(): bool
    {
        return $this->tag_type === self::TYPE_NORMAL;
    }

    /**
     * Find or create a tag by name
     */
    public static function findOrCreateByName(string $name, string $type = self::TYPE_NORMAL): self
    {
        return static::firstOrCreate(
            ['slug' => Str::slug($name), 'tag_type' => $type],
            ['name' => $name, 'tag_type' => $type]
        );
    }
}
