<?php

namespace ProPhoto\Access\Enums;

enum UserRole: string
{
    case STUDIO_USER = 'studio_user';  // Photographer
    case CLIENT_USER = 'client_user';  // Client user
    case GUEST_USER = 'guest_user';    // Subject/Guest
    case VENDOR_USER = 'vendor_user'; // Vendor user - added based on Roles: studio_user, client_user, guest_user, vendor_user

    public function label(): string
    {
        return match($this) {
            self::STUDIO_USER => 'Studio User (Photographer)',
            self::CLIENT_USER => 'Client User',
            self::GUEST_USER => 'Guest User (Subject)',
            self::VENDOR_USER => 'Vendor User (External Collaborator)',
        };
    }
}
