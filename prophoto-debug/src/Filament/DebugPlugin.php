<?php

namespace ProPhoto\Debug\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ProPhoto\Debug\Filament\Pages\ConfigSnapshotsPage;
use ProPhoto\Debug\Filament\Pages\IngestTracesPage;

class DebugPlugin implements Plugin
{
    public function getId(): string
    {
        return 'prophoto-debug';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            IngestTracesPage::class,
            ConfigSnapshotsPage::class,
        ]);
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
}
