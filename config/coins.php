<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Symboles et formats de devises
    |--------------------------------------------------------------------------
    */

    'currency_symbols' => [
        'usd' => '$',
        'eur' => '€',
        'cad' => 'CA$',
        'gbp' => '£',
    ],

    'currency_format' => [
        'usd' => ['symbol' => '$', 'position' => 'before', 'decimal' => '.'],
        'eur' => ['symbol' => '€', 'position' => 'after', 'decimal' => ','],
        'cad' => ['symbol' => 'CA$', 'position' => 'before', 'decimal' => '.'],
        'gbp' => ['symbol' => '£', 'position' => 'before', 'decimal' => '.'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces d'Intelligence
    |--------------------------------------------------------------------------
    */

    'intelligence_packs' => [
        [
            'key' => 'intelligence_starter',
            'name' => 'Pack Starter',
            'coins' => 50,
            'prices' => [
                'usd' => 999,
                'eur' => 999,
                'cad' => 1349,
                'gbp' => 799,
            ],
            'popular' => false,
        ],
        [
            'key' => 'intelligence_standard',
            'name' => 'Pack Standard',
            'coins' => 100,
            'prices' => [
                'usd' => 1799,
                'eur' => 1799,
                'cad' => 2449,
                'gbp' => 1449,
            ],
            'popular' => false,
        ],
        [
            'key' => 'intelligence_pro',
            'name' => 'Pack Pro',
            'coins' => 200,
            'prices' => [
                'usd' => 3199,
                'eur' => 3199,
                'cad' => 4349,
                'gbp' => 2599,
            ],
            'popular' => true,
        ],
        [
            'key' => 'intelligence_mega',
            'name' => 'Pack Mega',
            'coins' => 500,
            'prices' => [
                'usd' => 6499,
                'eur' => 6499,
                'cad' => 8799,
                'gbp' => 5249,
            ],
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces de Compétence
    |--------------------------------------------------------------------------
    */

    'competence_packs' => [
        [
            'key' => 'competence_starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'prices' => [
                'usd' => 99,
                'eur' => 99,
                'cad' => 129,
                'gbp' => 79,
            ],
            'popular' => false,
        ],
        [
            'key' => 'competence_popular',
            'name' => 'Pack Populaire',
            'coins' => 500,
            'prices' => [
                'usd' => 399,
                'eur' => 399,
                'cad' => 549,
                'gbp' => 329,
            ],
            'popular' => true,
        ],
        [
            'key' => 'competence_pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'prices' => [
                'usd' => 899,
                'eur' => 899,
                'cad' => 1199,
                'gbp' => 729,
            ],
            'popular' => false,
        ],
        [
            'key' => 'competence_mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'prices' => [
                'usd' => 1699,
                'eur' => 1699,
                'cad' => 2299,
                'gbp' => 1379,
            ],
            'popular' => false,
        ],
        [
            'key' => 'competence_ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'prices' => [
                'usd' => 2999,
                'eur' => 2999,
                'cad' => 3999,
                'gbp' => 2399,
            ],
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prix des Modes de Jeu
    |--------------------------------------------------------------------------
    */

    'mode_prices' => [
        'duo' => [
            'usd' => 1250,
            'eur' => 1250,
            'cad' => 1699,
            'gbp' => 999,
        ],
        'league' => [
            'usd' => 1250,
            'eur' => 1250,
            'cad' => 1699,
            'gbp' => 999,
        ],
        'master' => [
            'usd' => 1500,
            'eur' => 1500,
            'cad' => 1999,
            'gbp' => 1199,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe Configuration
    |--------------------------------------------------------------------------
    */

    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Checkout URLs
    |--------------------------------------------------------------------------
    */

    'urls' => [
        'success' => env('APP_URL') . '/coins/success?session_id={CHECKOUT_SESSION_ID}',
        'cancel' => env('APP_URL') . '/coins/cancel',
    ],
];
