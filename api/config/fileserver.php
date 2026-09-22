<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    */
    'storage_root' => env('FILESERVER_STORAGE_ROOT', storage_path('app/buckets')),

    'default_quota_bytes' => (int) env('FILESERVER_DEFAULT_QUOTA_BYTES', 10 * 1024 * 1024 * 1024),

    'default_max_file_size_bytes' => (int) env('FILESERVER_DEFAULT_MAX_FILE_SIZE_BYTES', 5 * 1024 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Presigned URLs
    |--------------------------------------------------------------------------
    */
    'presigned_url_ttl' => (int) env('FILESERVER_PRESIGNED_URL_TTL', 900),

    /*
    |--------------------------------------------------------------------------
    | Retention / soft-delete
    |--------------------------------------------------------------------------
    */
    'retention_days' => (int) env('FILESERVER_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Tokens
    |--------------------------------------------------------------------------
    */
    'token_prefix_length' => (int) env('FILESERVER_TOKEN_PREFIX_LENGTH', 8),

    /*
    |--------------------------------------------------------------------------
    | Rate limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit_api' => (int) env('FILESERVER_RATE_LIMIT_API', 60),

    'rate_limit_auth' => (int) env('FILESERVER_RATE_LIMIT_AUTH', 5),

    /*
    |--------------------------------------------------------------------------
    | Virus scanning (ClamAV)
    |--------------------------------------------------------------------------
    */
    'virus_scan' => [
        'enabled' => (bool) env('FILESERVER_VIRUS_SCAN_ENABLED', false),
        'clamav_host' => env('FILESERVER_CLAMAV_HOST', '127.0.0.1'),
        'clamav_port' => (int) env('FILESERVER_CLAMAV_PORT', 3310),
        'clamav_timeout' => (int) env('FILESERVER_CLAMAV_TIMEOUT', 30),
    ],

];
