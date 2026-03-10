<?php

namespace ProPhoto\Access\Policies;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Models\Organization;
use ProPhoto\Access\Permissions;

class OrganizationPolicy
{
    /**
     * Determine whether the user can view any organizations.
     */
    public function viewAny($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can view the organization.
     */
    public function view($user, Organization $organization): bool
    {
        // Studio users see all
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users see only their organization
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $organization->id;
        }

        return false;
    }

    /**
     * Determine whether the user can create organizations.
     */
    public function create($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can update the organization.
     */
    public function update($user, Organization $organization): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can delete the organization.
     */
    public function delete($user, Organization $organization): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can manage organization users.
     */
    public function manageUsers($user, Organization $organization): bool
    {
        // Studio users can manage any org
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users can manage their own org if they have permission
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $organization->id
                && $user->hasPermissionTo(Permissions::MANAGE_ORG_USERS);
        }

        return false;
    }

    /**
     * Determine whether the user can manage organization settings.
     */
    public function manageSettings($user, Organization $organization): bool
    {
        // Studio users can manage any org
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users can manage their own org if they have permission
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $organization->id
                && $user->hasPermissionTo(Permissions::MANAGE_ORG_SETTINGS);
        }

        return false;
    }
}
