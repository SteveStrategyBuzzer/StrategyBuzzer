<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MasterGameQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'master_game_id',
        'question_number',
        'type',
        'text',
        'choices',
        'correct_indexes',
        'media_url',
        'is_tiebreaker'
    ];

    protected $casts = [
        'choices' => 'array',
        'correct_indexes' => 'array',
        'is_tiebreaker' => 'boolean'
    ];

    // Accesseurs pour compatibilité avec la vue
    public function getQuestionTextAttribute()
    {
        return $this->text;
    }
    
    public function getQuestionImageAttribute()
    {
        return $this->media_url;
    }
    
    public function getAnswersAttribute()
    {
        return $this->choices ?? [];
    }
    
    public function getCorrectAnswerAttribute()
    {
        return $this->correct_indexes[0] ?? 0;
    }

    // Relations
    public function game()
    {
        return $this->belongsTo(MasterGame::class, 'master_game_id');
    }
}
