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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],

    'geolocation' => [
        'enabled' => env('GEOLOCATION_ENABLED', true),
        'endpoint' => env('GEOLOCATION_ENDPOINT', 'https://ipapi.co/{ip}/json/'),
        'timeout' => env('GEOLOCATION_TIMEOUT', 2),
        'reverse_geocode_endpoint' => env('GEOLOCATION_REVERSE_GEOCODE_ENDPOINT', 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat={lat}&lon={lng}'),
        'default_country_code' => env('GEOLOCATION_DEFAULT_COUNTRY_CODE', 'NG'),
        'default_state' => env('GEOLOCATION_DEFAULT_STATE', 'Lagos State'),
        'default_city' => env('GEOLOCATION_DEFAULT_CITY', 'Lagos'),
        'reverse_match_distance_km' => env('GEOLOCATION_REVERSE_MATCH_DISTANCE_KM', 75),
        'browser_max_accuracy_meters' => env('GEOLOCATION_BROWSER_MAX_ACCURACY_METERS', 25000),
        'browser_location_ttl_minutes' => env('GEOLOCATION_BROWSER_LOCATION_TTL_MINUTES', 180),
    ],

];
