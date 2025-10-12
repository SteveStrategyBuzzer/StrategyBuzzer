<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Team extends Model
{
    protected $fillable = [
        'name',
        'tag',
        'captain_id',
        'division',
        'points',
        'level',
        'matches_played',
        'matches_won',
        'matches_lost',
        'is_recruiting',
    ];

    protected $casts = [
        'is_recruiting' => 'boolean',
        'points' => 'integer',
        'level' => 'integer',
        'matches_played' => 'integer',
        'matches_won' => 'integer',
        'matches_lost' => 'integer',
    ];

    public function captain(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captain_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function matchesAsTeam1(): HasMany
    {
        return $this->hasMany(LeagueTeamMatch::class, 'team1_id');
    }

    public function matchesAsTeam2(): HasMany
    {
        return $this->hasMany(LeagueTeamMatch::class, 'team2_id');
    }

    public function isFull(): bool
    {
        return $this->members()->count() >= 5;
    }

    public function isMember(int $userId): bool
    {
        return $this->members()->where('users.id', $userId)->exists();
    }

    public function isCaptain(int $userId): bool
    {
        return $this->captain_id === $userId;
    }

    public function canAddMember(): bool
    {
        return !$this->isFull();
    }

    public function getWinRate(): float
    {
        if ($this->matches_played === 0) {
            return 0.0;
        }
        return ($this->matches_won / $this->matches_played) * 100;
    }
}
