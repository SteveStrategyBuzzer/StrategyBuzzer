<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quest extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'description',
        'tier',
        'reward_pieces',
        'icon',
        'category',
        'requirements',
        'unlocks',
        'target_value',
        'repeatable',
        'order',
    ];

    protected $casts = [
        'requirements' => 'array',
        'unlocks' => 'array',
        'repeatable' => 'boolean',
    ];

    public function userProgress()
    {
        return $this->hasMany(UserQuestProgress::class);
    }

    public function getUserProgress($userId)
    {
        return $this->userProgress()->where('user_id', $userId)->first();
    }
}
