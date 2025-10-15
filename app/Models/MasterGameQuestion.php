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
        'media_url'
    ];

    protected $casts = [
        'choices' => 'array',
        'correct_indexes' => 'array'
    ];

    // Relations
    public function game()
    {
        return $this->belongsTo(MasterGame::class, 'master_game_id');
    }
}
