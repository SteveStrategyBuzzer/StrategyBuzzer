<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'coins',
        'intelligence_pieces',
        'lives',
        'infinite_lives_until',
        'rank',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at'    => 'datetime',
        'password'             => 'hashed',
        'profile_settings'     => 'array',
        'infinite_lives_until' => 'datetime',
    ];

    public function playerDuoStat()
    {
        return $this->hasOne(PlayerDuoStat::class);
    }

    public function leagueIndividualStat()
    {
        return $this->hasOne(LeagueIndividualStat::class);
    }

    public function playerDivisions()
    {
        return $this->hasMany(PlayerDivision::class);
    }

    public function duoMatchesAsPlayer1()
    {
        return $this->hasMany(DuoMatch::class, 'player1_id');
    }

    public function duoMatchesAsPlayer2()
    {
        return $this->hasMany(DuoMatch::class, 'player2_id');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function captainedTeams()
    {
        return $this->hasMany(Team::class, 'captain_id');
    }

    public function teamInvitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function getDuoStats()
    {
        return $this->playerDuoStat ?? PlayerDuoStat::create([
            'user_id' => $this->id,
            'level' => 0,
        ]);
    }

    public function getDivisionForMode(string $mode)
    {
        return $this->playerDivisions()
            ->where('mode', $mode)
            ->first();
    }
}
