<?php

namespace ProPhoto\Gallery\Policies;

use ProPhoto\Access\Enums\UserRole;
use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\Gallery;

class GalleryPolicy
{
    /**
     * Determine whether the user can view any galleries.
     */
    public function viewAny($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value)
            || $user->hasRole(UserRole::CLIENT_USER->value);
    }

    /**
     * Determine whether the user can view the gallery.
     */
    public function view($user, Gallery $gallery): bool
    {
        // Studio users see all
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users see their organization's galleries
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $gallery->organization_id;
        }

        // Guest users see only their specific gallery via contextual permission
        if ($user->hasRole(UserRole::GUEST_USER->value)) {
            return $user->hasContextualPermission(Permissions::VIEW_GALLERIES, $gallery);
        }

        return false;
    }

    /**
     * Determine whether the user can create galleries.
     */
    public function create($user): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can update the gallery.
     */
    public function update($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can delete the gallery.
     */
    public function delete($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can archive the gallery.
     */
    public function archive($user, Gallery $gallery): bool
    {
        // Studio users can always archive
        if ($user->hasRole(UserRole::STUDIO_USER->value)) {
            return true;
        }

        // Client users can archive their organization's galleries
        if ($user->hasRole(UserRole::CLIENT_USER->value)) {
            return $user->organization_id === $gallery->organization_id
                && $user->hasContextualPermission(Permissions::ARCHIVE_GALLERY, $gallery);
        }

        return false;
    }

    /**
     * Determine whether the user can upload images to the gallery.
     */
    public function uploadImages($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can delete images from the gallery.
     */
    public function deleteImages($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can download images from the gallery.
     */
    public function downloadImages($user, Gallery $gallery): bool
    {
        // All authenticated users with gallery access can download
        return $this->view($user, $gallery);
    }

    /**
     * Determine whether the user can approve images in the gallery.
     */
    public function approveImages($user, Gallery $gallery): bool
    {
        if (!$this->view($user, $gallery)) {
            return false;
        }

        return $user->hasContextualPermission(Permissions::APPROVE_IMAGES, $gallery)
            || $user->hasPermissionTo(Permissions::APPROVE_IMAGES);
    }

    /**
     * Determine whether the user can rate images in the gallery.
     */
    public function rateImages($user, Gallery $gallery): bool
    {
        return $this->view($user, $gallery);
    }

    /**
     * Determine whether the user can comment on images in the gallery.
     */
    public function commentOnImages($user, Gallery $gallery): bool
    {
        return $this->view($user, $gallery);
    }

    /**
     * Determine whether the user can request edits for images.
     */
    public function requestEdits($user, Gallery $gallery): bool
    {
        // Only guest users (subjects) can request edits
        if (!$user->hasRole(UserRole::GUEST_USER->value)) {
            return false;
        }

        return $user->hasContextualPermission(Permissions::REQUEST_EDITS, $gallery);
    }

    /**
     * Determine whether the user can generate AI portraits.
     */
    public function generateAiPortraits($user, Gallery $gallery): bool
    {
        // Must be enabled for the gallery
        if (!$gallery->ai_enabled) {
            return false;
        }

        // Must have trained model
        if (!$gallery->canGenerateAiPortraits()) {
            return false;
        }

        return $user->hasContextualPermission(Permissions::GENERATE_AI_PORTRAITS, $gallery)
            || $user->hasPermissionTo(Permissions::GENERATE_AI_PORTRAITS);
    }

    /**
     * Determine whether the user can enable AI for the gallery.
     */
    public function enableAi($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can mark the gallery as complete.
     */
    public function markComplete($user, Gallery $gallery): bool
    {
        return $user->hasRole(UserRole::STUDIO_USER->value);
    }

    /**
     * Determine whether the user can share the gallery.
     */
    public function share($user, Gallery $gallery): bool
    {
        return $this->view($user, $gallery);
    }
}
