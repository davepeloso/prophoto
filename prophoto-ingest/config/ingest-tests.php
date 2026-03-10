<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Test Suite Switchboard
    |--------------------------------------------------------------------------
    | Control which test suites are enabled. Each suite can be toggled on/off.
    */
    'switchboard' => [
        'code_quality'  => env('TEST_CODE_QUALITY', true),
        'unit'          => env('TEST_UNIT', true),
        'feature'       => env('TEST_FEATURE', true),
        'integration'   => env('TEST_INTEGRATION', true),
        'jobs'          => env('TEST_JOBS', true),
        'e2e'           => env('TEST_E2E', false),
        'performance'   => env('TEST_PERFORMANCE', false),
        'security'      => env('TEST_SECURITY', true),
        'migrations'    => env('TEST_MIGRATIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Data Paths
    |--------------------------------------------------------------------------
    | Configurable paths for fixtures, sample images, and test artifacts.
    */
    'paths' => [
        'fixtures'      => base_path('tests/fixtures/images'),
        'raw_samples'   => storage_path('framework/testing/raw'),
        'performance'   => storage_path('framework/testing/performance'),
        'temp_disk'     => storage_path('framework/testing/temp'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Profiles
    |--------------------------------------------------------------------------
    | Pre-configured test suite combinations for different scenarios.
    */
    'profiles' => [
        // Fast local development - only essential tests
        'fast-dev' => [
            'code_quality'  => false,
            'unit'          => true,
            'feature'       => true,
            'integration'   => false,
            'jobs'          => false,
            'e2e'           => false,
            'performance'   => false,
            'security'      => false,
            'migrations'    => false,
        ],

        // Pre-commit - quick smoke test
        'pre-commit' => [
            'code_quality'  => true,
            'unit'          => true,
            'feature'       => false,
            'integration'   => false,
            'jobs'          => false,
            'e2e'           => false,
            'performance'   => false,
            'security'      => false,
            'migrations'    => false,
        ],

        // Full CI - everything
        'full-ci' => [
            'code_quality'  => true,
            'unit'          => true,
            'feature'       => true,
            'integration'   => true,
            'jobs'          => true,
            'e2e'           => true,
            'performance'   => true,
            'security'      => true,
            'migrations'    => true,
        ],

        // Pre-release - comprehensive but no performance tests
        'pre-release' => [
            'code_quality'  => true,
            'unit'          => true,
            'feature'       => true,
            'integration'   => true,
            'jobs'          => true,
            'e2e'           => true,
            'performance'   => false,
            'security'      => true,
            'migrations'    => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Thresholds
    |--------------------------------------------------------------------------
    | Maximum acceptable times for performance tests.
    */
    'performance' => [
        'upload_max_time'       => 500,  // ms
        'thumbnail_max_time'    => 200,  // ms
        'metadata_extract_time' => 100,  // ms
        'ingest_job_max_time'   => 2000, // ms per image
        'batch_upload_count'    => 50,   // number of images
    ],
];
