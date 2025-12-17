<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeagueTeamMatch extends Model
{
    protected $fillable = [
        'team1_id',
        'team2_id',
        'team1_level',
        'team2_level',
        'winner_team_id',
        'status',
        'game_mode',
        'player_order',
        'duel_pairings',
        'current_player_index',
        'game_state',
        'team1_points_earned',
        'team2_points_earned',
    ];

    protected $casts = [
        'game_state' => 'array',
        'player_order' => 'array',
        'duel_pairings' => 'array',
        'team1_level' => 'integer',
        'team2_level' => 'integer',
        'team1_points_earned' => 'integer',
        'team2_points_earned' => 'integer',
        'current_player_index' => 'integer',
    ];

    const MODE_CLASSIQUE = 'classique';
    const MODE_BATAILLE = 'bataille';
    const MODE_RELAIS = 'relais';

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function winnerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'winner_team_id');
    }

    public function isTeamInMatch(int $teamId): bool
    {
        return $this->team1_id === $teamId || $this->team2_id === $teamId;
    }

    public function isPlayerInMatch(int $userId): bool
    {
        $team1Members = $this->team1->members()->pluck('users.id')->toArray();
        $team2Members = $this->team2->members()->pluck('users.id')->toArray();
        
        return in_array($userId, $team1Members) || in_array($userId, $team2Members);
    }

    public function getPlayerTeam(int $userId): ?Team
    {
        if ($this->team1->members()->where('users.id', $userId)->exists()) {
            return $this->team1;
        }
        
        if ($this->team2->members()->where('users.id', $userId)->exists()) {
            return $this->team2;
        }
        
        return null;
    }

    public function getOpponentTeam(int $userId): ?Team
    {
        if ($this->team1->members()->where('users.id', $userId)->exists()) {
            return $this->team2;
        }
        
        if ($this->team2->members()->where('users.id', $userId)->exists()) {
            return $this->team1;
        }
        
        return null;
    }
}
