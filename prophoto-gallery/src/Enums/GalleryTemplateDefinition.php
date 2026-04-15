<?php

namespace ProPhoto\Gallery\Enums;

use ProPhoto\Gallery\Models\Gallery;

/**
 * GalleryTemplateDefinition
 *
 * Single source of truth for gallery template defaults.
 * Each case represents one card in the template picker UI.
 *
 * To add a new template: add a case here — no DB table or migration needed.
 *
 * Template type determines:
 *   - proofing     → mode_config pre-filled, pipeline fields shown in form
 *   - presentation → mode_config null, pipeline fields hidden in form
 */
enum GalleryTemplateDefinition: string
{
    case Portrait     = 'portrait';
    case Editorial    = 'editorial';
    case Classic      = 'classic';
    case Architectural = 'architectural';
    case Profile      = 'profile';
    case SingleColumn = 'single_column';

    // ── Display ───────────────────────────────────────────────────────────

    public function label(): string
    {
        return match ($this) {
            self::Portrait      => 'Portrait Template',
            self::Editorial     => 'Editorial Template',
            self::Classic       => 'Classic Template',
            self::Architectural => 'Architectural Template',
            self::Profile       => 'Profile Template',
            self::SingleColumn  => 'Single Column Template',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Portrait      => 'Two-column, tall cards. Intimate & warm.',
            self::Editorial     => 'Asymmetric, cinematic. Mixed aspect ratios.',
            self::Classic       => 'Balanced gallery grid with rating and note controls.',
            self::Architectural => 'Three-column grid, landscape cards. Precise.',
            self::Profile       => 'Centered profile header with portfolio grid.',
            self::SingleColumn  => 'Full-width vertical stack. Cinematic & editorial.',
        };
    }

    // ── Type ──────────────────────────────────────────────────────────────

    public function galleryType(): string
    {
        return match ($this) {
            self::Portrait,
            self::Editorial,
            self::Classic       => Gallery::TYPE_PROOFING,

            self::Architectural,
            self::Profile,
            self::SingleColumn  => Gallery::TYPE_PRESENTATION,
        };
    }

    public function isProofing(): bool
    {
        return $this->galleryType() === Gallery::TYPE_PROOFING;
    }

    // ── Mode config defaults ──────────────────────────────────────────────

    /**
     * Returns the default mode_config for this template.
     * Presentation templates return null — no pipeline config.
     *
     * @return array{
     *     min_approvals: int|null,
     *     max_approvals: int|null,
     *     max_pending: int|null,
     *     ratings_enabled: bool,
     *     pipeline_sequential: bool
     * }|null
     */
    public function modeConfig(): ?array
    {
        return match ($this) {
            self::Portrait => [
                'min_approvals'      => 1,
                'max_approvals'      => null,
                'max_pending'        => null,
                'ratings_enabled'    => true,
                'pipeline_sequential' => true,
            ],
            self::Editorial => [
                'min_approvals'      => 1,
                'max_approvals'      => null,
                'max_pending'        => null,
                'ratings_enabled'    => false,
                'pipeline_sequential' => true,
            ],
            self::Classic => [
                'min_approvals'      => 1,
                'max_approvals'      => null,
                'max_pending'        => null,
                'ratings_enabled'    => true,
                'pipeline_sequential' => true,
            ],

            // Presentation templates have no pipeline
            self::Architectural,
            self::Profile,
            self::SingleColumn => null,
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Returns all cases as a keyed array suitable for Filament Select/Radio options.
     * [ 'portrait' => 'Portrait Template', ... ]
     */
    public static function filamentOptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * Returns descriptions keyed by case value for Filament Radio descriptions().
     */
    public static function filamentDescriptions(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->description()])
            ->all();
    }
}
