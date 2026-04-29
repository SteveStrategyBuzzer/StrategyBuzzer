<?php

namespace App\Services\QuestionBank;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single point of access for question_groups + question_translations.
 *
 * - Reads with rich filters (level/boss + cognitive + depth + domain
 *   + sub_domain + exclusions).
 * - Writes deduplicated by `concept_id` (idempotent insert).
 * - Counters updated asynchronously by IncrementQuestionUsageJob (not here).
 */
class QuestionBankRepository
{
    /**
     * Pick one question group matching the given filters. Returns the group
     * loaded with its translation in $language (or fr/en fallback if missing).
     *
     * @param  array  $filters  {
     *   - mode_target: ['type' => 'solo_range'|'boss', 'levels' => [from, to], 'level' => int]
     *   - depth_range: [from, to]
     *   - cognitive_type: 'recognition'|'reasoning'|'deceptive_trap'
     *   - domain: string|array<string>          (single domain or list when "Général" expands)
     *   - sub_domain?: string|array<string>     (optional restriction)
     *   - question_type?: 'qcm'|'true_false'    (optional)
     *   - exclude_group_ids?: array<int>
     *   - exclude_concept_ids?: array<string>
     *   - deprioritize_concept_families?: array<string>
     *   - language: string                       (used to require translation availability)
     *   - require_validated?: bool               (default true)
     * }
     * @return QuestionGroup|null
     */
    public function pickOne(array $filters): ?QuestionGroup
    {
        $query = $this->baseQuery($filters);

        $query->orderBy('question_groups.usage_count', 'asc')
              ->orderByRaw('question_groups.last_used_at NULLS FIRST')
              ->inRandomOrder()
              ->limit(1);

        $row = $query->first();
        if (!$row) {
            return null;
        }

        return QuestionGroup::find($row->id);
    }

    /**
     * Count groups matching filters. Used by stats + worker depth checks.
     */
    public function countMatching(array $filters): int
    {
        // Honour the `language` filter when present: a group without a translation
        // for the requested language must NOT be counted as available for that
        // language. Without this, the dry-run availability tables and the
        // worker's deficit targeting both report inflated multilingual coverage.
        $withJoin = !empty($filters['language']);
        return $this->baseQuery($filters, withTranslationJoin: $withJoin)->count();
    }

    /**
     * Insert (or upsert by concept_id) a new question group with its translations.
     *
     * Returns the saved QuestionGroup, or null if rejected (duplicate concept_id
     * already in DB and `update_existing=false`).
     *
     * @param  array  $payload  {
     *   - difficulty_level?, boss_level?: int
     *   - difficulty_depth: int (1-10)
     *   - domain, sub_domain: string
     *   - question_type: 'qcm'|'true_false'
     *   - cognitive_type: 'recognition'|'reasoning'|'deceptive_trap'
     *   - concept_id?, concept_family?: string
     *   - source: string ('seed', 'gemini', 'openai', 'manual')
     *   - validated: bool
     *   - translations: array<string, array{
     *         question_text, answer_a, answer_b, answer_c?, answer_d?,
     *         correct_answer_key, explanation?, saviez_vous?
     *     }>
     * }
     * @param  bool  $updateExisting  If true and concept_id collision, refresh translations.
     */
    public function addToBank(array $payload, bool $updateExisting = false): ?QuestionGroup
    {
        $conceptId = $payload['concept_id'] ?? null;
        $existing = null;
        if ($conceptId !== null && $conceptId !== '') {
            $existing = QuestionGroup::where('concept_id', $conceptId)->first();
            if ($existing && !$updateExisting) {
                Log::debug('[QuestionBankRepository] Skipping duplicate concept_id', [
                    'concept_id' => $conceptId,
                    'existing_group_id' => $existing->id,
                ]);
                return null;
            }
        }

        return DB::transaction(function () use ($payload, $existing) {
            $groupAttributes = [
                'difficulty_level' => $payload['difficulty_level'] ?? null,
                'boss_level' => $payload['boss_level'] ?? null,
                'difficulty_depth' => (int) $payload['difficulty_depth'],
                'domain' => $payload['domain'],
                'sub_domain' => $payload['sub_domain'],
                'question_type' => $payload['question_type'] ?? 'qcm',
                'cognitive_type' => $payload['cognitive_type'],
                'concept_id' => $payload['concept_id'] ?? null,
                'concept_family' => $payload['concept_family'] ?? null,
                'source' => $payload['source'] ?? 'seed',
                'validated' => (bool) ($payload['validated'] ?? false),
            ];

            if ($existing) {
                $existing->fill($groupAttributes)->save();
                $group = $existing;
            } else {
                $group = QuestionGroup::create($groupAttributes);
            }

            foreach ($payload['translations'] ?? [] as $language => $translation) {
                QuestionTranslation::updateOrCreate(
                    [
                        'question_group_id' => $group->id,
                        'language' => $language,
                    ],
                    [
                        'question_text' => $translation['question_text'],
                        'answer_a' => $translation['answer_a'],
                        'answer_b' => $translation['answer_b'],
                        'answer_c' => $translation['answer_c'] ?? null,
                        'answer_d' => $translation['answer_d'] ?? null,
                        'correct_answer_key' => strtoupper($translation['correct_answer_key']),
                        'explanation' => $translation['explanation'] ?? null,
                        'saviez_vous' => $translation['saviez_vous'] ?? null,
                    ]
                );
            }

            return $group;
        });
    }

