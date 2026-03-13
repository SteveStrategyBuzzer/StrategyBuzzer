<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Season Reward Configuration — Wins-Based Prize Tiers
    |--------------------------------------------------------------------------
    | wins_threshold : minimum wins to be eligible for any prize this season
    | prizes         : ranked prizes (1st, 2nd, 3rd by wins count).
    |                  All players tied at a rank share that rank's prize.
    |                  Losses are ignored — only wins count.
    |--------------------------------------------------------------------------
    */

    'divisions' => [
        'bronze' => [
            'name'           => 'Bronze',
            'wins_threshold' => 10,
            'prizes'         => [
                ['rank' => 1, 'coins' => 100, 'exclusive_frame' => false],
            ],
        ],
        'argent' => [
            'name'           => 'Argent',
            'wins_threshold' => 10,
            'prizes'         => [
                ['rank' => 1, 'coins' => 150, 'exclusive_frame' => false],
                ['rank' => 2, 'coins' => 100, 'exclusive_frame' => false],
            ],
        ],
        'or' => [
            'name'           => 'Or',
            'wins_threshold' => 10,
            'prizes'         => [
                ['rank' => 1, 'coins' => 200, 'exclusive_frame' => false],
                ['rank' => 2, 'coins' => 150, 'exclusive_frame' => false],
                ['rank' => 3, 'coins' => 100, 'exclusive_frame' => false],
            ],
        ],
        'platine' => [
            'name'           => 'Platine',
            'wins_threshold' => 12,
            'prizes'         => [
                ['rank' => 1, 'coins' => 500, 'exclusive_frame' => false],
                ['rank' => 2, 'coins' => 200, 'exclusive_frame' => false],
                ['rank' => 3, 'coins' => 100, 'exclusive_frame' => false],
            ],
        ],
        'diamant' => [
            'name'           => 'Diamant',
            'wins_threshold' => 15,
            'prizes'         => [
                ['rank' => 1, 'coins'  => 1000, 'exclusive_frame' => false],
                ['rank' => 2, 'coins'  => 500,  'exclusive_frame' => false],
                ['rank' => 3, 'coins'  => 200,  'exclusive_frame' => false],
            ],
        ],
        'legende' => [
            'name'           => 'Légende',
            'wins_threshold' => 20,
            'prizes'         => [
                ['rank' => 1, 'coins' => 2000, 'exclusive_frame' => true],
                ['rank' => 2, 'coins' => 500,  'exclusive_frame' => false],
                ['rank' => 3, 'coins' => 100,  'exclusive_frame' => false],
            ],
        ],
    ],

    /*
    | Default season duration in days (used when creating new seasons).
    */
    'season_duration_days' => 90,
];
