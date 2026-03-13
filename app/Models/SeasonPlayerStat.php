<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeasonPlayerStat extends Model
{
    protected $fillable = [
        'season_id',
        'user_id',
        'mode',
        'division_at_start',
        'division_at_end',
        'season_points',
        'matches_played',
        'reward_coins_distributed',
        'coins_awarded',
        'promoted',
        'exclusive_frame_awarded',
    ];

    protected $casts = [
        'reward_coins_distributed' => 'boolean',
        'promoted' => 'boolean',
        'exclusive_frame_awarded' => 'boolean',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
