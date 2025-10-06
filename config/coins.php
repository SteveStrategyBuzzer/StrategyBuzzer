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
    | - 100 coins: $4.99 (base rate: $0.0499/coin)
    | - 500 coins: 5% discount ($0.047405/coin)
    | - 1200 coins: 10% discount ($0.04491/coin)
    | - 2500+ coins: 15% discount ($0.042415/coin, max discount)
    |
    */

    'packs' => [
        [
            'key' => 'starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'amount_cents' => 499,  // $4.99 (base: $0.0499/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'popular',
            'name' => 'Pack Populaire',
            'coins' => 500,
            'amount_cents' => 2370,  // $23.70 (5% discount: $0.047405/coin)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'amount_cents' => 5389,  // $53.89 (10% discount: $0.04491/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'amount_cents' => 10604,  // $106.04 (15% discount: $0.042415/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'amount_cents' => 21208,  // $212.08 (15% discount: $0.042415/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'supreme',
            'name' => 'Pack Suprême',
            'coins' => 10000,
            'amount_cents' => 42415,  // $424.15 (15% discount: $0.042415/coin)
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
