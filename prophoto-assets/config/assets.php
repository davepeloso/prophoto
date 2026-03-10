<?php

return [
    'storage' => [
        'disk' => env('ASSET_STORAGE_DISK', 'local'),
        'temporary_url_ttl_seconds' => (int) env('ASSET_TEMP_URL_TTL', 3600),
    ],

    'metadata' => [
        'normalizer_schema_version' => env('ASSET_METADATA_SCHEMA_VERSION', 'v1'),
        'normalizer_version' => env('ASSET_METADATA_NORMALIZER_VERSION', 'assets-normalizer-v1'),
    ],

    'features' => [
        // Canonical default: ingest writes accepted media into asset spine.
        'ingest_dual_write' => (bool) env('ASSET_INGEST_DUAL_WRITE', true),
    ],

    'tables' => [
        'assets' => 'assets',
        'asset_derivatives' => 'asset_derivatives',
        'asset_metadata_raw' => 'asset_metadata_raw',
        'asset_metadata_normalized' => 'asset_metadata_normalized',
    ],
];
