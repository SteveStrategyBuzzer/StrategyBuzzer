<?php

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\Log;

/**
 * Lightweight wrapper around the bank for single-question lookups.
 *
 * The bank is the nominal path for every multi-question match (planned via
 * MatchQuestionPlanner). This picker handles the rare unitary cases (legacy
 * QuestionService::generateQuestion calls, replays, single Boss probes) by
 * resolving the same profile the planner would and asking the repository
 * for one matching group.
 *
 * Returns null when no group is available for the requested combination —
 * caller is responsible for falling back (legacy path goes IA → seed).
 */
class QuestionBankPicker
{
    private QuestionBankRepository $repo;
    private MatchQuestionPlanner $planner;

    public function __construct(?QuestionBankRepository $repo = null, ?MatchQuestionPlanner $planner = null)
    {
        $this->repo = $repo ?? new QuestionBankRepository();
        $this->planner = $planner ?? new MatchQuestionPlanner($this->repo);
    }

    /**
     * Pick a single question matching the legacy (theme, niveau, language) inputs.
     *
     * @param  string  $theme            domain name (or 'general') from the legacy caller
     * @param  int     $niveau           1-100 (Solo / Boss numbering)
     * @param  string  $language
     * @param  array   $excludedGroupIds optional list of `qb_<id>` strings or numeric IDs
     * @param  string|null  $cognitiveTypeHint optional hint forcing a specific cognitive type
     * @return array|null  formatted question (same shape as MatchQuestionPlanner outputs) or null
     */
    public function pickOne(
        string $theme,
        int $niveau,
        string $language,
        array $excludedGroupIds = [],
        ?string $cognitiveTypeHint = null
    ): ?array {
        $config = config('question_bank_profiles');

        try {
            $resolved = $this->planner->resolveModeTarget('solo', $niveau, $config);
        } catch (\InvalidArgumentException $e) {
            return null;
        }

        $depthRange = $this->resolveDepthRange($resolved, $config);
        $domainList = $this->resolveDomainList($theme, $config);

        $excludeIds = [];
        foreach ($excludedGroupIds as $eid) {
            if (is_int($eid) || (is_string($eid) && ctype_digit($eid))) {
                $excludeIds[] = (int) $eid;
                continue;
            }
            if (is_string($eid) && str_starts_with($eid, 'qb_')) {
                $excludeIds[] = (int) substr($eid, 3);
            }
        }

        $cognitiveType = $cognitiveTypeHint ?? $this->randomCognitiveTypeFor($resolved, $config);

        $filters = [
            'mode_target' => $resolved,
            'depth_range' => $depthRange,
            'cognitive_type' => $cognitiveType,
            'domain' => $domainList,
            'exclude_group_ids' => $excludeIds,
            'language' => $language,
            'require_validated' => true,
        ];

        $group = $this->repo->pickOne($filters);
        if (!$group) {
            // Try without cognitive constraint, then without validated requirement.
            $group = $this->repo->pickOne(array_merge($filters, ['cognitive_type' => null]));
        }
        if (!$group) {
            $group = $this->repo->pickOne(array_merge($filters, [
                'cognitive_type' => null,
                'require_validated' => false,
            ]));
        }

        if (!$group) {
            return null;
        }

        Log::info('[QuestionBankPicker] served group', [
            'group_id' => $group->id,
            'cognitive_type' => $group->cognitive_type,
            'depth' => $group->difficulty_depth,
            'language' => $language,
            'theme' => $theme,
            'niveau' => $niveau,
        ]);

        return $this->materialise($group, $language);
    }

    private function resolveDepthRange(array $resolvedTarget, array $config): array
    {
        if ($resolvedTarget['type'] === 'boss') {
            $depth = $config['boss_profiles'][(int) $resolvedTarget['level']]['depth'];
            return [$depth, $depth];
        }
        if (isset($resolvedTarget['depth_range'])) {
            return $resolvedTarget['depth_range'];
        }
        return [3, 10];
    }

    private function resolveDomainList(string $domain, array $config): array
    {
        $normalised = strtolower($domain);
        if ($normalised === 'general' || $normalised === 'général') {
            return $config['general_sub_domains'];
        }
        return [$domain];
    }

    private function randomCognitiveTypeFor(array $resolvedTarget, array $config): ?string
    {
        $mix = ($resolvedTarget['type'] === 'boss')
            ? ($config['boss_profiles'][(int) $resolvedTarget['level']]['mix'] ?? null)
            : $config['student_cognitive_mix'];

        if (!$mix) {
            return null;
        }

        $roll = mt_rand(1, max(1, array_sum($mix)));
        $cumulative = 0;
        foreach ($mix as $type => $pct) {
            $cumulative += $pct;
            if ($roll <= $cumulative) {
                return $type;
            }
        }
        return array_key_first($mix);
    }

    private function materialise($group, string $language): array
    {
        $translation = $group->translationFor($language)
            ?? $group->translationFor('fr')
            ?? $group->translationFor('en')
            ?? $group->translations()->first();

        if (!$translation) {
            throw new \RuntimeException("QuestionGroup #{$group->id} has no translation");
        }

        $answers = $translation->answersList();
        $correctIndex = $translation->correctIndex();
        if ($correctIndex >= count($answers)) {
            $correctIndex = 0;
        }

        return [
            'id' => 'qb_' . $group->id,
            'group_id' => $group->id,
            'type' => $group->question_type === 'true_false' ? 'true_false' : 'multiple',
            'question_text' => $translation->question_text,
            'answers' => $answers,
            'correct_index' => $correctIndex,
            'correct_id' => $correctIndex,
            'explanation' => $translation->explanation,
            'saviez_vous' => $translation->saviez_vous,
            'theme' => $group->domain,
            'sub_theme' => $group->sub_domain,
            'cognitive_type' => $group->cognitive_type,
            'difficulty_depth' => $group->difficulty_depth,
            'concept_id' => $group->concept_id,
            'language' => $translation->language,
            'source' => 'bank',
        ];
    }
}
