<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionGroup extends Model
{
    use HasFactory;

    protected $table = 'question_groups';

    protected $fillable = [
        'difficulty_level',
        'boss_level',
        'difficulty_depth',
        'domain',
        'sub_domain',
        'question_type',
        'cognitive_type',
        'concept_id',
        'concept_family',
        'source',
        'validated',
        'usage_count',
        'last_used_at',
        'post_review_status',
        'question_intent_id',
        'question_intent_key',
        'subject',
        'angle_large',
        'micro_angle',
        'readable_code',
        'correction_notes',
    ];

    protected $casts = [
        'difficulty_level'   => 'integer',
        'boss_level'         => 'integer',
        'difficulty_depth'   => 'integer',
        'validated'          => 'boolean',
        'usage_count'        => 'integer',
        'last_used_at'       => 'datetime',
        'question_intent_id' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(QuestionTranslation::class, 'question_group_id');
    }

    public function questionIntent()
    {
        return $this->belongsTo(\App\Models\QuestionIntent::class, 'question_intent_id');
    }

    public function translationFor(string $language): ?QuestionTranslation
    {
        return $this->translations()->where('language', $language)->first();
    }

    /**
     * Alias for translationFor() to support legacy calls.
     */
    public function translation(string $language): ?QuestionTranslation
    {
        return $this->translationFor($language);
    }
}
