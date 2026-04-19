<?php

return [
    'storefront' => [
        'enabled' => env('STOREFRONT_ANALYTICS_ENABLED', true),
        'track_authenticated_pages' => env('STOREFRONT_ANALYTICS_TRACK_AUTHENTICATED', true),
        'capture_referrers' => env('STOREFRONT_ANALYTICS_CAPTURE_REFERRERS', true),
        'raw_retention_days' => (int) env('STOREFRONT_ANALYTICS_RETENTION_DAYS', 180),
        'aggregation_refresh_window_days' => (int) env('STOREFRONT_ANALYTICS_REFRESH_WINDOW_DAYS', 7),
        'frontend_batch_size' => (int) env('STOREFRONT_ANALYTICS_BATCH_SIZE', 5),
        'frontend_flush_interval_ms' => (int) env('STOREFRONT_ANALYTICS_FLUSH_INTERVAL_MS', 2000),
        'throttle_per_minute' => (int) env('STOREFRONT_ANALYTICS_THROTTLE_PER_MINUTE', 180),
        'excluded_path_prefixes' => [
            'admin',
            'sales',
            'settings',
            'up',
            '_debugbar',
            'telescope',
        ],
        'excluded_exact_paths' => [
            '/login',
            '/logout',
            '/register',
            '/password/reset',
            '/password/forgot',
        ],
        'allowed_component_prefixes' => [
            'Welcome',
            'WelcomeOld',
            'Storefront/',
            'Checkout/',
            'Account/',
            'PublicProduct/',
        ],
        'geo_headers' => [
            'country_code' => [
                'CF-IPCountry',
                'X-Appengine-Country',
                'X-Country-Code',
                'CloudFront-Viewer-Country',
            ],
            'region_name' => [
                'X-Region-Name',
                'X-Appengine-Region',
                'CloudFront-Viewer-Country-Region-Name',
                'CloudFront-Viewer-City',
            ],
        ],
    ],
];
