<?php

return [
    'name' => 'Warehouse',

    /*
    |--------------------------------------------------------------------------
    | WooCommerce API Configuration
    |--------------------------------------------------------------------------
    */
    'woocommerce' => [
        'store_url' => env('WOOCOMMERCE_STORE_URL', ''),
        'consumer_key' => env('WOOCOMMERCE_CONSUMER_KEY', ''),
        'consumer_secret' => env('WOOCOMMERCE_CONSUMER_SECRET', ''),
        'webhook_secret' => env('WOOCOMMERCE_WEBHOOK_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sync Settings
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'auto_sync_enabled' => env('WAREHOUSE_AUTO_SYNC', false),
        'sync_interval_minutes' => env('WAREHOUSE_SYNC_INTERVAL', 15),
        'sync_days_back' => env('WAREHOUSE_SYNC_DAYS', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Order Settings
    |--------------------------------------------------------------------------
    */
    'orders' => [
        'per_page' => 20,
        'default_internal_status' => 'new',
    ],
];
