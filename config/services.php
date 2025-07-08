<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | GPS Services Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for GPS-related services including GPServer and MININTER
    | endpoints for the GPS proxy system.
    |
    */

    'gpserver' => [
        'base_url' => env('GPSERVER_BASE_URL', 'https://www.gipies.pe/api/api.php'),
        'timeout' => env('GPSERVER_TIMEOUT', 30),
        'connect_timeout' => env('GPSERVER_CONNECT_TIMEOUT', 10),
        'retry_attempts' => env('GPSERVER_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('GPSERVER_RETRY_DELAY', 100),
    ],

    'mininter' => [
        'serenazgo_endpoint' => env('MININTER_SERENAZGO_ENDPOINT', 'https://transmision.mininter.gob.pe/retransmisionGPS/ubicacionGPS'),
        'policial_endpoint' => env('MININTER_POLICIAL_ENDPOINT', 'https://transmision.mininter.gob.pe/retransmisionpolicial/ubicacion/gps-policial'),
        'timeout' => env('MININTER_TIMEOUT', 30),
        'connect_timeout' => env('MININTER_CONNECT_TIMEOUT', 10),
        'retry_attempts' => env('MININTER_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('MININTER_RETRY_DELAY', 1000),
        'max_retries' => env('MININTER_MAX_RETRIES', 5),
        'backoff_multiplier' => env('MININTER_BACKOFF_MULTIPLIER', 2),
        'verify_ssl' => env('MININTER_VERIFY_SSL', true),
        'ssl_version' => env('MININTER_SSL_VERSION', 'TLSv1.2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GPS Proxy System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the GPS proxy system behavior, validation rules,
    | and operational parameters.
    |
    */

    'gps_proxy' => [
        'sync_interval' => env('GPS_SYNC_INTERVAL', 60), // seconds
        'batch_size' => env('GPS_BATCH_SIZE', 100),
        'max_processing_time' => env('GPS_MAX_PROCESSING_TIME', 300), // seconds
        'enable_validation' => env('GPS_ENABLE_VALIDATION', true),
        'enable_peru_bounds_check' => env('GPS_ENABLE_PERU_BOUNDS_CHECK', true),
        'enable_health_checks' => env('GPS_ENABLE_HEALTH_CHECKS', true),
        'health_check_interval' => env('GPS_HEALTH_CHECK_INTERVAL', 300), // seconds
        'log_retention_days' => env('GPS_LOG_RETENTION_DAYS', 30),
        'enable_metrics' => env('GPS_ENABLE_METRICS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Transformation Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data transformation and validation rules.
    |
    */

    'data_transformation' => [
        'coordinate_precision' => env('GPS_COORDINATE_PRECISION', 6),
        'datetime_format' => env('GPS_DATETIME_FORMAT', 'd/m/Y H:i:s'),
        'timezone' => env('GPS_TIMEZONE', 'America/Lima'),
        'max_speed_kmh' => env('GPS_MAX_SPEED_KMH', 500),
        'max_future_hours' => env('GPS_MAX_FUTURE_HOURS', 1),
        'min_year' => env('GPS_MIN_YEAR', 2000),
        'peru_bounds' => [
            'lat_min' => env('GPS_PERU_LAT_MIN', -18.4),
            'lat_max' => env('GPS_PERU_LAT_MAX', 0.0),
            'lng_min' => env('GPS_PERU_LNG_MIN', -81.4),
            'lng_max' => env('GPS_PERU_LNG_MAX', -68.7),
        ],
    ],

];
