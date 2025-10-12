<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueIndividualStat extends Model
{
    protected $fillable = [
        'user_id',
        'level',
        'matches_played',
        'matches_won',
        'matches_lost',
        'total_points',
        'initialized',
    ];

    protected $casts = [
        'initialized' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function winRate(): float
    {
        if ($this->matches_played == 0) {
            return 0;
        }
        return round(($this->matches_won / $this->matches_played) * 100, 1);
    }

    public function initializeFromDuo(int $duoLevel): void
    {
        $this->level = $duoLevel;
        $this->initialized = true;
        $this->save();
    }
}
