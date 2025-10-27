<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerStatistic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'game_mode',
        'scope',
        'game_id',
        'round_number',
        'total_questions',
        'questions_buzzed',
        'correct_answers',
        'wrong_answers',
        'points_earned',
        'points_possible',
        'efficacite_brute',
        'taux_participation',
        'taux_precision',
        'ratio_performance',
        'details',
    ];

    protected $casts = [
        'details' => 'array',
        'efficacite_brute' => 'decimal:2',
        'taux_participation' => 'decimal:2',
        'taux_precision' => 'decimal:2',
        'ratio_performance' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
