<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-gallery pending type menu item.
 *
 * Created automatically from studio_pending_type_templates when a gallery
 * is created (see GalleryObserver or GalleryService). Each item can be
 * toggled, reordered, or renamed independently per gallery.
 *
 * The image_approval_states table references this model's ID for
 * "pending for what reason?".
 *
 * @property int         $id
 * @property int         $gallery_id
 * @property int|null    $template_id
 * @property string      $name
 * @property string|null $description
 * @property string|null $icon
 * @property int         $sort_order
 * @property bool        $is_enabled
 */
class GalleryPendingType extends Model
{
    protected $fillable = [
        'gallery_id',
        'template_id',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_enabled',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_enabled' => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function gallery(): BelongsTo
    {
        return $this->belongsTo(Gallery::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(StudioPendingTypeTemplate::class, 'template_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true)->orderBy('sort_order');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Populate pending types for a newly created gallery from all active studio templates.
     * Used as the fallback when the photographer doesn't customise the checklist.
     */
    public static function populateFromStudioTemplates(Gallery $gallery): void
    {
        $templates = StudioPendingTypeTemplate::activeForStudio($gallery->studio_id)->get();

        foreach ($templates as $template) {
            static::create([
                'gallery_id'  => $gallery->id,
                'template_id' => $template->id,
                'name'        => $template->name,
                'description' => $template->description,
                'icon'        => $template->icon,
                'sort_order'  => $template->sort_order,
                'is_enabled'  => true,
            ]);
        }
    }

    /**
     * Populate pending types from a specific subset of template IDs.
     * Used when the photographer customises the checklist in the creation wizard.
     * Templates not in $templateIds are skipped; sort_order from the template is preserved.
     *
     * @param  int[]  $templateIds
     */
    public static function populateFromTemplateIds(Gallery $gallery, array $templateIds): void
    {
        if (empty($templateIds)) {
            return;
        }

        $templates = StudioPendingTypeTemplate::whereIn('id', $templateIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($templates as $template) {
            static::create([
                'gallery_id'  => $gallery->id,
                'template_id' => $template->id,
                'name'        => $template->name,
                'description' => $template->description,
                'icon'        => $template->icon,
                'sort_order'  => $template->sort_order,
                'is_enabled'  => true,
            ]);
        }
    }
}
