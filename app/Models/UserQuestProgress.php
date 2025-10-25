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
        'progress',
        'completed_at',
        'rewarded',
    ];

    protected $casts = [
        'progress' => 'array',
        'completed_at' => 'datetime',
        'rewarded' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function quest()
    {
        return $this->belongsTo(Quest::class);
    }

    // Vérifier si la quête est complétée
    public function isCompleted()
    {
        return $this->completed_at !== null;
    }
    
    // Vérifier si la récompense a été donnée
    public function isRewarded()
    {
        return $this->rewarded === true;
    }
}
