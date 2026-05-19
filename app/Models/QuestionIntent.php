<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionIntent extends Model
{
    protected $table = 'question_intents';

    protected $fillable = [
        'intent_key',
        'language_source',
        'domain',
        'sub_domain',
        'difficulty_depth',
        'subject',
        'angle_large',
        'micro_angle',
        'answer_target',
        'potential_trap',
        'concept_family',
        'source',
    ];

    protected $casts = [
        'difficulty_depth' => 'integer',
    ];

    public function questionGroups()
    {
        return $this->hasMany(QuestionGroup::class, 'question_intent_id');
    }
}
