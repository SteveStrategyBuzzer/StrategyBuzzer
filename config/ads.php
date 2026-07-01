<?php

return [
    'enabled' => env('ADS_ENABLED', false),

    'provider' => env('ADS_PROVIDER', 'google_ad_manager'),

    'test_mode' => env('ADS_TEST_MODE', true),

    'banner' => [
        'enabled' => false,
        'position' => 'bottom',
    ],

    'rewarded' => [
        'enabled' => env('ADS_REWARDED_ENABLED', false),
        'max_per_day' => 3,
        'rewards' => [
            'competence'   => ['type' => 'competence',   'amount' => 10],
            'intelligence' => ['type' => 'intelligence', 'amount' => 5],
        ],
    ],

    'allowed_banner_routes' => [
        'menu',
        'boutique',
        'boutique.category',
        'profile.show',
        'statistics',
        'quetes',
        'quetes-quotidiennes',
        'duo.lobby',
        'duo.result',
        'duo.rankings',
        'league.individual.lobby',
        'league.individual.results',
        'league.individual.rankings',
        'league.team.lobby',
        'league.team.results',
        'victory',
        'defeat',
        'game.master.match-result',
        'invitations',
    ],
];
