<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerDivision extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'mode',
        'division',
        'points',
        'level',
        'rank',
        'initial_efficiency',
        'matches_won',
        'matches_lost',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getDivisionName(): string
    {
        $divisions = [
            'bronze'        => 'Bronze',
            'argent'        => 'Argent',
            'or'            => 'Or',
            'platine'       => 'Platine',
            'diamant'       => 'Diamant',
            'legende'       => 'Légende',
            'novice'        => 'Novice',
            'intermediaire' => 'Intermédiaire',
            'expert'        => 'Expert',
        ];

        return $divisions[$this->division] ?? ucfirst($this->division ?? 'Novice');
    }

    public function addPoints(int $points): void
    {
        $this->points += $points;
        
        $this->division = $this->calculateDivision($this->points);
        $this->save();
    }

    public function calculateDivision(int $totalPoints): string
    {
        if ($this->mode === 'duo') {
            if ($totalPoints < 100) return 'novice';
            if ($totalPoints < 200) return 'intermediaire';
            return 'expert';
        }
        if ($totalPoints < 100) return 'bronze';
        if ($totalPoints < 200) return 'argent';
        if ($totalPoints < 300) return 'or';
        if ($totalPoints < 400) return 'platine';
        if ($totalPoints < 500) return 'diamant';
        return 'legende';
    }
}
