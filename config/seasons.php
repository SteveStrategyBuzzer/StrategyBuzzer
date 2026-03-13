<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Season Reward Configuration
    |--------------------------------------------------------------------------
    | Two-layer reward system per division:
    |   Layer 1 (Universal): All players above threshold get intelligence coins
    |   Layer 2 (Promotion): Top 10 (+ ties) get promoted to next division
    |--------------------------------------------------------------------------
    */

    'divisions' => [
        'bronze' => [
            'name'            => 'Bronze',
            'points_threshold' => 50,
            'coins_reward'    => 100,
            'exclusive_frame' => false,
        ],
        'argent' => [
            'name'            => 'Argent',
            'points_threshold' => 50,
            'coins_reward'    => 150,
            'exclusive_frame' => false,
        ],
        'or' => [
            'name'            => 'Or',
            'points_threshold' => 50,
            'coins_reward'    => 200,
            'exclusive_frame' => false,
        ],
        'platine' => [
            'name'            => 'Platine',
            'points_threshold' => 60,
            'coins_reward'    => 500,
            'exclusive_frame' => false,
        ],
        'diamant' => [
            'name'            => 'Diamant',
            'points_threshold' => 70,
            'coins_reward'    => 1000,
            'exclusive_frame' => false,
        ],
        'legende' => [
            'name'            => 'Légende',
            'points_threshold' => 80,
            'coins_reward'    => 2000,
            'exclusive_frame' => true,
        ],
    ],

    /*
    | Number of top players promoted at season end.
    | All players tied at the cutoff rank are also promoted.
    */
    'top_promotion_count' => 10,

    /*
    | Default season duration in days (used when creating new seasons).
    */
    'season_duration_days' => 90,
];
