<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Coin Packs Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the available coin packs for purchase with real money.
    | Each pack includes a key, number of coins, and price in cents.
    | 
    | Pricing structure with progressive discounts:
    | - Base rate: $0.021429/coin (calculated from 10,000 coins @ $150 with 30% discount)
    | - 100 coins: No discount (base rate)
    | - 500 coins: 10% discount
    | - 1200 coins: 20% discount
    | - 2500+ coins: 30% discount (maximum)
    |
    */

    'packs' => [
        [
            'key' => 'starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'amount_cents' => 214,  // $2.14 (base rate: $0.021429/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'standard',
            'name' => 'Pack Standard',
            'coins' => 500,
            'amount_cents' => 964,  // $9.64 (10% discount: $0.019286/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'amount_cents' => 2057,  // $20.57 (20% discount: $0.017143/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'amount_cents' => 3750,  // $37.50 (30% discount: $0.015/coin)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'amount_cents' => 7500,  // $75.00 (30% discount: $0.015/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'supreme',
            'name' => 'Pack Suprême',
            'coins' => 10000,
            'amount_cents' => 15000,  // $150.00 (30% discount: $0.015/coin)
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
