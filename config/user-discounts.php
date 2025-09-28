<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | The user model class that will be used for discount assignments.
    | This should be the fully qualified class name of your User model.
    |
    */
    'user_model' => env('USER_DISCOUNT_USER_MODEL', 'App\Models\User'),

    /*
    |--------------------------------------------------------------------------
    | Stacking Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for how discounts are stacked and applied.
    |
    */
    'stacking' => [
        /*
        |--------------------------------------------------------------------------
        | Stacking Order
        |--------------------------------------------------------------------------
        |
        | The order in which discounts are applied. Lower numbers are applied first.
        | This affects the final calculation when multiple discounts are stacked.
        |
        */
        'order' => [
            'percentage' => 1,
            'fixed' => 2,
        ],

        /*
        |--------------------------------------------------------------------------
        | Maximum Percentage Cap
        |--------------------------------------------------------------------------
        |
        | The maximum percentage discount that can be applied in total.
        | This prevents excessive discounting even when multiple percentage
        | discounts are stacked.
        |
        */
        'max_percentage_cap' => env('USER_DISCOUNT_MAX_PERCENTAGE_CAP', 100),

        /*
        |--------------------------------------------------------------------------
        | Allow Negative Final Amount
        |--------------------------------------------------------------------------
        |
        | Whether the final amount can be negative after applying all discounts.
        | If false, the final amount will be capped at 0.
        |
        */
        'allow_negative_final_amount' => env('USER_DISCOUNT_ALLOW_NEGATIVE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rounding Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for how decimal values are rounded.
    |
    */
    'rounding' => [
        /*
        |--------------------------------------------------------------------------
        | Rounding Mode
        |--------------------------------------------------------------------------
        |
        | The rounding mode to use for discount calculations.
        | Options: 'round', 'floor', 'ceil'
        |
        */
        'mode' => env('USER_DISCOUNT_ROUNDING_MODE', 'round'),

        /*
        |--------------------------------------------------------------------------
        | Decimal Places
        |--------------------------------------------------------------------------
        |
        | The number of decimal places to round to.
        |
        */
        'decimal_places' => env('USER_DISCOUNT_DECIMAL_PLACES', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for handling concurrent discount applications.
    |
    */
    'concurrency' => [
        /*
        |--------------------------------------------------------------------------
        | Lock Timeout
        |--------------------------------------------------------------------------
        |
        | The timeout in seconds for acquiring locks during concurrent operations.
        |
        */
        'lock_timeout' => env('USER_DISCOUNT_LOCK_TIMEOUT', 30),

        /*
        |--------------------------------------------------------------------------
        | Retry Attempts
        |--------------------------------------------------------------------------
        |
        | The number of retry attempts for concurrent operations.
        |
        */
        'retry_attempts' => env('USER_DISCOUNT_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for discount auditing and logging.
    |
    */
    'audit' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Auditing
        |--------------------------------------------------------------------------
        |
        | Whether to enable audit logging for discount operations.
        |
        */
        'enabled' => env('USER_DISCOUNT_AUDIT_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Audit Retention
        |--------------------------------------------------------------------------
        |
        | The number of days to retain audit records. Set to null for indefinite retention.
        |
        */
        'retention_days' => env('USER_DISCOUNT_AUDIT_RETENTION_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for caching discount data.
    |
    */
    'cache' => [
        /*
        |--------------------------------------------------------------------------
        | Enable Caching
        |--------------------------------------------------------------------------
        |
        | Whether to enable caching for discount queries.
        |
        */
        'enabled' => env('USER_DISCOUNT_CACHE_ENABLED', true),

        /*
        |--------------------------------------------------------------------------
        | Cache TTL
        |--------------------------------------------------------------------------
        |
        | The time-to-live for cached discount data in minutes.
        |
        */
        'ttl' => env('USER_DISCOUNT_CACHE_TTL', 60),
    ],
];

