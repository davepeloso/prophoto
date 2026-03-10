<?php

namespace ProPhoto\Access\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ProPhoto\Access\Models\PermissionContext;
use ProPhoto\Access\Models\Organization;
use Spatie\Permission\Models\Permission;

trait HasContextualPermissions
{
    /**
     * Get all contextual permissions for this user.
     */
    public function permissionContexts(): HasMany
    {
        return $this->hasMany(PermissionContext::class, 'user_id');
    }

    /**
     * Get the organization this user belongs to (for client users).
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Check if user has a contextual permission.
     */
    public function hasContextualPermission(string $permission, $context): bool
    {
        // Check if user has global permission (studio_user)
        if ($this->hasPermissionTo($permission)) {
            return true;
        }

        // Check contextual permission (not expired)
        return $this->permissionContexts()
            ->whereHas('permission', fn($q) => $q->where('name', $permission))
            ->where('contextable_type', get_class($context))
            ->where('contextable_id', $context->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    /**
     * Grant a contextual permission to this user.
     */
    public function grantContextualPermission(string $permission, $context, $expiresAt = null): PermissionContext|false
    {
        $permissionModel = Permission::findByName($permission);

        if (!$permissionModel) {
            return false;
        }

        return PermissionContext::updateOrCreate(
            [
                'user_id' => $this->id,
                'permission_id' => $permissionModel->id,
                'contextable_type' => get_class($context),
                'contextable_id' => $context->id,
            ],
            [
                'granted_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );
    }

    /**
     * Revoke a contextual permission from this user.
     */
    public function revokeContextualPermission(string $permission, $context): bool
    {
        $permissionModel = Permission::findByName($permission);

        if (!$permissionModel) {
            return false;
        }

        return $this->permissionContexts()
            ->where('permission_id', $permissionModel->id)
            ->where('contextable_type', get_class($context))
            ->where('contextable_id', $context->id)
            ->delete() > 0;
    }

    /**
     * Revoke all contextual permissions for a specific context.
     */
    public function revokeAllContextualPermissions($context): int
    {
        return $this->permissionContexts()
            ->where('contextable_type', get_class($context))
            ->where('contextable_id', $context->id)
            ->delete();
    }

    /**
     * Grant multiple contextual permissions at once.
     */
    public function grantContextualPermissions(array $permissions, $context, $expiresAt = null): array
    {
        $results = [];

        foreach ($permissions as $permission) {
            $results[$permission] = $this->grantContextualPermission($permission, $context, $expiresAt);
        }

        return $results;
    }

    /**
     * Get all contextual permissions for a specific context.
     */
    public function getContextualPermissions($context): array
    {
        return $this->permissionContexts()
            ->where('contextable_type', get_class($context))
            ->where('contextable_id', $context->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->with('permission')
            ->get()
            ->pluck('permission.name')
            ->toArray();
    }

    /**
     * Check if user has any of the given contextual permissions.
     */
    public function hasAnyContextualPermission(array $permissions, $context): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasContextualPermission($permission, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given contextual permissions.
     */
    public function hasAllContextualPermissions(array $permissions, $context): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasContextualPermission($permission, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sync contextual permissions for a context.
     */
    public function syncContextualPermissions(array $permissions, $context, $expiresAt = null): void
    {
        // Remove all existing permissions for this context
        $this->revokeAllContextualPermissions($context);

        // Grant the new permissions
        $this->grantContextualPermissions($permissions, $context, $expiresAt);
    }
}
