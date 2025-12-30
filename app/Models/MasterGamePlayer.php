<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGamePlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_game_id',
        'user_id',
        'master_game_code_id',
        'side',
        'score',
        'answered',
        'status',
        'team_id',
        'seat_index',
        'is_captain'
    ];

    protected $casts = [
        'answered' => 'array',
        'is_captain' => 'boolean'
    ];

    // Relations
    public function game()
    {
        return $this->belongsTo(MasterGame::class, 'master_game_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function code()
    {
        return $this->belongsTo(MasterGameCode::class, 'master_game_code_id');
    }

    public function team()
    {
        return $this->belongsTo(MasterGameTeam::class, 'team_id');
    }
}
