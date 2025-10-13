<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserQuestProgress extends Model
{
    use HasFactory;

    protected $table = 'user_quest_progress';

    protected $fillable = [
        'user_id',
        'quest_id',
        'current_value',
        'completed',
        'claimed',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'boolean',
        'claimed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quest()
    {
        return $this->belongsTo(Quest::class);
    }

    public function getProgressPercentageAttribute()
    {
        if ($this->quest->target_value == 0) return 100;
        return min(100, ($this->current_value / $this->quest->target_value) * 100);
    }
}
