<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mapping Langue → Devise
    |--------------------------------------------------------------------------
    |
    | Détermine la devise à utiliser selon la langue du joueur.
    | Les langues européennes (fr, es, it, de, pt, el) utilisent l'EUR.
    | L'anglais utilise USD. Les autres (ru, ar, zh) utilisent USD par défaut
    | car les devises locales (RUB, SAR, CNY) sont moins stables.
    |
    */

    'language_currency_map' => [
        'fr' => 'eur',
        'en' => 'usd',
        'es' => 'eur',
        'it' => 'eur',
        'de' => 'eur',
        'pt' => 'eur',
        'el' => 'eur',
        'ru' => 'usd',
        'ar' => 'usd',
        'zh' => 'usd',
    ],

    /*
    |--------------------------------------------------------------------------
    | Symboles et formats de devises
    |--------------------------------------------------------------------------
    */

    'currency_symbols' => [
        'usd' => '$',
        'eur' => '€',
    ],

    'currency_format' => [
        'usd' => ['symbol' => '$', 'position' => 'before', 'decimal' => '.'],
        'eur' => ['symbol' => '€', 'position' => 'after', 'decimal' => ','],
    ],

    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces d'Intelligence
    |--------------------------------------------------------------------------
    |
    | Pièces gagnées en Multijoueur (Duo, Ligue, Master) car vous prouvez
    | vos connaissances face à d'autres joueurs. Aussi achetables avec Stripe.
    |
    | Les prix sont harmonisés entre EUR et USD pour être équitables.
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
            ],
            'amount_cents' => 999,
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'intelligence_standard',
            'name' => 'Pack Standard',
            'coins' => 100,
            'prices' => [
                'usd' => 1799,
                'eur' => 1799,
            ],
            'amount_cents' => 1799,
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'intelligence_pro',
            'name' => 'Pack Pro',
            'coins' => 200,
            'prices' => [
                'usd' => 3199,
                'eur' => 3199,
            ],
            'amount_cents' => 3199,
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'intelligence_mega',
            'name' => 'Pack Mega',
            'coins' => 500,
            'prices' => [
                'usd' => 6499,
                'eur' => 6499,
            ],
            'amount_cents' => 6499,
            'currency' => 'usd',
            'popular' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces de Compétence
    |--------------------------------------------------------------------------
    |
    | Pièces gagnées en Solo et Quêtes car vous débloquez des skills/compétences.
    | Utilisées pour acheter des avatars stratégiques dans la boutique.
    | Aussi achetables avec Stripe. Prix progressifs avec économies sur les gros packs.
    |
    | Les prix sont harmonisés entre EUR et USD pour être équitables.
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
            ],
            'amount_cents' => 99,
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'competence_popular',
            'name' => 'Pack Populaire',
            'coins' => 500,
            'prices' => [
                'usd' => 399,
                'eur' => 399,
            ],
            'amount_cents' => 399,
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'competence_pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'prices' => [
                'usd' => 899,
                'eur' => 899,
            ],
            'amount_cents' => 899,
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'competence_mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'prices' => [
                'usd' => 1699,
                'eur' => 1699,
            ],
            'amount_cents' => 1699,
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'competence_ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'prices' => [
                'usd' => 2999,
                'eur' => 2999,
            ],
            'amount_cents' => 2999,
            'currency' => 'usd',
            'popular' => false,
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
