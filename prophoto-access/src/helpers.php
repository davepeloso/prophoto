<?php

use ProPhoto\Access\Services\PermissionService;

if (!function_exists('can_access')) {
    /**
     * Check if the current user has a permission.
     *
     * @param string $permission The permission to check
     * @param mixed|null $context Optional context model for contextual permissions
     * @return bool
     */
    function can_access(string $permission, $context = null): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        if ($context) {
            return $user->hasContextualPermission($permission, $context);
        }

        return $user->hasPermissionTo($permission);
    }
}

if (!function_exists('user_permissions')) {
    /**
     * Get the effective permissions for the current user.
     *
     * @param mixed|null $context Optional context model
     * @return array
     */
    function user_permissions($context = null): array
    {
        $user = auth()->user();

        if (!$user) {
            return [];
        }

        $service = app(PermissionService::class);

        if ($context) {
            return $service->getEffectivePermissions(
                $user,
                $context->id,
                get_class($context)
            );
        }

        return $service->getEffectivePermissions($user);
    }
}

if (!function_exists('is_studio_user')) {
    /**
     * Check if the current user is a studio user (photographer).
     *
     * @return bool
     */
    function is_studio_user(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('studio_user');
    }
}

if (!function_exists('is_client_user')) {
    /**
     * Check if the current user is a client user.
     *
     * @return bool
     */
    function is_client_user(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('client_user');
    }
}

if (!function_exists('is_guest_user')) {
    /**
     * Check if the current user is a guest user (subject).
     *
     * @return bool
     */
    function is_guest_user(): bool
    {
        $user = auth()->user();

        return $user && $user->hasRole('guest_user');
    }
}
