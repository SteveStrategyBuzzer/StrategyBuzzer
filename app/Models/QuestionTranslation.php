<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionTranslation extends Model
{
    use HasFactory;

    protected $table = 'question_translations';

    protected $fillable = [
        'question_group_id',
        'language',
        'question_text',
        'answer_a',
        'answer_b',
        'answer_c',
        'answer_d',
        'correct_answer_key',
        'explanation',
        'saviez_vous',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    /**
     * Returns answers as an ordered list [A, B, C, D] keeping nulls filtered.
     */
    public function answersList(): array
    {
        return array_values(array_filter(
            [$this->answer_a, $this->answer_b, $this->answer_c, $this->answer_d],
            fn ($a) => $a !== null && $a !== ''
        ));
    }

    /**
     * Réponses ordonnées A,B,C,D (D peut être null pour true_false).
     *
     * @return array<int, string|null>
     */
    public function answersOrdered(): array
    {
        return [
            $this->answer_a,
            $this->answer_b,
            $this->answer_c,
            $this->answer_d,
        ];
    }

    /**
     * Returns the 0-based index of the correct answer in answersList().
     */
    public function correctIndex(): int
    {
        $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3];
        return $map[$this->correct_answer_key] ?? 0;
    }
}
