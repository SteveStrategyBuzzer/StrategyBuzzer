<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchQuestionPlan extends Model
{
    use HasFactory;

    protected $table = 'match_question_plans';

    protected $fillable = [
        'plan_uid',
        'mode',
        'division',
        'difficulty_level',
        'boss_level',
        'domain',
        'language',
        'total_questions',
        'rounds_count',
        'global_composition',
        'per_round_composition',
        'group_ids',
        'issues',
    ];

    protected $casts = [
        'difficulty_level' => 'integer',
        'boss_level' => 'integer',
        'total_questions' => 'integer',
        'rounds_count' => 'integer',
        'global_composition' => 'array',
        'per_round_composition' => 'array',
        'group_ids' => 'array',
        'issues' => 'array',
    ];
}
