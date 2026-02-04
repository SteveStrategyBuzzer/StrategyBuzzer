<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Packs de Pièces d'Intelligence
    |--------------------------------------------------------------------------
    |
    | Pièces gagnées en Multijoueur (Duo, Ligue, Master) car vous prouvez
    | vos connaissances face à d'autres joueurs. Aussi achetables avec Stripe.
    |
    */

    'intelligence_packs' => [
        [
            'key' => 'intelligence_starter',
            'name' => 'Pack Débutant',
            'coins' => 100,
            'amount_cents' => 99,  // 0.99 (devise détectée automatiquement)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'intelligence_popular',
            'name' => 'Pack Populaire',
            'coins' => 500,
            'amount_cents' => 399,  // 3.99 (devise détectée automatiquement)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'intelligence_pro',
            'name' => 'Pack Pro',
            'coins' => 1200,
            'amount_cents' => 899,  // 8.99 (devise détectée automatiquement)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'intelligence_mega',
            'name' => 'Pack Mega',
            'coins' => 2500,
            'amount_cents' => 1699,  // 16.99 (devise détectée automatiquement)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'intelligence_ultimate',
            'name' => 'Pack Ultimate',
            'coins' => 5000,
            'amount_cents' => 2999,  // 29.99 (devise détectée automatiquement)
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
    */

    'competence_packs' => [
        [
            'key' => 'competence_starter',
            'name' => 'Pack Starter',
            'coins' => 50,
            'amount_cents' => 1000,  // 10.00 (devise détectée automatiquement)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'competence_standard',
            'name' => 'Pack Standard',
            'coins' => 100,
            'amount_cents' => 1800,  // 18.00 (10% économie)
            'currency' => 'usd',
            'popular' => false,
        ],
        [
            'key' => 'competence_pro',
            'name' => 'Pack Pro',
            'coins' => 200,
            'amount_cents' => 3200,  // 32.00 (20% économie)
            'currency' => 'usd',
            'popular' => true,
        ],
        [
            'key' => 'competence_mega',
            'name' => 'Pack Mega',
            'coins' => 500,
            'amount_cents' => 6500,  // 65.00 (35% économie)
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
