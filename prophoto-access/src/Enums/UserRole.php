<?php

namespace ProPhoto\Access\Enums;

enum UserRole: string
{
    case STUDIO_USER = 'studio_user';  // Photographer
    case CLIENT_USER = 'client_user';  // Client user
    case GUEST_USER = 'guest_user';    // Subject/Guest
    case VENDOR_USER = 'vendor_user';   // Vendor user - external collaborator
    case SYSTEM_ADMIN = 'system_admin'; // Cross-studio tech admin — RBAC Phase 2 (stub only, not yet seeded)

    public function label(): string
    {
        return match($this) {
            self::STUDIO_USER => 'Studio User (Photographer)',
            self::CLIENT_USER => 'Client User',
            self::GUEST_USER => 'Guest User (Subject)',
            self::VENDOR_USER => 'Vendor User (External Collaborator)',
            self::SYSTEM_ADMIN => 'System Admin (Cross-Studio — Phase 2)',
        };
    }
}
