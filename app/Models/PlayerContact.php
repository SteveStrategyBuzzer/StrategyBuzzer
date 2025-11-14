<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerContact extends Model
{
    protected $fillable = [
        'user_id',
        'contact_user_id',
        'matches_played_together',
        'matches_won',
        'matches_lost',
        'decisive_rounds_played',
        'decisive_rounds_won',
        'last_played_at',
    ];

    protected $casts = [
        'last_played_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contact_user_id');
    }

    public function getDecisiveRoundsPercentageAttribute(): ?float
    {
        if ($this->decisive_rounds_played === 0) {
            return null;
        }

        return round(($this->decisive_rounds_won / $this->decisive_rounds_played) * 100, 1);
    }

    public function getDecisiveRoundsStatsFormattedAttribute(): string
    {
        if ($this->decisive_rounds_played === 0) {
            return 'Aucune';
        }

        $percentage = $this->decisive_rounds_percentage;
        $won = $this->decisive_rounds_won;
        $lost = $this->decisive_rounds_played - $this->decisive_rounds_won;

        return "{$percentage}% - {$won}V / {$lost}D";
    }

    public function getWinRateAttribute(): float
    {
        $totalMatches = $this->matches_won + $this->matches_lost;
        if ($totalMatches === 0) {
            return 0;
        }

        return round(($this->matches_won / $totalMatches) * 100, 1);
    }

    public function getLastPlayedFormattedAttribute(): string
    {
        if (!$this->last_played_at) {
            return 'Jamais';
        }

        return $this->last_played_at->diffForHumans();
    }
}
