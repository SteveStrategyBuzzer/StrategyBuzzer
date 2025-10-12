<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueIndividualMatch extends Model
{
    protected $fillable = [
        'player1_id',
        'player2_id',
        'winner_id',
        'status',
        'player1_level',
        'player2_level',
        'game_state',
        'player1_points_earned',
        'player2_points_earned',
    ];

    protected $casts = [
        'game_state' => 'array',
    ];

    public function player1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player1_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'player2_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function isPlayerInMatch(int $userId): bool
    {
        return $this->player1_id === $userId || $this->player2_id === $userId;
    }

    public function getOpponent(int $userId): ?User
    {
        if ($this->player1_id === $userId) {
            return $this->player2;
        } elseif ($this->player2_id === $userId) {
            return $this->player1;
        }
        return null;
    }
}
