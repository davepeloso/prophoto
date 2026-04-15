<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Gallery Settings
    |--------------------------------------------------------------------------
    |
    | Default configuration for the ProPhoto Galleries package
    |
    */

    'defaults' => [
        // Default gallery settings
        'allow_downloads' => true,
        'allow_comments' => true,
        'allow_ratings' => true,
        'watermark_enabled' => false,
        'auto_archive_days' => 365,
    ],

    'sharing' => [
        // Share link defaults
        'default_expiration_days' => 30,
        'require_password' => false,
        'allow_downloads' => true,
        'allow_social_sharing' => true,
    ],

    'collections' => [
        // Collection defaults
        'max_galleries_per_collection' => 100,
        'allow_nested_collections' => false,
    ],

    'templates' => [
        // Template defaults
        'enabled' => true,
        'max_per_user' => 50,
    ],

    'access_logs' => [
        // Access logging
        'enabled' => true,
        'retention_days' => 90,
        'track_ip_address' => true,
        'track_user_agent' => true,
    ],

    'images' => [
        // Image settings
        'max_file_size_mb' => 50,
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'generate_thumbnails' => true,
        'max_tags_per_image' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Viewer Templates
    |--------------------------------------------------------------------------
    |
    | Available visual templates for client-facing gallery viewers.
    | Each template has: name, description, supported types, and Google Fonts.
    | slug => config. The 'default' template is always available (no config needed).
    |
    | Adding a new template:
    |   1. Add a Blade file at resources/views/viewer/{type}/{slug}.blade.php
    |   2. Register it below with name, description, types, and fonts.
    |
    */

    'viewer_templates' => [
        'portrait' => [
            'name'        => 'Portrait',
            'description' => 'Two-column tall cards. Intimate & warm. Best for headshots and portraits.',
            'types'       => ['presentation', 'proofing'],
            'fonts'       => ['Playfair Display', 'Lato'],
        ],
        'editorial' => [
            'name'        => 'Editorial',
            'description' => 'Asymmetric cinematic layout. Mixed aspect ratios with hero image.',
            'types'       => ['presentation', 'proofing'],
            'fonts'       => ['Cormorant Garamond', 'Montserrat'],
        ],
        'architectural' => [
            'name'        => 'Architectural',
            'description' => 'Three-column landscape grid. Precise and structured.',
            'types'       => ['presentation', 'proofing'],
            'fonts'       => ['Archivo', 'Inter'],
        ],
        'classic' => [
            'name'        => 'Classic',
            'description' => 'Balanced grid with inline ratings and image numbers. Built for proofing.',
            'types'       => ['proofing'],
            'fonts'       => ['Libre Baskerville', 'Source Sans 3'],
        ],
        'profile' => [
            'name'        => 'Profile',
            'description' => 'Centered header with portfolio grid. Great for personal branding.',
            'types'       => ['presentation', 'proofing'],
            'fonts'       => ['DM Sans', 'DM Serif Display'],
        ],
        'single-column' => [
            'name'        => 'Single Column',
            'description' => 'Full-width vertical stack. Cinematic and editorial focus.',
            'types'       => ['presentation', 'proofing'],
            'fonts'       => ['Instrument Serif', 'Work Sans'],
        ],
    ],

    'asset_spine' => [
        // Canonical path for new gallery media writes.
        'write_enabled' => (bool) env('GALLERY_ASSET_SPINE_WRITE_ENABLED', true),

        // If true, legacy fallback is allowed when asset write fails.
        'write_fail_open' => (bool) env('GALLERY_ASSET_SPINE_WRITE_FAIL_OPEN', false),

        // Phase 4 read switch: when enabled, image resources prefer asset-backed paths/URLs.
        'read_switch' => (bool) env('GALLERY_ASSET_SPINE_READ_SWITCH', false),
    ],
];
