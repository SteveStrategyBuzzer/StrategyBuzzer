<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    protected $fillable = [
        'name',
        'mode',
        'starts_at',
        'ends_at',
        'status',
        'rewards_distributed_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'rewards_distributed_at' => 'datetime',
    ];

    public function playerStats(): HasMany
    {
        return $this->hasMany(SeasonPlayerStat::class);
    }

    public static function activeSeason(string $mode = 'all'): ?self
    {
        return static::where('status', 'active')
            ->where(function ($q) use ($mode) {
                $q->where('mode', $mode)->orWhere('mode', 'all');
            })
            ->orderByDesc('starts_at')
            ->first();
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && now()->between($this->starts_at, $this->ends_at);
    }

    public function daysRemaining(): int
    {
        return max(0, (int) now()->diffInDays($this->ends_at, false));
    }
}
