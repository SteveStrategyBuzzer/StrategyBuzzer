<?php

return [
    'enabled' => env('ADS_ENABLED', true),

    'provider' => env('ADS_PROVIDER', 'google'),

    'banner' => [
        'enabled' => true,
        'position' => 'bottom',
    ],

    'rewarded' => [
        'enabled' => true,
        'max_per_day' => 3,
        'reward' => [
            'type'   => 'competence',
            'amount' => 10,
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
