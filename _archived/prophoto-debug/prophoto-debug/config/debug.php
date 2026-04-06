<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable or disable debug tracing. When disabled, no traces are recorded.
    | Set INGEST_DEBUG=true in your .env file to enable.
    |
    */
    'enabled' => env('INGEST_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Retention Period
    |--------------------------------------------------------------------------
    |
    | Number of days to keep debug traces before auto-cleanup.
    | The debug:cleanup command uses this value.
    |
    */
    'retention_days' => (int) env('INGEST_DEBUG_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Trace Types
    |--------------------------------------------------------------------------
    |
    | Enable or disable specific trace types. Set to false to skip recording
    | certain trace types for performance or noise reduction.
    |
    */
    'trace_types' => [
        'preview_extraction' => true,
        'metadata_extraction' => true,
        'thumbnail_generation' => true,
        'enhancement' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Environment Variables to Capture
    |--------------------------------------------------------------------------
    |
    | List of environment variable names to include in config snapshots.
    | Values are captured but sensitive data should not be included.
    |
    */
    'capture_environment' => [
        'EXIFTOOL_BINARY',
        'EXIFTOOL_SPEED_MODE',
        'EXIFTOOL_TIMEOUT',
        'QUEUE_CONNECTION',
        'QUEUE_DRIVER',
        'INGEST_TEMP_DISK',
        'INGEST_FINAL_DISK',
        'INGEST_STORAGE_PATH_TEMP',
        'INGEST_STORAGE_PATH_FINAL',
    ],

    /*
    |--------------------------------------------------------------------------
    | Supervisor Config Paths
    |--------------------------------------------------------------------------
    |
    | Paths to check for Supervisor configuration files.
    |
    */
    'supervisor_paths' => [
        '/etc/supervisor/conf.d/',
        '/etc/supervisord.d/',
        storage_path('app/supervisor/'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament Integration
    |--------------------------------------------------------------------------
    |
    | Settings for the Filament admin panel integration.
    |
    */
    'filament' => [
        'enabled' => true,
        'navigation_group' => 'Debug',
        'navigation_icon' => 'heroicon-o-bug-ant',
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Images Directory
    |--------------------------------------------------------------------------
    |
    | Path to the directory containing test images for debugging.
    | Default location: /Herd-Profoto/test-images/
    |
    | This keeps test images separate from any package, making them:
    | - Accessible to all packages (sandbox, ingest, debug)
    | - Not committed to any package repository
    | - Easy to manage and reference
    |
    */
    'test_images_path' => env('DEBUG_TEST_IMAGES_PATH', base_path('../test-images')),
];
