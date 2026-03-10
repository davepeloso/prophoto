<?php

namespace ProPhoto\Booking\Policies;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;
use ProPhoto\Booking\Models\Session;

class SessionPolicy
{
    /**
     * Determine whether the user can view any sessions.
     */
    public function viewAny($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value)
            || $user->hasRole(UserRole::CLIENT_USER->value);
    }

    /**
     * Determine whether the user can view the session.
     */
    public function view($user, Session $session): bool
    {
        // Studio users see all
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users see their organization's sessions
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $session->organization_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create sessions.
     */
    public function create($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can update the session.
     */
    public function update($user, Session $session): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can delete the session.
     */
    public function delete($user, Session $session): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can cancel the session.
     */
    public function cancel($user, Session $session): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }
}
