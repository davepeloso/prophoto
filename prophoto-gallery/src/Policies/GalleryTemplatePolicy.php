<?php

namespace ProPhoto\Gallery\Policies;

use ProPhoto\Access\Permissions;
use ProPhoto\Gallery\Models\GalleryTemplate;

class GalleryTemplatePolicy
{
    /**
     * Determine if the user can view any templates.
     */
    public function viewAny($user): bool
    {
        return $user->can(Permissions::CREATE_GALLERY_TEMPLATE) ||
               $user->can(Permissions::MANAGE_GALLERY_TEMPLATES);
    }

    /**
     * Determine if the user can view the template.
     */
    public function view($user, GalleryTemplate $template): bool
    {
        // Studio users can view all templates
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can view global templates
        if ($template->is_default) {
            return true;
        }

        // Users can view templates they created
        if ($template->created_by_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the user can create templates.
     */
    public function create($user): bool
    {
        return $user->can(Permissions::CREATE_GALLERY_TEMPLATE);
    }

    /**
     * Determine if the user can update the template.
     */
    public function update($user, GalleryTemplate $template): bool
    {
        // Studio users can edit all templates
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can edit templates they created (non-global only)
        if ($template->created_by_user_id === $user->id && !$template->is_default) {
            return $user->can(Permissions::CREATE_GALLERY_TEMPLATE);
        }

        // Only studio users can edit global templates
        return false;
    }

    /**
     * Determine if the user can delete the template.
     */
    public function delete($user, GalleryTemplate $template): bool
    {
        // Studio users can delete all templates
        if ($user->hasRole('studio_user')) {
            return true;
        }

        // Users can delete templates they created (non-global only)
        if ($template->created_by_user_id === $user->id && !$template->is_default) {
            return $user->can(Permissions::MANAGE_GALLERY_TEMPLATES);
        }

        // Only studio users can delete global templates
        return false;
    }

    /**
     * Determine if the user can manage global templates.
     */
    public function manageGlobal($user): bool
    {
        return $user->hasRole('studio_user') &&
               $user->can(Permissions::MANAGE_GALLERY_TEMPLATES);
    }

    /**
     * Determine if the user can restore the template.
     */
    public function restore($user, GalleryTemplate $template): bool
    {
        return $this->delete($user, $template);
    }

    /**
     * Determine if the user can permanently delete the template.
     */
    public function forceDelete($user, GalleryTemplate $template): bool
    {
        return $user->hasRole('studio_user');
    }
}
