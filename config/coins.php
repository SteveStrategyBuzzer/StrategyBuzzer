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
    | - 500 coins: 10% discount ($0.04491/coin)
    | - 1200 coins: 20% discount ($0.03992/coin)
    | - 2500+ coins: 30% discount ($0.03493/coin, max discount)
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
            'key' => 'standard',
            'name' => 'Pack Standard',
            'coins' => 500,
            'amount_cents' => 2246,  // $22.46 (10% discount: $0.04491/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'amount_cents' => 4790,  // $47.90 (20% discount: $0.03992/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'amount_cents' => 8733,  // $87.33 (30% discount: $0.03493/coin)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'amount_cents' => 17465,  // $174.65 (30% discount: $0.03493/coin)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'supreme',
            'name' => 'Pack Suprême',
            'coins' => 10000,
            'amount_cents' => 15000,  // $150.00 ($0.015/coin, 66.7 coins per dollar)
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
