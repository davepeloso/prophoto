<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ExifTool Binary Path
    |--------------------------------------------------------------------------
    |
    | The path to the exiftool binary. This should be an absolute path in
    | production to avoid PATH-related issues in PHP-FPM/Horizon/queue workers.
    |
    | Examples:
    | - 'exiftool' (searches in PATH)
    | - '/usr/local/bin/exiftool'
    | - '/opt/local/bin/exiftool'
    |
    */
    'bin' => env('EXIFTOOL_BIN', 'exiftool'),

    /*
    |--------------------------------------------------------------------------
    | PATH Prefix
    |--------------------------------------------------------------------------
    |
    | Optional directory to prepend to the PATH environment variable when
    | spawning exiftool processes. This ensures the binary is found even when
    | the PHP runtime PATH differs from the shell PATH.
    |
    | Examples:
    | - null (no PATH modification)
    | - '/usr/local/bin'
    | - '/opt/local/bin'
    |
    */
    'path_prefix' => env('EXIFTOOL_PATH_PREFIX'),
];
