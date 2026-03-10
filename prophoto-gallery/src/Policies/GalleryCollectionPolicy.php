<?php

namespace ProPhoto\Gallery\Policies;

use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\GalleryCollection;

class GalleryCollectionPolicy
{
    /**
     * Determine if the user can view any collections.
     */
    public function viewAny($user): bool
    {
        return $user->can(Permissions::VIEW_COLLECTION);
    }

    /**
     * Determine if the user can view the collection.
     */
    public function view($user, GalleryCollection $collection): bool
    {
        // Studio users can view all collections
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can view collections they created
        if ($collection->created_by_user_id === $user->id) {
            return true;
        }

        // Organization members can view their org's collections
        if ($user->organization_id && $user->organization_id === $collection->organization_id) {
            return $user->can(Permissions::VIEW_COLLECTION);
        }

        // Check for contextual permission
        if ($user->hasContextualPermission(Permissions::VIEW_COLLECTION, $collection)) {
            return true;
        }

        // Public collections can be viewed by anyone with permission
        if ($collection->is_public && $user->can(Permissions::VIEW_COLLECTION)) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create collections.
     */
    public function create($user): bool
    {
        return $user->can(Permissions::CREATE_COLLECTION);
    }

    /**
     * Determine if the user can update the collection.
     */
    public function update($user, GalleryCollection $collection): bool
    {
        // Studio users can edit all collections
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can edit collections they created
        if ($collection->created_by_user_id === $user->id && $user->can(Permissions::EDIT_COLLECTION)) {
            return true;
        }

        // Check for contextual permission
        return $user->hasContextualPermission(Permissions::EDIT_COLLECTION, $collection);
    }

    /**
     * Determine if the user can delete the collection.
     */
    public function delete($user, GalleryCollection $collection): bool
    {
        // Studio users can delete all collections
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can delete collections they created
        if ($collection->created_by_user_id === $user->id && $user->can(Permissions::DELETE_COLLECTION)) {
            return true;
        }

        // Check for contextual permission
        return $user->hasContextualPermission(Permissions::DELETE_COLLECTION, $collection);
    }

    /**
     * Determine if the user can restore the collection.
     */
    public function restore($user, GalleryCollection $collection): bool
    {
        return $this->delete($user, $collection);
    }

    /**
     * Determine if the user can permanently delete the collection.
     */
    public function forceDelete($user, GalleryCollection $collection): bool
    {
        return $user->hasRole('studio_user');
    }
}
