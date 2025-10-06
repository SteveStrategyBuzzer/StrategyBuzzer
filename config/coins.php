<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Coin Packs Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the available coin packs for purchase with real money.
    | Each pack includes a key, number of coins, and price in cents.
    | Ratio: 100 coins = $4.99 (about $0.0499 per coin)
    |
    */

    'packs' => [
        [
            'key' => 'starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'amount_cents' => 499,  // $4.99
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'popular',
            'name' => 'Pack Populaire',
            'coins' => 500,
            'amount_cents' => 2495,  // $24.95 (500 × $0.0499)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'amount_cents' => 5988,  // $59.88 (1200 × $0.0499)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'amount_cents' => 12475,  // $124.75 (2500 × $0.0499)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'amount_cents' => 24950,  // $249.50 (5000 × $0.0499)
            'currency' => 'usd',
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    |
    | Your Stripe API keys will be loaded from environment variables.
    |
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout URLs
    |--------------------------------------------------------------------------
    |
    | URLs for Stripe Checkout success and cancel redirects.
    |
    */

    'urls' => [
        'success' => env('APP_URL') . '/coins/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel' => env('APP_URL') . '/coins/cancel',
    ],
];
