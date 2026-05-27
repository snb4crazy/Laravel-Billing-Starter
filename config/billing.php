<?php

return [
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),

    'idempotency' => [
        'ttl_seconds' => (int) env('BILLING_IDEMPOTENCY_TTL_SECONDS', 600),
    ],

    // Provider-specific API credentials
    'providers' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
        ],
        'paypal' => [
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'secret' => env('PAYPAL_SECRET'),
            'base_url' => env('PAYPAL_BASE_URL', 'https://api-m.sandbox.paypal.com'),
            'return_url' => env('PAYPAL_RETURN_URL', env('APP_URL').'/billing/paypal/return'),
            'cancel_url' => env('PAYPAL_CANCEL_URL', env('APP_URL').'/billing/paypal/cancel'),
        ],
        'paddle' => [
            'vendor_id' => env('PADDLE_VENDOR_ID'),
            'api_key' => env('PADDLE_API_KEY'),
            'base_url' => env('PADDLE_API_BASE_URL', 'https://api.sandbox.paddle.com'),
        ],
    ],

    'webhooks' => [
        'tolerance_seconds' => (int) env('BILLING_WEBHOOK_TOLERANCE_SECONDS', 300),

        'providers' => [
            'stripe' => [
                'signing_secret' => env('STRIPE_WEBHOOK_SECRET'),
            ],
            'paypal' => [
                // PayPal uses webhook ID instead of a shared HMAC secret.
                'signing_secret' => env('PAYPAL_WEBHOOK_ID', env('PAYPAL_WEBHOOK_SECRET')),
            ],
            'paddle' => [
                'signing_secret' => env('PADDLE_WEBHOOK_SECRET'),
            ],
            'square' => [
                'signing_secret' => env('SQUARE_WEBHOOK_SECRET'),
            ],
        ],
    ],
];

