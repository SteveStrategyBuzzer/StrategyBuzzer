<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotProfile extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'is_active',
        'bot_avatar_slug',
        'stake_enabled',
        'max_stake_per_match',
        'times_used_as_bot',
        'bot_wins',
        'bot_losses',
        'coins_earned_for_owner',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'stake_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function qualificationEvents()
    {
        return $this->hasMany(BotQualificationEvent::class, 'user_id', 'user_id');
    }

    public function qualifyingCount(): int
    {
        return BotQualificationEvent::where('user_id', $this->user_id)->count();
    }

    public function qualificationTier(): string
    {
        $count = $this->qualifyingCount();
        if ($count >= 200) return 'gold';
        if ($count >= 50)  return 'silver';
        if ($count >= 10)  return 'bronze';
        return 'none';
    }

    public function isQualified(): bool
    {
        return $this->qualifyingCount() >= 10;
    }
}
