<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchQuestionHistory extends Model
{
    protected $table = 'match_question_history';

    public $timestamps = false;

    protected $fillable = [
        'match_ref',
        'question_group_id',
        'question_intent_key',
        'round',
        'question_number',
        'served_at',
    ];

    protected $casts = [
        'question_group_id' => 'integer',
        'round'             => 'integer',
        'question_number'   => 'integer',
        'served_at'         => 'datetime',
    ];

    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public static function getMatchIntentKeys(string $matchRef): array
    {
        return self::where('match_ref', $matchRef)
            ->whereNotNull('question_intent_key')
            ->pluck('question_intent_key')
            ->unique()
            ->values()
            ->toArray();
    }

    public static function record(
        string $matchRef,
        ?int $groupId,
        ?string $intentKey,
        ?int $round = null,
        ?int $questionNumber = null
    ): void {
        self::create([
            'match_ref'           => $matchRef,
            'question_group_id'   => $groupId,
            'question_intent_key' => $intentKey,
            'round'               => $round,
            'question_number'     => $questionNumber,
            'served_at'           => now(),
        ]);
    }
}
