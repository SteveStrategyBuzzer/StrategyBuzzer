<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionIntent extends Model
{
    use HasFactory;

    protected $table = 'question_intents';

    protected $fillable = [
        // Identité noyau
        'intent_key',
        'semantic_key',
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

        // Tracking dialyse
        'dialysis_status',
        'dialysed_at',
        'locked_at',
        'locked_by',

        // Variantes
        'variantes_present',
        'variantes_missing',
        'variantes_count',

        // Notes structurées
        'dialysis_summary',
        'dialysis_last_issue',
        'dialysis_action_count',
    ];

    protected $casts = [
        'difficulty_depth'       => 'integer',
        'dialysed_at'            => 'datetime',
        'locked_at'              => 'datetime',
        'variantes_present'      => 'array',
        'variantes_missing'      => 'array',
        'variantes_count'        => 'integer',
        'dialysis_action_count'  => 'integer',
        // Kernel Blueprint Frame
        'frame_en'               => 'array',
        'frame_validated_at'     => 'datetime',
    ];

    public function questionGroups()
    {
        return $this->hasMany(QuestionGroup::class, 'question_intent_id');
    }

    /**
     * The 5 target variant combos for a complete noyau.
     */
    public static function targetVariants(): array
    {
        return [
            'qcm/recognition',
            'qcm/reasoning',
            'qcm/deceptive_trap',
            'true_false/recognition',
            'true_false/reasoning',
        ];
    }

    /**
     * Compute and write variantes_present / variantes_missing / variantes_count
     * from the current question_groups state. Does NOT save — call ->save() after.
     */
    public function refreshVariantState(): static
    {
        $present = $this->questionGroups()
            ->where('post_review_status', 'ready_bank')
            ->get()
            ->map(fn ($g) => $g->question_type . '/' . $g->cognitive_type)
            ->unique()
            ->values()
            ->all();

        $missing = array_values(array_diff(self::targetVariants(), $present));

        $this->variantes_present = $present;
        $this->variantes_missing = $missing;
        $this->variantes_count   = count($present);

        return $this;
    }

    /**
     * True if all 5 variants are present and ready.
     */
    public function isComplete(): bool
    {
        return count($this->variantes_missing ?? self::targetVariants()) === 0
            && $this->variantes_count >= 5;
    }

    /**
     * Increment dialysis_action_count and save.
     */
    public function incrementAction(): void
    {
        $this->increment('dialysis_action_count');
    }
}
