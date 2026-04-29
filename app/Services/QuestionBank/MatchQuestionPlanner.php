<?php

namespace App\Services\QuestionBank;

use App\Models\MatchQuestionPlan;
use App\Models\QuestionGroup;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Builds the question_plan for a match BEFORE it starts.
 *
 * Cognitive percentages from config/question_bank_profiles.php are interpreted
 * as MATCH-COMPOSITION QUOTAS, never as per-question probabilities. The
 * composition is guaranteed within ±1 per cognitive_type globally, and within
 * ±1 per cognitive_type per round whenever the bank permits.
 *
 * "Général" expands to the 8 sub-domains via balanced sub-domain quotas with
 * consecutive repetition avoidance.
 */
class MatchQuestionPlanner
{
    private QuestionBankRepository $repo;

    public function __construct(?QuestionBankRepository $repo = null)
    {
        $this->repo = $repo ?? new QuestionBankRepository();
    }

    /**
     * @param  string  $mode             'solo'|'boss'|'duo'|'mj_auto'|'ligue'
     * @param  string|int|null  $levelOrDivision  Solo/Boss numeric level (int) or Duo/MJ/Ligue division (string).
     * @param  int     $totalQuestions   Total main questions for the match (e.g. 30).
     * @param  int     $roundsCount      Number of rounds (e.g. 3).
     * @param  string  $language         Player language code (fr, en, ...).
     * @param  string  $domain           Theme: 'general' (orchestrator) or one of the 8 sub-domains.
     * @return array{
     *     plan_uid: string,
     *     mode: string,
     *     division: ?string,
     *     difficulty_level: ?int,
     *     boss_level: ?int,
     *     domain: string,
     *     language: string,
     *     total_questions: int,
     *     rounds_count: int,
     *     global_composition: array<string,int>,
     *     per_round_composition: array<int, array<string,int>>,
     *     sub_domain_distribution: array<string,int>,
     *     questions: array<int, array>,    // formatted question objects, ordered
     *     issues: array<string>,           // empty array if plan was fully realised
     * }
     */
    public function buildPlan(
        string $mode,
        $levelOrDivision,
        int $totalQuestions,
        int $roundsCount,
        string $language,
        string $domain = 'general'
    ): array {
        $config = config('question_bank_profiles');
        $resolvedTarget = $this->resolveModeTarget($mode, $levelOrDivision, $config);

        $cognitiveMix = $this->resolveCognitiveMix($resolvedTarget, $config);
        $depthRange = $this->resolveDepthRange($resolvedTarget, $config);

        $globalComposition = QuotaAllocator::allocate(
            $cognitiveMix,
            $totalQuestions,
            $config['cognitive_priority_order'] ?? []
        );

        $perRoundByRound = $this->distributeCognitiveAcrossRounds(
            $globalComposition,
            $totalQuestions,
            $roundsCount,
            $config['cognitive_priority_order'] ?? []
        );

        $domainList = $this->resolveDomainList($domain, $config);
        $subDomainQuotas = $this->buildSubDomainQuotas($domainList, $totalQuestions, $config);

        $selection = $this->selectQuestions(
            $resolvedTarget,
            $depthRange,
            $perRoundByRound,
            $subDomainQuotas,
            $domainList,
            $language,
            $totalQuestions,
            $roundsCount,
            $config
        );

        $planUid = (string) Str::uuid();

        $persisted = MatchQuestionPlan::create([
            'plan_uid' => $planUid,
            'mode' => $mode,
            'division' => is_string($levelOrDivision) ? $levelOrDivision : null,
            'difficulty_level' => $resolvedTarget['type'] === 'solo_range'
                ? (int) $resolvedTarget['levels'][0]
                : null,
            'boss_level' => $resolvedTarget['type'] === 'boss'
                ? (int) $resolvedTarget['level']
                : null,
            'domain' => $domain,
            'language' => $language,
            'total_questions' => $totalQuestions,
            'rounds_count' => $roundsCount,
            'global_composition' => $globalComposition,
            'per_round_composition' => $perRoundByRound,
            'group_ids' => array_map(fn ($g) => $g['group_id'], $selection['questions']),
            'issues' => $selection['issues'],
        ]);

        Log::info('[MatchQuestionPlanner] plan built', [
            'plan_uid' => $planUid,
            'mode' => $mode,
            'division' => $levelOrDivision,
            'composition' => $this->formatCompositionLog($globalComposition),
            'rounds' => $roundsCount,
            'language' => $language,
            'served' => count($selection['questions']),
            'issues' => $selection['issues'],
        ]);

        return [
            'plan_uid' => $planUid,
            'mode' => $mode,
            'division' => is_string($levelOrDivision) ? $levelOrDivision : null,
            'difficulty_level' => $persisted->difficulty_level,
            'boss_level' => $persisted->boss_level,
            'domain' => $domain,
            'language' => $language,
            'total_questions' => $totalQuestions,
            'rounds_count' => $roundsCount,
            'global_composition' => $globalComposition,
            'per_round_composition' => $perRoundByRound,
            'sub_domain_distribution' => $selection['sub_domain_distribution'],
            'questions' => $selection['questions'],
            'issues' => $selection['issues'],
        ];
    }

