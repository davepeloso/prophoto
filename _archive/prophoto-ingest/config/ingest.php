<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    | These settings are developer-only and configured via environment variables.
    */
    'route_prefix' => env('INGEST_ROUTE_PREFIX', 'ingest'),
    'middleware' => ['web', 'auth'],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    | Storage disks and paths are developer-only settings configured via ENV.
    */
    'storage' => [
        // Where temporary uploads/proxies are stored during the session
        'temp_disk' => env('INGEST_TEMP_DISK', 'public'),
        'temp_path' => env('INGEST_STORAGE_PATH_TEMP', 'ingest-temp'),

        // Where final processed images are stored after ingest
        'final_disk' => env('INGEST_FINAL_DISK', 'local'),
        'final_path' => env('INGEST_STORAGE_PATH_FINAL', 'images'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ingest Schema Configuration
    |--------------------------------------------------------------------------
    | Defines how files are renamed and organized during final ingest.
    | Available variables:
    |   {date:FORMAT} - Date from EXIF (e.g., {date:Y}/{date:m}/{date:d})
    |   {camera}      - Camera make (slugified)
    |   {model}       - Camera model (slugified)
    |   {sequence}    - Auto-incrementing number (padded)
    |   {original}    - Original filename (without extension)
    |   {uuid}        - Unique proxy image identifier
    |   {project}     - Project tag name (slugified) - requires Project tag
    |   {filename}    - Filename tag name (slugified) - requires Filename tag
    */
    'schema' => [
        // Path pattern for organizing files
        // Example with camera: shoots/2025/01/Canon-EOS-R5/
        // Example with project: shoots/2025/01/123-Main-Street/
        'path' => 'shoots/{date:Y}/{date:m}/{camera}',

        // Filename pattern
        // Example with sequence: 001-IMG_1234.jpg
        // Example with filename tag: 001-Living-Room.jpg
        'filename' => '{sequence}-{original}',

        // Starting sequence number
        'sequence_start' => 1,

        // Sequence padding (001 vs 1)
        'sequence_padding' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | ExifTool Configuration
    |--------------------------------------------------------------------------
    | Settings for the ExifTool-based metadata and preview extraction service.
    | ExifTool provides robust, fast metadata extraction for all image formats.
    */
    'exiftool' => [
        // Path to exiftool binary (use full path in production for security)
        'binary' => env('EXIFTOOL_BINARY', 'exiftool'),

        // Default timeout in seconds for ExifTool operations
        'timeout' => env('EXIFTOOL_TIMEOUT', 30),

        // Speed mode: 'fast' (recommended), 'fast2' (aggressive), or 'full'
        // - 'fast': Skips non-essential processing, good balance of speed/completeness
        // - 'fast2': More aggressive, may skip some metadata fields
        // - 'full': Complete extraction, slower but thorough
        'speed_mode' => env('EXIFTOOL_SPEED_MODE', 'fast'),

        // Include group names in output (e.g., "EXIF:Make" instead of "Make")
        'include_groups' => false,

        // Default options for all ExifTool calls
        'default_options' => [
            '-j',                     // JSON output
            '-n',                     // Numeric values
            '-charset', 'filename=UTF8',
            '-api', 'QuickTimeUTC=1', // Consistent timezone handling
        ],

        // Preview extraction tag priority (tried in order)
        'preview_tags' => [
            'PreviewImage',    // High-quality preview (Sony, Nikon)
            'JpgFromRaw',      // Embedded JPEG (Canon CR2)
            'ThumbnailImage',  // Fallback thumbnail
        ],

        // Maximum preview size in bytes (larger previews will be flagged for downscaling)
        'max_preview_size' => 8 * 1024 * 1024, // 8MB

        // Fallback to PHP exif functions if ExifTool unavailable
        'fallback_to_php' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | EXIF Extraction Configuration
    |--------------------------------------------------------------------------
    */
    'exif' => [
        // Keys to extract into dedicated database columns for fast filtering
        'denormalize_keys' => [
            'DateTimeOriginal' => 'date_taken',
            'Make' => 'camera_make',
            'Model' => 'camera_model',
            'FNumber' => 'f_stop',
            'ISOSpeedRatings' => 'iso',
            'ExposureTime' => 'shutter_speed',
            'FocalLength' => 'focal_length',
            'LensModel' => 'lens',
            'GPSLatitude' => 'gps_lat',
            'GPSLongitude' => 'gps_lng',
        ],

        // Generate thumbnails during proxy upload (for grid view)
        'thumbnail' => [
            'enabled' => true,
            'width' => 400,
            'height' => 400,
            'quality' => 80,
        ],

        // Generate preview images during proxy upload (for preview panel)
        // This creates a medium-sized version for fast preview without loading full originals
        'preview' => [
            'enabled' => true,
            'max_dimension' => 2048, // Maximum width or height in pixels
            'quality' => 85,
        ],

        // Optional: Resize original images during final ingest
        // Set enabled=true to create resized finals, false to keep originals as-is
        'final' => [
            'enabled' => false,
            'max_dimension' => null, // null = keep original size, or set max dimension
            'quality' => 95,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup Configuration
    |--------------------------------------------------------------------------
    */
    'cleanup' => [
        // Auto-delete abandoned proxy uploads after X hours
        'proxy_ttl_hours' => 48,

        // Run cleanup on a schedule (requires scheduler setup)
        'schedule_cleanup' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset Spine Transition Flags
    |--------------------------------------------------------------------------
    | Canonical controls for writing accepted uploads into prophoto-assets.
    | Defaults are enabled for new development in this workspace.
    */
    'asset_spine' => [
        // Phase 3: enable additive asset writes at staging acceptance seam.
        'dual_write' => (bool) env('INGEST_ASSET_SPINE_DUAL_WRITE', true),

        // If true, dual-write failures should not block legacy ingest->gallery flow.
        'fail_open' => (bool) env('INGEST_ASSET_SPINE_FAIL_OPEN', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Association
    |--------------------------------------------------------------------------
    | Allow images to be associated with models in the host application
    */
    'associations' => [
        'enabled' => true,
        // Example: 'App\Models\Shoot', 'App\Models\Project'
        'models' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tagging Configuration
    |--------------------------------------------------------------------------
    | Quick tags and tag source models from host app
    */
    'tagging' => [
        // Predefined quick-access tags (user-editable in settings UI)
        'quick_tags' => [],

        // Models from host app that can provide tag suggestions
        'sources' => [
            // 'Clients' => 'App\Models\Client',
            // 'Events' => 'App\Models\Event',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Metadata & Chart Configuration
    |--------------------------------------------------------------------------
    | Settings for metadata display and chart fields
    */
    'metadata' => [
        // Fields to display in charts (user-editable in settings UI)
        'chart_fields' => ['iso', 'aperture', 'shutterSpeed', 'focalLength'],

        // Default chart field for single-field displays
        'default_chart_field' => 'iso',
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance Configuration
    |--------------------------------------------------------------------------
    | UI customization settings - developer-only via environment variables.
    | Theme mode: 'light', 'dark', or 'system' (follows OS preference)
    */
    'appearance' => [
        // Theme mode: light, dark, or system
        'theme_mode' => env('INGEST_THEME_MODE', 'system'),

        // Light mode colors
        'light' => [
            'accent_color' => env('INGEST_LIGHT_ACCENT', '#3b82f6'),
            'background' => env('INGEST_LIGHT_BG', '#ffffff'),
            'foreground' => env('INGEST_LIGHT_FG', '#0f172a'),
        ],

        // Dark mode colors
        'dark' => [
            'accent_color' => env('INGEST_DARK_ACCENT', '#60a5fa'),
            'background' => env('INGEST_DARK_BG', '#0f172a'),
            'foreground' => env('INGEST_DARK_FG', '#f8fafc'),
        ],

        // Shared appearance settings
        'border_radius' => (int) env('INGEST_RADIUS', 8),
        'spacing_scale' => env('INGEST_SPACING', 'medium'),
    ],
];
