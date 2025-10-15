<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGameCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_game_id',
        'code',
        'role',
        'side',
        'capacity',
        'used',
        'state'
    ];

    // Relations
    public function game()
    {
        return $this->belongsTo(MasterGame::class, 'master_game_id');
    }

    public function players()
    {
        return $this->hasMany(MasterGamePlayer::class);
    }
}
