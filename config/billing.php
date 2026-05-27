<?php

return [
    'default_provider' => env('BILLING_DEFAULT_PROVIDER', 'stripe'),

    // Require idempotency keys on mutation endpoints to prevent duplicate writes.
    'idempotency' => [
        'ttl_seconds' => (int) env('BILLING_IDEMPOTENCY_TTL_SECONDS', 600),
    ],

    'webhooks' => [
        'tolerance_seconds' => (int) env('BILLING_WEBHOOK_TOLERANCE_SECONDS', 300),

        'providers' => [
            'stripe' => [
                'signing_secret' => env('STRIPE_WEBHOOK_SECRET'),
            ],
            'paypal' => [
                'signing_secret' => env('PAYPAL_WEBHOOK_SECRET'),
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

