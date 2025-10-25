<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'condition',
        'reward_coins',
        'rarity',
        'badge_emoji',
        'badge_description',
        'detection_code',
        'detection_params',
        'auto_complete',
    ];

    protected $casts = [
        'detection_params' => 'array',
        'auto_complete' => 'boolean',
    ];

    public function userProgress()
    {
        return $this->hasMany(UserQuestProgress::class);
    }

    public function getUserProgress($userId)
    {
        return $this->userProgress()->where('user_id', $userId)->first();
    }
    
    // Vérifier si la quête est complétée par un utilisateur
    public function isCompletedBy($userId)
    {
        $progress = $this->getUserProgress($userId);
        return $progress && $progress->completed_at !== null;
    }
}
