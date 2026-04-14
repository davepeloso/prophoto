<?php

namespace ProPhoto\Gallery\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource;

/**
 * GalleryPlugin
 *
 * Registers ProPhoto Gallery Filament resources into a Filament panel.
 *
 * Usage in your panel provider:
 *   ->plugins([
 *       \ProPhoto\Gallery\Filament\GalleryPlugin::make(),
 *   ])
 *
 * Resources registered:
 *   - PendingTypeTemplateResource (Gallery Settings → Pending Type Templates)
 */
class GalleryPlugin implements Plugin
{
    protected bool $hasPendingTypeTemplates = true;

    public function getId(): string
    {
        return 'prophoto-gallery';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if ($this->hasPendingTypeTemplates) {
            $resources[] = PendingTypeTemplateResource::class;
        }

        $panel->resources($resources);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        /** @var static $plugin */
        $plugin = filament(app(static::class)->getId());

        return $plugin;
    }

    /**
     * Enable or disable the Pending Type Templates resource.
     */
    public function pendingTypeTemplates(bool $condition = true): static
    {
        $this->hasPendingTypeTemplates = $condition;

        return $this;
    }
}
