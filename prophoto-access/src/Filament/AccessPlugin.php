<?php

namespace ProPhoto\Access\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use ProPhoto\Access\Filament\Pages\PermissionMatrix;
use ProPhoto\Access\Filament\Resources\PermissionResource;
use ProPhoto\Access\Filament\Resources\RoleResource;

class AccessPlugin implements Plugin
{
    protected bool $hasRoleResource = true;

    protected bool $hasPermissionResource = true;

    protected bool $hasPermissionMatrix = true;

    public function getId(): string
    {
        return 'prophoto-access';
    }

    public function register(Panel $panel): void
    {
        $resources = [];
        $pages = [];

        if ($this->hasRoleResource) {
            $resources[] = RoleResource::class;
        }

        if ($this->hasPermissionResource) {
            $resources[] = PermissionResource::class;
        }

        if ($this->hasPermissionMatrix) {
            $pages[] = PermissionMatrix::class;
        }

        $panel
            ->resources($resources)
            ->pages($pages);
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
     * Enable or disable the Role resource
     */
    public function roleResource(bool $condition = true): static
    {
        $this->hasRoleResource = $condition;

        return $this;
    }

    /**
     * Enable or disable the Permission resource
     */
    public function permissionResource(bool $condition = true): static
    {
        $this->hasPermissionResource = $condition;

        return $this;
    }

    /**
     * Enable or disable the Permission Matrix page
     */
    public function permissionMatrix(bool $condition = true): static
    {
        $this->hasPermissionMatrix = $condition;

        return $this;
    }
}
