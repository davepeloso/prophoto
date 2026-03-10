<?php

namespace ProPhoto\Gallery\Policies;

use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\GalleryShare;

class GallerySharePolicy
{
    /**
     * Determine if the user can view any share links.
     */
    public function viewAny($user): bool
    {
        return $user->can(Permissions::CREATE_SHARE_LINK) ||
               $user->can(Permissions::VIEW_SHARE_ANALYTICS);
    }

    /**
     * Determine if the user can view the share link.
     */
    public function view($user, GalleryShare $share): bool
    {
        // Studio users can view all share links
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can view share links they created
        if ($share->shared_by_user_id === $user->id) {
            return true;
        }

        // Check gallery ownership
        if ($share->gallery && data_get($user, 'organization_id') === $share->gallery->organization_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create share links.
     */
    public function create($user): bool
    {
        return $user->can(Permissions::CREATE_SHARE_LINK);
    }

    /**
     * Determine if the user can update the share link.
     */
    public function update($user, GalleryShare $share): bool
    {
        // Studio users can edit all share links
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can edit share links they created
        if ($share->shared_by_user_id === $user->id && $user->can(Permissions::CREATE_SHARE_LINK)) {
            return true;
        }

        // Check gallery ownership
        if ($share->gallery && data_get($user, 'organization_id') === $share->gallery->organization_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can delete (revoke) the share link.
     */
    public function delete($user, GalleryShare $share): bool
    {
        // Studio users can revoke all share links
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can revoke share links they created
        if ($share->shared_by_user_id === $user->id && $user->can(Permissions::REVOKE_SHARE_LINK)) {
            return true;
        }

        // Check gallery ownership
        if ($share->gallery && data_get($user, 'organization_id') === $share->gallery->organization_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can view share analytics.
     */
    public function viewAnalytics($user, GalleryShare $share): bool
    {
        // Studio users can view all analytics
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Check permission
        if (!$user->can(Permissions::VIEW_SHARE_ANALYTICS)) {
            return false;
        }

        // Users can view analytics for share links they created
        if ($share->shared_by_user_id === $user->id) {
            return true;
        }

        // Check gallery ownership
        if ($share->gallery && data_get($user, 'organization_id') === $share->gallery->organization_id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can restore the share link.
     */
    public function restore($user, GalleryShare $share): bool
    {
        return $this->delete($user, $share);
    }

    /**
     * Determine if the user can permanently delete the share link.
     */
    public function forceDelete($user, GalleryShare $share): bool
    {
        return $user->hasRole('studio_user');
    }
}
