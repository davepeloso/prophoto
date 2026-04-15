<?php

namespace ProPhoto\Gallery\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ProPhoto\Gallery\Filament\Resources\AccessLogResource;
use ProPhoto\Gallery\Filament\Resources\GalleryResource;
use ProPhoto\Gallery\Filament\Resources\PendingTypeTemplateResource;
use ProPhoto\Gallery\Filament\Widgets\GalleryDownloadStatsWidget;
use ProPhoto\Gallery\Filament\Widgets\RecentSubmissionsWidget;

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
 *   - GalleryResource           (Galleries → Galleries)
 *   - PendingTypeTemplateResource (Gallery Settings → Pending Type Templates)
 *
 * Widgets registered:
 *   - RecentSubmissionsWidget   (Dashboard — recent proofing submissions)
 */
class GalleryPlugin implements Plugin
{
    protected bool $hasGalleries            = true;
    protected bool $hasPendingTypeTemplates = true;
    protected bool $hasAccessLogs          = true;
    protected bool $hasSubmissionsWidget       = true;
    protected bool $hasDownloadStatsWidget    = true;

    public function getId(): string
    {
        return 'prophoto-gallery';
    }

    public function register(Panel $panel): void
    {
        $resources = [];

        if ($this->hasGalleries) {
            $resources[] = GalleryResource::class;
        }

        if ($this->hasPendingTypeTemplates) {
            $resources[] = PendingTypeTemplateResource::class;
        }

        if ($this->hasAccessLogs) {
            $resources[] = AccessLogResource::class;
        }

        $panel->resources($resources);

        if ($this->hasSubmissionsWidget) {
            $panel->widgets([
                RecentSubmissionsWidget::class,
            ]);
        }
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
     * Enable or disable the Galleries resource.
     */
    public function galleries(bool $condition = true): static
    {
        $this->hasGalleries = $condition;

        return $this;
    }

    /**
     * Enable or disable the Recent Submissions dashboard widget.
     */
    public function submissionsWidget(bool $condition = true): static
    {
        $this->hasSubmissionsWidget = $condition;

        return $this;
    }

    /**
     * Enable or disable the Pending Type Templates resource.
     */
    public function pendingTypeTemplates(bool $condition = true): static
    {
        $this->hasPendingTypeTemplates = $condition;

        return $this;
    }

    /**
     * Enable or disable the Download Stats widget on the gallery edit page.
     */
    public function downloadStatsWidget(bool $condition = true): static
    {
        $this->hasDownloadStatsWidget = $condition;

        return $this;
    }

    public function hasDownloadStats(): bool
    {
        return $this->hasDownloadStatsWidget;
    }
}
