<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchSnapshot extends Model
{
    protected $primaryKey = 'match_id';
    public $incrementing   = false;
    protected $keyType     = 'string';

    protected $fillable = [
        'match_id',
        'mode',
        'round_number',
        'player_scores',
        'rounds_won',
        'player_stats',
        'snapshotted_at',
    ];

    protected $casts = [
        'player_scores'  => 'array',
        'rounds_won'     => 'array',
        'player_stats'   => 'array',
        'snapshotted_at' => 'datetime',
    ];
}
