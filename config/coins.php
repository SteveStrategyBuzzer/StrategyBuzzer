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
    |
    | Même valeur nominale dans toutes les devises.
    |
    */

    'intelligence_packs' => [
        [
            'key' => 'intelligence_starter',
            'name' => 'Pack Starter',
            'coins' => 50,
            'prices' => [
                'usd' => 999,
                'eur' => 999,
                'cad' => 999,
                'gbp' => 999,
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
                'cad' => 1799,
                'gbp' => 1799,
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
                'cad' => 3199,
                'gbp' => 3199,
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
                'cad' => 6499,
                'gbp' => 6499,
            ],
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces de Compétence
    |--------------------------------------------------------------------------
    |
    | Même valeur nominale dans toutes les devises.
    |
    */

    'competence_packs' => [
        [
            'key' => 'competence_starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'prices' => [
                'usd' => 99,
                'eur' => 99,
                'cad' => 99,
                'gbp' => 99,
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
                'cad' => 399,
                'gbp' => 399,
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
                'cad' => 899,
                'gbp' => 899,
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
                'cad' => 1699,
                'gbp' => 1699,
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
                'cad' => 2999,
                'gbp' => 2999,
            ],
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prix des Modes de Jeu
    |--------------------------------------------------------------------------
    |
    | Même valeur nominale dans toutes les devises.
    |
    */

    'mode_prices' => [
        'duo' => [
            'usd' => 1250,
            'eur' => 1250,
            'cad' => 1250,
            'gbp' => 1250,
        ],
        'league' => [
            'usd' => 1575,
            'eur' => 1575,
            'cad' => 1575,
            'gbp' => 1575,
        ],
        'master' => [
            'usd' => 2999,
            'eur' => 2999,
            'cad' => 2999,
            'gbp' => 2999,
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
