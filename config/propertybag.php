<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for property bag settings to improve performance.
    | When enabled, settings will be cached to avoid database queries.
    |
    */
    'cache' => [
        'enabled' => env('PROPERTY_BAG_CACHE_ENABLED', true),
        
        // Cache duration in seconds (default: 24 hours)
        'duration' => env('PROPERTY_BAG_CACHE_DURATION', 86400),
        
        // Cache store to use (null uses default cache store)
        'store' => env('PROPERTY_BAG_CACHE_STORE', null),
    ],
];