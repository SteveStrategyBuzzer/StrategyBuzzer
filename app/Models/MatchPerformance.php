<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchPerformance extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id',
        'game_mode',
        'game_id',
        'performance',
        'rounds_played',
        'is_victory',
        'played_at',
    ];
    
    protected $casts = [
        'performance' => 'decimal:2',
        'is_victory' => 'boolean',
        'played_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public static function getLast10Matches(int $userId, string $gameMode = null)
    {
        $query = self::where('user_id', $userId);
        
        if ($gameMode) {
            $query->where('game_mode', $gameMode);
        }
        
        return $query->orderBy('played_at', 'desc')
            ->limit(10)
            ->get();
    }
    
    public static function getAverageLast10(int $userId, string $gameMode): float
    {
        $matches = self::getLast10Matches($userId, $gameMode);
        
        if ($matches->isEmpty()) {
            return 0;
        }
        
        return round($matches->avg('performance'), 2);
    }
    
    public static function getLast10Stats(int $userId, string $gameMode): array
    {
        $matches = self::getLast10Matches($userId, $gameMode);
        
        if ($matches->isEmpty()) {
            return [
                'count' => 0,
                'avg_efficiency' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_ratio' => 0,
                'global_efficiency' => 0,
            ];
        }
        
        $wins = $matches->where('is_victory', true)->count();
        $losses = $matches->where('is_victory', false)->count();
        $avgEfficiency = round($matches->avg('performance'), 2);
        $winRatio = $matches->count() > 0 ? round(($wins / $matches->count()) * 100, 2) : 0;
        
        $globalEfficiency = round(($avgEfficiency + $winRatio) / 2, 2);
        
        return [
            'count' => $matches->count(),
            'avg_efficiency' => $avgEfficiency,
            'wins' => $wins,
            'losses' => $losses,
            'win_ratio' => $winRatio,
            'global_efficiency' => $globalEfficiency,
        ];
    }
}