    /**
     * Project quotas only (no DB selection) — used by `questions:plan:dryrun`
     * to display what a profile would request.
     *
     * @return array{
     *     resolved_target: array,
     *     cognitive_mix: array<string,int>,
     *     depth_range: array{0:int,1:int},
     *     global_composition: array<string,int>,
     *     per_round_composition: array<int,array<string,int>>,
     *     sub_domain_quotas: array<string,int>,
     * }
     */
    public function projectPlan(
        string $mode,
        $levelOrDivision,
        int $totalQuestions,
        int $roundsCount,
        string $domain = 'general'
    ): array {
        $config = config('question_bank_profiles');
        $resolvedTarget = $this->resolveModeTarget($mode, $levelOrDivision, $config);
        $cognitiveMix = $this->resolveCognitiveMix($resolvedTarget, $config);
        $depthRange = $this->resolveDepthRange($resolvedTarget, $config);

        $globalComposition = QuotaAllocator::allocate(
            $cognitiveMix,
            $totalQuestions,
            $config['cognitive_priority_order'] ?? []
        );

        $perRoundByRound = $this->distributeCognitiveAcrossRounds(
            $globalComposition,
            $totalQuestions,
            $roundsCount,
            $config['cognitive_priority_order'] ?? []
        );

        $domainList = $this->resolveDomainList($domain, $config);
        $subDomainQuotas = $this->buildSubDomainQuotas($domainList, $totalQuestions, $config);

        return [
            'resolved_target' => $resolvedTarget,
            'cognitive_mix' => $cognitiveMix,
            'depth_range' => $depthRange,
            'global_composition' => $globalComposition,
            'per_round_composition' => $perRoundByRound,
            'sub_domain_quotas' => $subDomainQuotas,
        ];
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Resolve (mode, level/division) into a normalised target descriptor.
     * @return array{type:string, levels?:array{0:int,1:int}, level?:int}
     */
    public function resolveModeTarget(string $mode, $levelOrDivision, array $config): array
    {
        if ($mode === 'solo') {
            $level = (int) $levelOrDivision;
            // Boss multiples take their own profile
            if ($this->isBossLevel($level, $config)) {
                return ['type' => 'boss', 'level' => $level];
            }
            $band = $this->resolveStudentBand($level, $config);
            // Targeting any student match uses the level itself for filtering
            return ['type' => 'solo_range', 'levels' => [$level, $level], 'depth_range' => $band['depth_range']];
        }

        if ($mode === 'boss') {
            return ['type' => 'boss', 'level' => (int) $levelOrDivision];
        }

        $mappings = $config['mode_mappings'][$mode] ?? null;
        if ($mappings === null) {
            throw new \InvalidArgumentException("Unknown mode: {$mode}");
        }
        $division = (string) $levelOrDivision;
        if (!isset($mappings[$division])) {
            throw new \InvalidArgumentException("Unknown division '{$division}' for mode '{$mode}'");
        }
        return $mappings[$division];
    }

    private function isBossLevel(int $level, array $config): bool
    {
        return isset($config['boss_profiles'][$level]);
    }

    private function resolveStudentBand(int $level, array $config): array
    {
        foreach ($config['student_bands'] ?? [] as $band) {
            [$from, $to] = $band['levels'];
            if ($level >= $from && $level <= $to) {
                return $band;
            }
        }
        // Defensive: never silently return; fall back to widest plausible band.
        Log::warning('[MatchQuestionPlanner] orphan Solo level falling back to depth 5-6', ['level' => $level]);
        return ['levels' => [$level, $level], 'depth_range' => [5, 6]];
    }

    private function resolveCognitiveMix(array $resolvedTarget, array $config): array
    {
        if ($resolvedTarget['type'] === 'boss') {
            $level = (int) $resolvedTarget['level'];
            $profile = $config['boss_profiles'][$level] ?? null;
            if (!$profile) {
                throw new \InvalidArgumentException("No Boss profile for level {$level}");
            }
            return $profile['mix'];
        }
        return $config['student_cognitive_mix'];
    }

    private function resolveDepthRange(array $resolvedTarget, array $config): array
    {
        if ($resolvedTarget['type'] === 'boss') {
            $level = (int) $resolvedTarget['level'];
            $depth = $config['boss_profiles'][$level]['depth'];
            return [$depth, $depth];
        }
        if (isset($resolvedTarget['depth_range'])) {
            return $resolvedTarget['depth_range'];
        }
        // For solo_range targets coming from mode mappings, take the widest matching band.
        [$from, $to] = $resolvedTarget['levels'];
        $minDepth = 10;
        $maxDepth = 1;
        for ($lvl = $from; $lvl <= $to; $lvl++) {
            if ($this->isBossLevel($lvl, $config)) {
                continue;
            }
            $band = $this->resolveStudentBand($lvl, $config);
            $minDepth = min($minDepth, $band['depth_range'][0]);
            $maxDepth = max($maxDepth, $band['depth_range'][1]);
        }
        if ($maxDepth < $minDepth) {
            $minDepth = 5;
            $maxDepth = 6;
        }
        return [$minDepth, $maxDepth];
    }

    /**
     * @return array<string>  list of sub-domains involved in this match
     */
    private function resolveDomainList(string $domain, array $config): array
    {
        if (strtolower($domain) === 'general' || strtolower($domain) === 'général') {
            return $config['general_sub_domains'];
        }
        return [$domain];
    }

    /**
     * Distribute the totalQuestions across domain list according to weights.
     *
     * @return array<string,int> domainName => quota
     */
    private function buildSubDomainQuotas(array $domainList, int $totalQuestions, array $config): array
    {
        if (count($domainList) === 1) {
            return [$domainList[0] => $totalQuestions];
        }
        $weights = $config['general_sub_domain_weights'] ?? 'equal';
        $weighted = [];
        foreach ($domainList as $sd) {
            $weighted[$sd] = ($weights === 'equal') ? 100 : (int) ($weights[$sd] ?? 0);
        }
        return QuotaAllocator::allocate($weighted, $totalQuestions, $domainList);
    }

    /**
     * Distribute global cognitive quotas across rounds so that:
     *   1. Each round receives EXACTLY its target slot count
     *      (totalQuestions / rounds, with the remainder absorbed by the last round).
     *   2. The global per-cognitive total is preserved.
     *   3. The per-cognitive per-round drift stays bounded (greedy slot-filling
     *      consumes the largest remaining quota first, with the configured
     *      `cognitive_priority_order` breaking ties).
     *
     * This replaces the previous independent per-cognitive splitting which could
     * produce per-round sums that do not match the fixed slot count (e.g. Boss 100
     * 17/9/4 over 3 rounds → 11/10/9 instead of the required 10/10/10).
     *
     * @param  array<string,int>  $globalComposition
     * @param  array<string>      $priorityOrder
     * @return array<int,array<string,int>>  byRound[$round][$cogType] = count
     */
    private function distributeCognitiveAcrossRounds(
        array $globalComposition,
        int $totalQuestions,
        int $roundsCount,
        array $priorityOrder
    ): array {
        // Step 1: independent per-cognitive split (preserves cog totals exactly,
        // gives near-balanced per-cog per-round distribution).
        $byRound = [];
        for ($r = 1; $r <= $roundsCount; $r++) {
            $byRound[$r] = array_fill_keys(array_keys($globalComposition), 0);
        }
        foreach ($globalComposition as $cogType => $quota) {
            $rounds = QuotaAllocator::allocatePerRound($quota, $roundsCount);
            for ($r = 1; $r <= $roundsCount; $r++) {
                $byRound[$r][$cogType] = $rounds[$r] ?? 0;
            }
        }

        // Step 2: rebalance round-totals to the target slot count (base±1 per round)
        // by moving 1 question at a time from over-filled rounds to under-filled
        // rounds. Pick the cognitive type with the highest count in the source
        // round (avoids creating > ±1 cog-drift). Loop is bounded by totalQuestions.
        $base = intdiv($totalQuestions, $roundsCount);
        $remainder = $totalQuestions - ($base * $roundsCount);
        $targetPerRound = [];
        for ($r = 1; $r <= $roundsCount; $r++) {
            $targetPerRound[$r] = $base + (($r === $roundsCount) ? $remainder : 0);
        }

        $maxIterations = $totalQuestions * 2;
        for ($i = 0; $i < $maxIterations; $i++) {
            $sums = [];
            for ($r = 1; $r <= $roundsCount; $r++) {
                $sums[$r] = array_sum($byRound[$r]);
            }
            $deltas = [];
            for ($r = 1; $r <= $roundsCount; $r++) {
                $deltas[$r] = $sums[$r] - $targetPerRound[$r];
            }
            $maxDelta = max($deltas);
            $minDelta = min($deltas);
            if ($maxDelta <= 0 && $minDelta >= 0) {
                break; // every round at exact target
            }

            $sourceRound = array_search($maxDelta, $deltas, true);
            $targetRound = array_search($minDelta, $deltas, true);
            if ($sourceRound === false || $targetRound === false || $sourceRound === $targetRound) {
                break;
            }

            // Pick the cog with max count in source round (priority order breaks ties).
            $sourceBucket = $byRound[$sourceRound];
            arsort($sourceBucket);
            $maxCount = reset($sourceBucket);
            if ($maxCount <= 0) {
                break;
            }
            $tied = array_keys(array_filter($sourceBucket, fn ($v) => $v === $maxCount));
            $priorityRank = [];
            foreach ($priorityOrder as $idx => $type) {
                $priorityRank[$type] = $idx;
            }
            usort($tied, fn ($a, $b) => ($priorityRank[$a] ?? PHP_INT_MAX) <=> ($priorityRank[$b] ?? PHP_INT_MAX));
            $pick = $tied[0];

            $byRound[$sourceRound][$pick] -= 1;
            $byRound[$targetRound][$pick] += 1;
        }

        return $byRound;
    }

    /**
     * Greedy selection that respects per-round cognitive quotas, balances
     * sub-domains across the match, avoids consecutive sub-domain repeats,
     * and applies anti-clone (concept_id) + concept_family deprioritization.
     *
     * @return array{
     *     questions: array<int, array>,
     *     sub_domain_distribution: array<string,int>,
     *     issues: array<string>,
     * }
     */
    private function selectQuestions(
        array $resolvedTarget,
        array $depthRange,
        array $perRoundByRound,
        array $subDomainQuotas,
        array $domainList,
        string $language,
        int $totalQuestions,
        int $roundsCount,
        array $config
    ): array {
        $questionsPerRound = (int) ($totalQuestions / $roundsCount);
        // Note: if totalQuestions doesn't divide evenly the last round absorbs the remainder.

        $usedGroupIds = [];
        $usedConceptIds = [];
        $conceptFamilyCounts = [];
        $remainingSubDomain = $subDomainQuotas;

        $selected = []; // ordered list of selected items
        $issues = [];
        $lastSubDomain = null;

        for ($round = 1; $round <= $roundsCount; $round++) {
            $countForRound = ($round === $roundsCount)
                ? $totalQuestions - ($questionsPerRound * ($roundsCount - 1))
                : $questionsPerRound;

            $remainingCogPerRound = $perRoundByRound[$round] ?? [];

            for ($q = 0; $q < $countForRound; $q++) {
                $cogType = $this->pickNextCognitiveType($remainingCogPerRound, $config);
                if ($cogType === null) {
                    $issues[] = "round_{$round}_no_cognitive_quota_left";
                    break;
                }

                $subDomain = $this->pickNextSubDomain($remainingSubDomain, $lastSubDomain);
                $deprioritisedFamilies = $this->deprioritisedFamilies($conceptFamilyCounts, $totalQuestions, $config);

                $group = $this->repo->pickOne([
                    'mode_target' => $resolvedTarget,
                    'depth_range' => $depthRange,
                    'cognitive_type' => $cogType,
                    'domain' => $domainList,
                    'sub_domain' => $subDomain,
                    'exclude_group_ids' => $usedGroupIds,
                    'exclude_concept_ids' => $usedConceptIds,
                    'exclude_concept_families' => $deprioritisedFamilies,
                    'language' => $language,
                    'require_validated' => true,
                ]);

                // Relax 1: drop concept_family deprioritization.
                if (!$group && !empty($deprioritisedFamilies)) {
                    $group = $this->repo->pickOne([
                        'mode_target' => $resolvedTarget,
                        'depth_range' => $depthRange,
                        'cognitive_type' => $cogType,
                        'domain' => $domainList,
                        'sub_domain' => $subDomain,
                        'exclude_group_ids' => $usedGroupIds,
                        'exclude_concept_ids' => $usedConceptIds,
                        'language' => $language,
                        'require_validated' => true,
                    ]);
                }

                // Relax 2: drop sub-domain restriction (keep cognitive type).
                if (!$group && $subDomain !== null) {
                    $group = $this->repo->pickOne([
                        'mode_target' => $resolvedTarget,
                        'depth_range' => $depthRange,
                        'cognitive_type' => $cogType,
                        'domain' => $domainList,
                        'exclude_group_ids' => $usedGroupIds,
                        'exclude_concept_ids' => $usedConceptIds,
                        'language' => $language,
                        'require_validated' => true,
                    ]);
                    if ($group) {
                        $issues[] = "round_{$round}_subdomain_{$subDomain}_unavailable_for_{$cogType}";
                    }
                }

                // Relax 3: drop validated requirement (let the worker mark groups validated later).
                if (!$group) {
                    $group = $this->repo->pickOne([
                        'mode_target' => $resolvedTarget,
                        'depth_range' => $depthRange,
                        'cognitive_type' => $cogType,
                        'domain' => $domainList,
                        'exclude_group_ids' => $usedGroupIds,
                        'exclude_concept_ids' => $usedConceptIds,
                        'language' => $language,
                        'require_validated' => false,
                    ]);
                }

                if (!$group) {
                    $issues[] = "round_{$round}_no_group_for_{$cogType}";
                    continue;
                }

                $selected[] = $this->materialise($group, $language, count($selected) + 1);
                $usedGroupIds[] = $group->id;
                if ($group->concept_id) {
                    $usedConceptIds[] = $group->concept_id;
                }
                if ($group->concept_family) {
                    $conceptFamilyCounts[$group->concept_family] = ($conceptFamilyCounts[$group->concept_family] ?? 0) + 1;
                }

                $remainingCogPerRound[$cogType] = max(0, ($remainingCogPerRound[$cogType] ?? 0) - 1);
                if ($subDomain !== null) {
                    $remainingSubDomain[$group->sub_domain] = max(0, ($remainingSubDomain[$group->sub_domain] ?? 0) - 1);
                }
                $lastSubDomain = $group->sub_domain;
            }
        }

        $subDistribution = [];
        foreach ($selected as $q) {
            $subDistribution[$q['sub_theme']] = ($subDistribution[$q['sub_theme']] ?? 0) + 1;
        }

        return [
            'questions' => $selected,
            'sub_domain_distribution' => $subDistribution,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    private function pickNextCognitiveType(array $remainingCogPerRound, array $config): ?string
    {
        $priority = $config['cognitive_priority_order'] ?? [];
        // Pick the cognitive_type with highest remaining count; ties broken by priority order.
        $best = null;
        $bestCount = 0;
        $bestRank = PHP_INT_MAX;
        $rankMap = [];
        foreach ($priority as $i => $key) {
            $rankMap[$key] = $i;
        }
        foreach ($remainingCogPerRound as $type => $count) {
            if ($count <= 0) {
                continue;
            }
            $rank = $rankMap[$type] ?? PHP_INT_MAX;
            if ($count > $bestCount || ($count === $bestCount && $rank < $bestRank)) {
                $best = $type;
                $bestCount = $count;
                $bestRank = $rank;
            }
        }
        return $best;
    }

    private function pickNextSubDomain(array $remainingSubDomain, ?string $lastSubDomain): ?string
    {
        if (count($remainingSubDomain) === 1) {
            $only = array_key_first($remainingSubDomain);
            return ($remainingSubDomain[$only] > 0) ? $only : null;
        }

        // Prefer a sub-domain that is NOT the same as last (rotation), with the largest remaining quota.
        $candidates = array_filter($remainingSubDomain, fn ($c) => $c > 0);
        if (empty($candidates)) {
            return null;
        }

        $bestKey = null;
        $bestCount = -1;
        foreach ($candidates as $sd => $count) {
            if ($lastSubDomain !== null && $sd === $lastSubDomain && count($candidates) > 1) {
                continue;
            }
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestKey = $sd;
            }
        }
        return $bestKey ?? array_key_first($candidates);
    }

    /**
     * @return array<string>  list of concept_family that have already exceeded the per-match share.
     */
    private function deprioritisedFamilies(array $counts, int $totalQuestions, array $config): array
    {
        $maxShare = $config['concept_family_match_max_share'] ?? 0.35;
        $maxAbsolute = max(2, (int) floor($totalQuestions * $maxShare));
        $out = [];
        foreach ($counts as $family => $n) {
            if ($n >= $maxAbsolute) {
                $out[] = $family;
            }
        }
        return $out;
    }

    /**
     * Format a QuestionGroup + its translation into the legacy shape expected by
     * GameServerQuestionPipeline::formatQuestion(). The bank takes the place of
     * the IA generator: the format is identical so no downstream change is needed.
     */
    private function materialise(QuestionGroup $group, string $language, int $questionNumber): array
    {
        $translation = $group->translationFor($language);
        if (!$translation) {
            // Fallback: try fr, then en, then any.
            $translation = $group->translationFor('fr')
                ?? $group->translationFor('en')
                ?? $group->translations()->first();
        }
        if (!$translation) {
            throw new \RuntimeException("QuestionGroup #{$group->id} has no translation in any language");
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

    private function formatCompositionLog(array $composition): string
    {
        $parts = [];
        foreach ($composition as $key => $count) {
            $abbr = match ($key) {
                'recognition' => 'R',
                'reasoning' => 'Ra',
                'deceptive_trap' => 'D',
                default => $key,
            };
            $parts[] = "{$count}{$abbr}";
        }
        return implode('/', $parts);
    }
}
