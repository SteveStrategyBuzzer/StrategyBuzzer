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
}
