<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerAvatarStat extends Model
{
    protected $fillable = [
        'user_id',
        'avatar_slug',
        'matches_played',
        'avg_buzz_ms',
        'accuracy_rate',
    ];

    protected $casts = [
        'accuracy_rate' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
