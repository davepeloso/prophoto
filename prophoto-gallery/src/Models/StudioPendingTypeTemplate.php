<?php

namespace ProPhoto\Gallery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use ProPhoto\Access\Models\Studio;

/**
 * Studio-level master list of pending types.
 *
 * The photographer manages this once from their settings. System defaults
 * (studio_id = null) are seeded automatically and cannot be deleted,
 * but can be hidden per-studio via is_active = false.
 *
 * @property int         $id
 * @property int|null    $studio_id
 * @property string      $name
 * @property string|null $description
 * @property string|null $icon
 * @property int         $sort_order
 * @property bool        $is_system_default
 * @property bool        $is_active
 */
class StudioPendingTypeTemplate extends Model
{
    protected $fillable = [
        'studio_id',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_system_default',
        'is_active',
    ];

    protected $casts = [
        'sort_order'        => 'integer',
        'is_system_default' => 'boolean',
        'is_active'         => 'boolean',
    ];

    // ── System defaults ───────────────────────────────────────────────────────

    /**
     * The four default pending types seeded for every studio.
     * Order matters — determines default sort_order.
     */
    public const SYSTEM_DEFAULTS = [
        [
            'name'        => 'Retouch',
            'description' => 'Image needs standard retouching (skin, exposure, sharpness)',
            'icon'        => 'pencil-square',
        ],
        [
            'name'        => 'Background Swap',
            'description' => 'Replace or composite a new background',
            'icon'        => 'photo',
        ],
        [
            'name'        => 'Awaiting Second Approval',
            'description' => 'Hold for a second stakeholder to approve before delivery',
            'icon'        => 'clock',
        ],
        [
            'name'        => 'Colour Correction',
            'description' => 'Adjust white balance, colour grading, or tone',
            'icon'        => 'swatch',
        ],
    ];

    // ── Relationships ──────────────────────────────────────────────────────────

    public function studio(): BelongsTo
    {
        return $this->belongsTo(Studio::class);
    }

    public function galleryPendingTypes(): HasMany
    {
        return $this->hasMany(GalleryPendingType::class, 'template_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────────

    /**
     * Active templates for a given studio (includes active system defaults).
     */
    public function scopeActiveForStudio($query, int $studioId)
    {
        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($studioId) {
                $q->whereNull('studio_id')              // system defaults
                  ->orWhere('studio_id', $studioId);   // studio custom
            })
            ->orderBy('sort_order');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    /**
     * Seed or refresh system defaults. Safe to call multiple times (upsert).
     */
    public static function seedSystemDefaults(): void
    {
        foreach (static::SYSTEM_DEFAULTS as $index => $data) {
            static::updateOrCreate(
                ['studio_id' => null, 'name' => $data['name']],
                [
                    'description'       => $data['description'],
                    'icon'              => $data['icon'],
                    'sort_order'        => $index,
                    'is_system_default' => true,
                    'is_active'         => true,
                ]
            );
        }
    }
}
