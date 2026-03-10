<?php

return [
    'roles' => [
        'studio_user' => [
            'name' => 'Studio User',
            'description' => 'Photographer/administrator with full access',
        ],
        'client_user' => [
            'name' => 'Client User',
            'description' => 'Organization contacts (marketing, billing, etc.)',
        ],
        'guest_user' => [
            'name' => 'Guest User',
            'description' => 'Subjects with magic link access to their galleries',
        ],
        'vendor_user' => [
            'name' => 'Vendor User',
            'description' => 'External collaborators (future use)',
        ],
    ],
    
    'permission_categories' => [
        'galleries' => 'Gallery Management',
        'ai' => 'AI Portrait Generation',
        'sessions' => 'Sessions & Booking',
        'organizations' => 'Client Organizations',
        'invoices' => 'Invoicing & Payments',
        'users' => 'User Management',
        'messages' => 'Messages & Notifications',
        'system' => 'System Administration',
    ],
];
