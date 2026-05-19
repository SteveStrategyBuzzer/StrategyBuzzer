<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerQuestionHistory extends Model
{
    protected $table = 'player_question_history';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'question_group_id',
        'question_intent_key',
        'mode',
        'played_at',
    ];

    protected $casts = [
        'user_id'           => 'integer',
        'question_group_id' => 'integer',
        'played_at'         => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function questionGroup()
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public static function getRecentIntentKeys(int $userId, int $days = 30): array
    {
        return self::where('user_id', $userId)
            ->whereNotNull('question_intent_key')
            ->where('played_at', '>=', now()->subDays($days))
            ->pluck('question_intent_key')
            ->unique()
            ->values()
            ->toArray();
    }

    public static function record(int $userId, ?int $groupId, ?string $intentKey, string $mode): void
    {
        self::create([
            'user_id'             => $userId,
            'question_group_id'   => $groupId,
            'question_intent_key' => $intentKey,
            'mode'                => $mode,
            'played_at'           => now(),
        ]);
    }
}
