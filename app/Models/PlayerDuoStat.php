<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDuoStat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'total_matches',
        'victories',
        'defeats',
        'level',
        'win_rate',
        'current_streak',
        'best_win_streak',
        'best_lose_streak',
    ];

    protected $casts = [
        'win_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function updateAfterMatch(bool $won): void
    {
        $this->total_matches++;
        
        if ($won) {
            $this->victories++;
            $this->level = $this->victories; // Niveau = nombre de victoires
            $this->current_streak = max(0, $this->current_streak) + 1;
            $this->best_win_streak = max($this->best_win_streak, $this->current_streak);
        } else {
            $this->defeats++;
            $this->current_streak = min(0, $this->current_streak) - 1;
            $this->best_lose_streak = max($this->best_lose_streak, abs($this->current_streak));
        }

        $this->win_rate = $this->total_matches > 0 
            ? ($this->victories / $this->total_matches) * 100 
            : 0;

        $this->save();
    }

    public function hasPlayedEnoughForLeague(): bool
    {
        return $this->total_matches >= 100;
    }

    public function getLeagueInitialLevel(): int
    {
        return $this->victories;
    }
}
