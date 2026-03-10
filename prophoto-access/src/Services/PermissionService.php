<?php

namespace ProPhoto\Access\Services;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;

class PermissionService
{
    /**
     * Get effective permissions for a user, considering inheritance hierarchy:
     * Organization Defaults → User Overrides → Gallery-Specific Restrictions
     */
    public function getEffectivePermissions($user, ?int $contextableId = null, string $contextableType = null): array
    {
        // Studio users have all permissions globally
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return array_fill_keys(array_keys(Permissions::labels()), true);
        }

        $permissions = Permissions::getRoleDefaults($user->getRoleNames()->first());

        // Apply organization defaults for client users if available
        if ($user->hasRole(UserRole::CLIENT_USER->value) && $user->organization) {
            $orgPermissions = $user->organization->permissions ?? [];
            $orgSettings = $user->organization->settings ?? [];
            $aiEnabled = $orgSettings['ai_enabled'] ?? false;

            $permissions[Permissions::VIEW_GALLERIES] = $orgPermissions[Permissions::VIEW_GALLERIES] ?? true;
            $permissions[Permissions::APPROVE_IMAGES] = $orgPermissions[Permissions::APPROVE_IMAGES] ?? true;
            $permissions[Permissions::DOWNLOAD_IMAGES] = $orgPermissions[Permissions::DOWNLOAD_IMAGES] ?? true;
            $permissions[Permissions::ARCHIVE_GALLERY] = $orgPermissions[Permissions::ARCHIVE_GALLERY] ?? true;
            $permissions[Permissions::GENERATE_AI_PORTRAITS] = $aiEnabled ? ($orgPermissions[Permissions::GENERATE_AI_PORTRAITS] ?? true) : false;
            $permissions[Permissions::DISABLE_AI_PORTRAITS] = $orgPermissions[Permissions::DISABLE_AI_PORTRAITS] ?? true;
            $permissions[Permissions::REQUEST_BOOKING] = $orgPermissions[Permissions::REQUEST_BOOKING] ?? true;
            $permissions[Permissions::VIEW_CALENDAR] = $orgPermissions[Permissions::VIEW_CALENDAR] ?? true;
            $permissions[Permissions::VIEW_INVOICES] = $orgPermissions[Permissions::VIEW_INVOICES] ?? true;
            $permissions[Permissions::DOWNLOAD_INVOICE_PDF] = $orgPermissions[Permissions::DOWNLOAD_INVOICE_PDF] ?? true;
            $permissions[Permissions::INVITE_USERS] = $orgPermissions[Permissions::INVITE_USERS] ?? true;
            $permissions[Permissions::MANAGE_ORG_SETTINGS] = $orgPermissions[Permissions::MANAGE_ORG_SETTINGS] ?? false;
        }

        // Apply user-level overrides (can only restrict, not expand) for client users
        if ($user->hasRole(UserRole::CLIENT_USER->value) && isset($user->permissions)) {
            $userOverrides = $user->permissions ?? [];
            foreach ($userOverrides as $permission => $allowed) {
                if (isset($permissions[$permission]) && $allowed === false) {
                    $permissions[$permission] = false;
                }
            }
        }

        // Apply gallery-specific restrictions for guest users
        if ($user->hasRole(UserRole::GUEST_USER->value) && $contextableId && $contextableType) {
            // For guest users, `hasContextualPermission` (from trait) is the primary check.
            // This service method is more for UI display of permissions. If a guest user
            // has a gallery-specific permission, it would be granted via PermissionContext.
            // Here, we'll assume if contextableId is provided, we check against that specific context.
            // This part is illustrative and would ideally use the HasContextualPermissions trait.
            // For now, if a guest user is provided with a contextableId, assume they only have access
            // to permissions specifically granted for that context.
            $contextualPermissions = $user->permissionContexts()
                                        ->where('contextable_id', $contextableId)
                                        ->where('contextable_type', $contextableType)
                                        ->pluck('permission.name')
                                        ->toArray();
            
            $filteredPermissions = [];
            foreach ($permissions as $permissionName => $allowed) {
                $filteredPermissions[$permissionName] = in_array($permissionName, $contextualPermissions);
            }
            return $filteredPermissions;
        }

        return $permissions;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission($user, string $permission, ?int $contextableId = null, string $contextableType = null): bool
    {
        // Studio users always have all permissions
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }
        
        // Use the HasContextualPermissions trait for guest users if context is provided
        if ($user->hasRole(UserRole::GUEST_USER->value) && $contextableId && $contextableType) {
             $context = $contextableType::find($contextableId); // Assuming contextableType is a fully qualified class name
             if ($context) {
                 return $user->hasContextualPermission($permission, $context);
             }
             return false;
        }

        $effectivePermissions = $this->getEffectivePermissions($user, $contextableId, $contextableType);
        return $effectivePermissions[$permission] ?? false;
    }
}
