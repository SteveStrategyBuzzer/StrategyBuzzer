<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces de Compétence
    |--------------------------------------------------------------------------
    |
    | Packs de pièces disponibles à l'achat avec Stripe.
    | Prix progressifs avec économies sur les gros packs.
    |
    | - 50 pièces : $10.00 ($0.20/pièce)
    | - 100 pièces : $18.00 ($0.18/pièce - 10% économie)
    | - 200 pièces : $32.00 ($0.16/pièce - 20% économie)
    | - 500 pièces : $65.00 ($0.13/pièce - 35% économie)
    |
    */

    'packs' => [
        [
            'key' => 'starter',
            'name' => 'Pack Starter',
            'coins' => 50,
            'amount_cents' => 1000,  // $10.00
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'standard',
            'name' => 'Pack Standard',
            'coins' => 100,
            'amount_cents' => 1800,  // $18.00 (10% économie)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'pro',
            'name' => 'Pack Pro',
            'coins' => 200,
            'amount_cents' => 3200,  // $32.00 (20% économie)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'mega',
            'name' => 'Pack Mega',
            'coins' => 500,
            'amount_cents' => 6500,  // $65.00 (35% économie)
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
