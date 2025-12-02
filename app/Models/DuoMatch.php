<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DuoMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'player1_id',
        'player2_id',
        'status',
        'match_type',
        'player1_score',
        'player2_score',
        'winner_id',
        'theme',
        'player1_level',
        'player2_level',
        'player1_points_earned',
        'player2_points_earned',
        'game_state',
        'lobby_code',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'game_state' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
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

    public function isFinished(): bool
    {
        return $this->status === 'finished';
    }

    public function isPlaying(): bool
    {
        return $this->status === 'playing';
    }
}
