<?php

namespace ProPhoto\Access\Facades;

use Illuminate\Support\Facades\Facade;
use ProPhoto\Access\Services\PermissionService;

/**
 * @method static array getEffectivePermissions($user, ?int $contextableId = null, string $contextableType = null)
 * @method static bool hasPermission($user, string $permission, ?int $contextableId = null, string $contextableType = null)
 *
 * @see \ProPhoto\Access\Services\PermissionService
 */
class Access extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return PermissionService::class;
    }
}