    /**
     * Aggregated depth report. Returns rows with totals grouped by tuple.
     *
     * @return array<int, array<string,mixed>>
     */
    public function depthReport(?string $language = null, ?string $domain = null): array
    {
        $query = DB::table('question_groups')
            ->select(
                'difficulty_level', 'boss_level', 'difficulty_depth',
                'domain', 'sub_domain', 'cognitive_type', 'question_type',
                DB::raw('COUNT(*) as group_count'),
                DB::raw('SUM(CASE WHEN validated THEN 1 ELSE 0 END) as validated_count')
            );

        if ($language !== null) {
            $query->whereExists(function (QueryBuilder $sub) use ($language) {
                $sub->select(DB::raw(1))
                    ->from('question_translations')
                    ->whereColumn('question_translations.question_group_id', 'question_groups.id')
                    ->where('question_translations.language', $language);
            });
        }

        if ($domain !== null) {
            $query->where('domain', $domain);
        }

        return $query
            ->groupBy(
                'difficulty_level', 'boss_level', 'difficulty_depth',
                'domain', 'sub_domain', 'cognitive_type', 'question_type'
            )
            ->orderBy('difficulty_level')
            ->orderBy('boss_level')
            ->orderBy('domain')
            ->orderBy('sub_domain')
            ->orderBy('cognitive_type')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();
    }

    /**
     * Build the base query joining translations for language availability.
     */
    private function baseQuery(array $filters, bool $withTranslationJoin = true): QueryBuilder
    {
        $query = DB::table('question_groups');

        $modeTarget = $filters['mode_target'] ?? null;
        if ($modeTarget) {
            if (($modeTarget['type'] ?? null) === 'solo_range') {
                [$from, $to] = $modeTarget['levels'];
                $query->whereNotNull('difficulty_level')
                      ->whereBetween('difficulty_level', [(int) $from, (int) $to]);
            } elseif (($modeTarget['type'] ?? null) === 'boss') {
                $query->whereNotNull('boss_level')
                      ->where('boss_level', (int) $modeTarget['level']);
            }
        }

        if (!empty($filters['depth_range'])) {
            [$dFrom, $dTo] = $filters['depth_range'];
            $query->whereBetween('difficulty_depth', [(int) $dFrom, (int) $dTo]);
        }

        if (!empty($filters['cognitive_type'])) {
            $query->where('cognitive_type', $filters['cognitive_type']);
        }

        if (!empty($filters['domain'])) {
            if (is_array($filters['domain'])) {
                $query->whereIn('domain', $filters['domain']);
            } else {
                $query->where('domain', $filters['domain']);
            }
        }

        if (!empty($filters['sub_domain'])) {
            if (is_array($filters['sub_domain'])) {
                $query->whereIn('sub_domain', $filters['sub_domain']);
            } else {
                $query->where('sub_domain', $filters['sub_domain']);
            }
        }

        if (!empty($filters['question_type'])) {
            $query->where('question_type', $filters['question_type']);
        }

        if (!empty($filters['exclude_group_ids'])) {
            $query->whereNotIn('id', $filters['exclude_group_ids']);
        }

        if (!empty($filters['exclude_concept_ids'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('concept_id')
                  ->orWhereNotIn('concept_id', $filters['exclude_concept_ids']);
            });
        }

        if (!empty($filters['exclude_concept_families'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereNull('concept_family')
                  ->orWhereNotIn('concept_family', $filters['exclude_concept_families']);
            });
        }

        $requireValidated = $filters['require_validated'] ?? true;
        if ($requireValidated) {
            $query->where('validated', true);
        }

        if ($withTranslationJoin && !empty($filters['language'])) {
            $language = $filters['language'];
            $query->whereExists(function (QueryBuilder $sub) use ($language) {
                $sub->select(DB::raw(1))
                    ->from('question_translations')
                    ->whereColumn('question_translations.question_group_id', 'question_groups.id')
                    ->where('question_translations.language', $language);
            });
        }

        return $query;
    }
}
