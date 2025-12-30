<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGameTeam extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_game_id',
        'name',
        'color',
        'team_order',
        'max_players'
    ];

    // Relations
    public function game()
    {
        return $this->belongsTo(MasterGame::class, 'master_game_id');
    }

    public function players()
    {
        return $this->hasMany(MasterGamePlayer::class, 'team_id');
    }
}
