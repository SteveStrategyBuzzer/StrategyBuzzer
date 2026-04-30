<?php

namespace App\Services;

use App\Jobs\MarkQuestionGroupUsedJob;
use App\Models\MatchQuestionPlan;
use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Planificateur de match — construit, avant chaque partie, un plan ordonné
 * de question_group_id qui satisfait :
 *  - les quotas globaux par cognitive_type (largest-remainder, ±1)
 *  - les quotas par manche par cognitive_type (largest-remainder, ±1)
 *  - les quotas par sous-domaine quand domain=general (largest-remainder, ±1)
 *  - l'anti-clone par concept_id et anti-surutilisation par concept_family
 *  - l'alternance des sous-domaines pour éviter les répétitions consécutives
 *
 * Aucun appel IA pendant la construction. Si la banque est insuffisante, on
 * remplit ce qu'on peut, on log explicitement le manque, et on complète en
 * tout dernier recours avec le seed pool — JAMAIS d'appel IA synchrone.
 */
class MatchQuestionPlanner
{
    /** @var array<string,mixed> */
    private array $profilesCfg;
    private QuestionBankRepository $repo;
    private SeedQuestionPoolService $seedPool;

    public function __construct(?QuestionBankRepository $repo = null, ?SeedQuestionPoolService $seedPool = null)
    {
        $this->repo = $repo ?? new QuestionBankRepository();
        $this->seedPool = $seedPool ?? new SeedQuestionPoolService();
        $this->profilesCfg = $this->normaliseConfig(config('question_bank_profiles', []));
    }

    /**
     * Re-render a previously selected group_id in the requested language.
     * Used by GameServerQuestionPipeline to serve the same canonical
     * concept in another language for multilingual rooms, with a FR→EN
     * fallback chain so missing translations never call the IA.
     *
     * Returns null when neither the requested language nor any fallback
     * translation exists; the caller is expected to render a stub instead.
     */
    public function renderGroupForLanguage(int $groupId, string $language): ?array
    {
        $group = QuestionGroup::with('translations')->find($groupId);
        if (!$group) {
            return null;
        }

        $chain = array_unique(array_merge([$language], $this->profilesCfg['translation_fallback_chain'] ?? ['fr', 'en']));
        $translation = null;
        foreach ($chain as $lang) {
            $translation = $group->translationFor($lang);
            if ($translation) {
                break;
            }
        }
        if (!$translation) {
            $translation = $group->translations()->first();
        }
        if (!$translation) {
            return null;
        }

        $answers = $translation->answersList();
        $correctIndex = $translation->correctIndex();
        if ($correctIndex >= count($answers)) {
            $correctIndex = 0;
        }

        return [
            'id'              => 'qb_' . $group->id,
            'group_id'        => $group->id,
            'type'            => $group->question_type === 'true_false' ? 'true_false' : 'multiple',
            'question_text'   => $translation->question_text,
            'text'            => $translation->question_text,
            'answers'         => $answers,
            'correct_index'   => $correctIndex,
            'correct_id'      => $correctIndex,
            'explanation'     => $translation->explanation,
            'saviez_vous'     => $translation->saviez_vous,
            'theme'           => $group->domain,
            'sub_theme'       => $group->sub_domain,
            'cognitive_type'  => $group->cognitive_type,
            'difficulty_depth'=> $group->difficulty_depth,
            'concept_id'      => $group->concept_id,
            'language'        => $translation->language,
            'source'          => 'bank',
        ];
    }

    /**
     * Boss profile in question_bank_profiles.php uses key `mix`; the legacy
     * planner internals expect `cognitive_mix`. Mirror it without losing
     * the original to stay forward-compatible with the new planner.
     */
    private function normaliseBossProfile(array $bossProfile): array
    {
        if (!isset($bossProfile['cognitive_mix']) && isset($bossProfile['mix'])) {
            $bossProfile['cognitive_mix'] = $bossProfile['mix'];
        }
        return $bossProfile;
    }

    /**
     * Bridge between the two coexisting config dialects after the #81/#82
     * rebase: the new authoritative keys live in question_bank_profiles.php
     * (`student_cognitive_mix`, `student_bands`, `general_sub_domain_weights`)
     * while this legacy planner still reads the old names. We expose both so
     * either reader works.
     */
    private function normaliseConfig(array $cfg): array
    {
        if (!isset($cfg['student_mix']) && isset($cfg['student_cognitive_mix'])) {
            $cfg['student_mix'] = $cfg['student_cognitive_mix'];
        }
        if (!isset($cfg['solo_bands']) && isset($cfg['student_bands'])) {
            $cfg['solo_bands'] = array_map(function ($band) {
                if (!isset($band['depth']) && isset($band['depth_range'])) {
                    $band['depth'] = $band['depth_range'];
                }
                return $band;
            }, $cfg['student_bands']);
        }
        if (!isset($cfg['general_subdomain_weights']) && isset($cfg['general_sub_domain_weights'])) {
            $cfg['general_subdomain_weights'] = $cfg['general_sub_domain_weights'];
        }
        return $cfg;
    }

    /**
     * Construit le plan d'un match.
     *
     * @param string      $mode             solo|duo|mj|ligue|boss
     * @param string|int  $levelOrDivision  niveau Solo (int 1-99) ou nom de division (string)
     * @param int         $totalQuestions   N total de questions
     * @param int         $roundsCount      nombre de manches
     * @param string      $language         langue du joueur
     * @param array       $extra            { domain?, sub_domain?, question_type? }
     *
     * @return array{
     *   plan_id: string,
     *   mode: string,
     *   division: string|null,
     *   profile: array,
     *   global_quotas: array<string,int>,
     *   per_round_quotas: array<int, array<string,int>>,
     *   subdomain_quotas: array<string,int>|null,
     *   subdomain_per_round: array<int, array<string,int>>|null,
     *   ordered_questions: array<int, array>,
     *   ordered_group_ids: array<int, int|string>,
     *   shortages: array,
     *   composition_actual: array<string,int>,
     *   per_round_actual: array<int, array<string,int>>
     * }
     */
    public function buildPlan(
        string $mode,
        $levelOrDivision,
        int $totalQuestions,
        int $roundsCount,
        string $language,
        array $extra = []
    ): array {
        $mode = strtolower($mode);
        $resolved = $this->resolveProfile($mode, $levelOrDivision);
        $profile = $resolved['profile'];

        $domain = $extra['domain'] ?? 'general';
        $questionType = $extra['question_type'] ?? null;
        $stableOrder = $this->profilesCfg['stable_tiebreak_order'] ?? [];

        // --- 1. Quotas cognitifs globaux (largest-remainder) -----------------
        $cognitiveMix = $profile['cognitive_mix'];
        $globalQuotas = QuotaAllocator::allocate($cognitiveMix, $totalQuestions, $stableOrder);

        // --- 2. Quotas cognitifs par manche --------------------------------
        $perRoundQuotas = $this->distributePerRound($globalQuotas, $roundsCount, $stableOrder);

        // --- 3. Quotas par sous-domaine (si domain=general) -----------------
        $subQuotas = null;
        $subPerRound = null;
        if ($domain === 'general') {
            $weights = $this->profilesCfg['general_subdomain_weights'] ?? [];
            $subQuotas = QuotaAllocator::allocate($weights, $totalQuestions, array_keys($weights));
            $subPerRound = $this->distributePerRound($subQuotas, $roundsCount, array_keys($weights));
        }

        // --- 4. Sélection question par question, manche par manche ---------
        [$selected, $shortages, $perRoundActual, $globalActual, $subActual] = $this->selectQuestions(
            $resolved,
            $perRoundQuotas,
            $subPerRound,
            $domain,
            $questionType,
            $language,
            $totalQuestions,
            $roundsCount
        );

        // --- 5. Charge les traductions et formate ---------------------------
        $orderedQuestions = $this->loadAndFormatQuestions($selected, $language);
        $orderedGroupIds = array_map(fn ($q) => $q['group_id'] ?? $q['id'], $orderedQuestions);

        // --- 6. Persiste le plan pour audit --------------------------------
        $planId = (string) Str::uuid();
        $division = is_string($levelOrDivision) ? $levelOrDivision : null;

        try {
            // Persiste sur le schéma canonique (HEAD) : plan_uid + group_ids.
            // Le planner expose 'plan_id' / 'ordered_group_ids' dans son
            // ordered_questions return-shape pour compat avec les tests.
            MatchQuestionPlan::create([
                'plan_uid'              => $planId,
                'mode'                  => $mode,
                'division'              => $division,
                'total_questions'       => $totalQuestions,
                'rounds_count'          => $roundsCount,
                'language'              => $language,
                'global_composition'    => [
                    'target' => $globalQuotas,
                    'actual' => $globalActual,
                ],
                'per_round_composition' => [
                    'target' => $perRoundQuotas,
                    'actual' => $perRoundActual,
                ],
                'group_ids'             => $orderedGroupIds,
                'shortages'             => $shortages,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MatchQuestionPlanner] could not persist plan audit row', [
                'error' => $e->getMessage(),
            ]);
        }

        // --- 7. Marque les groupes comme utilisés (asynchrone) -------------
        foreach ($orderedGroupIds as $gid) {
            if (is_int($gid) || ctype_digit((string) $gid)) {
                try {
                    MarkQuestionGroupUsedJob::dispatch((int) $gid);
                } catch (\Throwable $e) {
                    // Ne pas casser le plan si la queue est down.
                }
            }
        }

        Log::info('[MatchQuestionPlanner] plan built', [
            'plan_id'        => $planId,
            'mode'           => $mode,
            'division'       => $division,
            'total'          => $totalQuestions,
            'rounds'         => $roundsCount,
            'language'       => $language,
            'global_target'  => $globalQuotas,
            'global_actual'  => $globalActual,
            'per_round_target' => $perRoundQuotas,
            'per_round_actual' => $perRoundActual,
            'shortages'      => $shortages,
            'compact_log'    => $this->buildCompactLog($globalActual, $perRoundActual),
        ]);

        return [
            'plan_id'            => $planId,
            'mode'               => $mode,
            'division'           => $division,
            'profile'            => $profile,
            'global_quotas'      => $globalQuotas,
            'per_round_quotas'   => $perRoundQuotas,
            'subdomain_quotas'   => $subQuotas,
            'subdomain_per_round' => $subPerRound,
            'subdomain_actual'   => $subActual,
            'ordered_questions'  => $orderedQuestions,
            'ordered_group_ids'  => $orderedGroupIds,
            'shortages'          => $shortages,
            'composition_actual' => $globalActual,
            'per_round_actual'   => $perRoundActual,
        ];
    }

    /**
     * Résout (mode, level|division) en :
     *  - profile cognitive_mix + depth
     *  - difficulty_level (pour les modes Solo / élève) ou boss_level
     *
     * @return array{profile: array, kind: string, level: int|null, boss_level: int|null, depth_min: int, depth_max: int, level_range: array{int,int}|null}
     */
    public function resolveProfile(string $mode, $levelOrDivision): array
    {
        $cfg = $this->profilesCfg;
        $studentMix = $cfg['student_mix'];

        // Mode "boss" direct avec un level boss numérique
        if ($mode === 'boss' || ($mode === 'solo' && is_int($levelOrDivision) && $this->isBossLevel($levelOrDivision))) {
            $bossLevel = (int) $levelOrDivision;
            $bossProfile = $cfg['boss_profiles'][$bossLevel] ?? null;
            if (!$bossProfile) {
                throw new \InvalidArgumentException("Unknown boss level: {$bossLevel}");
            }
            $bossProfile = $this->normaliseBossProfile($bossProfile);
            return [
                'profile'    => $bossProfile,
                'kind'       => 'boss',
                'level'      => null,
                'boss_level' => $bossLevel,
                'depth_min'  => is_array($bossProfile['depth']) ? $bossProfile['depth'][0] : $bossProfile['depth'],
                'depth_max'  => is_array($bossProfile['depth']) ? $bossProfile['depth'][1] : $bossProfile['depth'],
                'level_range'=> null,
            ];
        }

        if ($mode === 'solo') {
            $level = (int) $levelOrDivision;
            $band = $this->findSoloBand($level);
            if (!$band) {
                throw new \InvalidArgumentException("No solo band for level {$level}");
            }
            return [
                'profile'    => ['cognitive_mix' => $studentMix, 'depth' => $band['depth']],
                'kind'       => 'solo',
                'level'      => $level,
                'boss_level' => null,
                'depth_min'  => $band['depth'][0],
                'depth_max'  => $band['depth'][1],
                'level_range'=> null,
            ];
        }

        // Modes mappés (duo / mj / ligue)
        $mapping = $cfg['mode_mappings'][$mode][strtolower((string) $levelOrDivision)] ?? null;
        if (!$mapping) {
            throw new \InvalidArgumentException("Unknown {$mode} division: {$levelOrDivision}");
        }

        $mappingKind = $mapping['kind'] ?? $mapping['type'] ?? '';
        if ($mappingKind === 'boss') {
            $bossLevel = (int) $mapping['level'];
            $bossProfile = $cfg['boss_profiles'][$bossLevel] ?? null;
            if (!$bossProfile) {
                throw new \InvalidArgumentException("Unknown boss level: {$bossLevel}");
            }
            $bossProfile = $this->normaliseBossProfile($bossProfile);
            return [
                'profile'    => $bossProfile,
                'kind'       => 'boss',
                'level'      => null,
                'boss_level' => $bossLevel,
                'depth_min'  => is_array($bossProfile['depth']) ? $bossProfile['depth'][0] : $bossProfile['depth'],
                'depth_max'  => is_array($bossProfile['depth']) ? $bossProfile['depth'][1] : $bossProfile['depth'],
                'level_range'=> null,
            ];
        }

        // solo_range : on cible la bande Solo couvrant le range, depth = union
        $range = $mapping['levels'];
        $minBand = $this->findSoloBand($range[0]);
        $maxBand = $this->findSoloBand($range[1]);
        $depthMin = min($minBand['depth'][0], $maxBand['depth'][0]);
        $depthMax = max($minBand['depth'][1], $maxBand['depth'][1]);

        return [
            'profile'    => ['cognitive_mix' => $studentMix, 'depth' => [$depthMin, $depthMax]],
            'kind'       => 'solo_range',
            'level'      => null,
            'boss_level' => null,
            'depth_min'  => $depthMin,
            'depth_max'  => $depthMax,
            'level_range'=> $range,
        ];
    }

    private function findSoloBand(int $level): ?array
    {
        foreach (($this->profilesCfg['solo_bands'] ?? []) as $band) {
            [$lo, $hi] = $band['levels'];
            if ($level >= $lo && $level <= $hi) {
                $depth = $band['depth'];
                return [
                    'depth' => is_array($depth) ? $depth : [$depth, $depth],
                ];
            }
        }
        return null;
    }

    private function isBossLevel(int $level): bool
    {
        return isset($this->profilesCfg['boss_profiles'][$level]);
    }

    /**
     * Distribue des quotas globaux sur N manches via largest-remainder par
     * code, en visant la cible théorique = quota_global / rounds.
     *
     * Garantit Σ rounds = quota_global pour chaque code, et tolérance ±1
     * par manche par construction (largest-remainder sur valeurs ≥ 0).
     *
     * @param array<string,int> $globalQuotas
     * @return array<int, array<string,int>>  round (1-indexed) → code → quota
     */
    private function distributePerRound(array $globalQuotas, int $rounds, array $stableOrder): array
    {
        $perRound = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $perRound[$r] = [];
            foreach (array_keys($globalQuotas) as $code) {
                $perRound[$r][$code] = 0;
            }
        }

        foreach ($globalQuotas as $code => $total) {
            // Répartit $total sur $rounds manches en uniforme via largest-remainder.
            // Poids égaux par manche → quotient ± reste.
            $weights = array_fill(1, $rounds, 1);
            $alloc = QuotaAllocator::allocate($weights, $total, range(1, $rounds));
            foreach ($alloc as $r => $v) {
                $perRound[$r][$code] = $v;
            }
        }

        return $perRound;
    }

    /**
     * Sélectionne les questions, manche par manche, en respectant les quotas
     * cognitifs et sous-domaine, l'anti-clone par concept_id, l'anti-
     * surutilisation par concept_family, et l'alternance des sous-domaines.
     *
     * Stratégie :
     *  - On itère sur les manches.
     *  - Pour chaque manche on construit la liste cible (cognitive_type[]) et
     *    sub_domain[] selon les quotas par manche.
     *  - On sélectionne pour chaque slot une candidate dans la banque,
     *    fallback élargi (relaxe sub_domain → relaxe depth → relaxe boss_level)
     *    avant de marquer un shortage.
     *
     * @return array{0: array<int,array>, 1: array, 2: array, 3: array<string,int>, 4: array<string,int>|null}
     *     [selected, shortages, perRoundActual, globalActual, subActual]
     */
    private function selectQuestions(
        array $resolved,
        array $perRoundQuotas,
        ?array $subPerRound,
        string $domain,
        ?string $questionType,
        string $language,
        int $totalQuestions,
        int $roundsCount
    ): array {
        $selected = [];
        $shortages = [];
        $usedConceptIds = [];
        $usedConceptFamilies = [];
        $usedGroupIds = [];
        $perRoundActual = [];
        $globalActual = ['recognition' => 0, 'reasoning' => 0, 'deceptive_trap' => 0];
        $subActual = $subPerRound !== null ? [] : null;

        for ($r = 1; $r <= $roundsCount; $r++) {
            $perRoundActual[$r] = ['recognition' => 0, 'reasoning' => 0, 'deceptive_trap' => 0];

            // Construit l'ordre intra-manche : alterner les cognitive_types et
            // les sub_domains pour éviter les paquets.
            $cogQueue = $this->expandQuota($perRoundQuotas[$r]);
            $subQueue = $subPerRound !== null ? $this->expandQuota($subPerRound[$r]) : null;

            // Mélange interne déterministe via interleave : on prend par tour
            // un cognitive_type et un sub_domain selon l'ordre d'apparition.
            $slotsCount = array_sum($perRoundQuotas[$r]);
            $lastSubDomain = $r > 1 ? ($selected[count($selected) - 1]['sub_domain'] ?? null) : null;

            for ($slot = 0; $slot < $slotsCount; $slot++) {
                $cognitiveType = array_shift($cogQueue);
                $subDomain = $subQueue !== null ? $this->pickNonAdjacentSub($subQueue, $lastSubDomain) : null;

                $picked = $this->pickOneFromBank(
                    $resolved,
                    $cognitiveType,
                    $domain,
                    $subDomain,
                    $questionType,
                    $language,
                    $usedConceptIds,
                    $usedConceptFamilies,
                    $usedGroupIds
                );

                if ($picked === null) {
                    // Shortage — log et placeholder
                    $shortages[] = [
                        'round'           => $r,
                        'cognitive_type'  => $cognitiveType,
                        'sub_domain'      => $subDomain,
                        'depth_range'     => [$resolved['depth_min'], $resolved['depth_max']],
                        'language'        => $language,
                    ];
                    Log::warning('[MatchQuestionPlanner] bank shortage', [
                        'round' => $r,
                        'cognitive_type' => $cognitiveType,
                        'sub_domain' => $subDomain,
                        'language' => $language,
                    ]);
                    $selected[] = [
                        'group_id'       => null,
                        'cognitive_type' => $cognitiveType,
                        'sub_domain'     => $subDomain,
                        'round'          => $r,
                        'shortage'       => true,
                        // Carry the segment (#93) so the seed-pool fallback in
                        // loadAndFormatQuestions() can target the right
                        // (theme, niveau) bucket instead of picking blind.
                        'domain'         => $domain,
                        'depth_range'    => [$resolved['depth_min'], $resolved['depth_max']],
                    ];
                    continue;
                }

                $usedConceptIds[] = $picked->concept_id;
                if ($picked->concept_family) {
                    $usedConceptFamilies[] = $picked->concept_family;
                }
                $usedGroupIds[] = $picked->id;
                $lastSubDomain = $picked->sub_domain;

                $perRoundActual[$r][$picked->cognitive_type]++;
                $globalActual[$picked->cognitive_type]++;
                if ($subActual !== null) {
                    $subActual[$picked->sub_domain] = ($subActual[$picked->sub_domain] ?? 0) + 1;
                }

                $selected[] = [
                    'group_id'       => $picked->id,
                    'cognitive_type' => $picked->cognitive_type,
                    'sub_domain'     => $picked->sub_domain,
                    'round'          => $r,
                    'shortage'       => false,
                    // #93 — carry the slot's segment so a translation gap
                    // can fall through to the targeted seed pool, not a
                    // random one.
                    'domain'         => $domain,
                    'depth_range'    => [$resolved['depth_min'], $resolved['depth_max']],
                ];
            }
        }

        return [$selected, $shortages, $perRoundActual, $globalActual, $subActual];
    }

    /**
     * @param array<string,int> $quota  code → count
     * @return array<int,string>
     */
    private function expandQuota(array $quota): array
    {
        $out = [];
        foreach ($quota as $code => $n) {
            for ($i = 0; $i < $n; $i++) {
                $out[] = $code;
            }
        }
        return $out;
    }

    /**
     * Tire un sub_domain dans la file en évitant si possible le même que le
     * précédent. Modifie la file en place.
     */
    private function pickNonAdjacentSub(array &$subQueue, ?string $lastSub): ?string
    {
        if (empty($subQueue)) {
            return null;
        }

        if ($lastSub === null) {
            return array_shift($subQueue);
        }

        foreach ($subQueue as $i => $candidate) {
            if ($candidate !== $lastSub) {
                array_splice($subQueue, $i, 1);
                return $candidate;
            }
        }

        // Tous les restants sont identiques → on prend le premier.
        return array_shift($subQueue);
    }

    /**
     * Tire UNE question dans la banque selon les filtres demandés, avec
     * relaxation progressive : sub_domain exact → sub_domain libre → depth
     * élargie → cognitive_type libre.
     */
    private function pickOneFromBank(
        array $resolved,
        string $cognitiveType,
        string $domain,
        ?string $subDomain,
        ?string $questionType,
        string $language,
        array $usedConceptIds,
        array $usedConceptFamilies,
        array $usedGroupIds
    ): ?QuestionGroup {
        $base = [
            'depth_min'                => $resolved['depth_min'],
            'depth_max'                => $resolved['depth_max'],
            'cognitive_type'           => $cognitiveType,
            'language'                 => $language,
            'excluded_concept_ids'     => $usedConceptIds,
            'excluded_concept_families'=> $usedConceptFamilies,
            'excluded_group_ids'       => $usedGroupIds,
            'limit'                    => 1,
        ];

        if ($questionType) {
            $base['question_type'] = $questionType;
        }
        if ($domain !== 'general') {
            $base['domain'] = $domain;
        }

        if ($resolved['kind'] === 'boss') {
            $base['boss_level'] = $resolved['boss_level'];
        } elseif ($resolved['kind'] === 'solo' && $resolved['level'] !== null) {
            $base['difficulty_level'] = $resolved['level'];
        }
        // Pour solo_range on filtre par depth uniquement (pas un level précis).

        // Relaxations successives
        $tries = [];
        $tries[] = $base + ($subDomain ? ['sub_domain' => $subDomain] : []);
        if ($subDomain) {
            $tries[] = $base; // libère le sub_domain
        }
        // Élargit la depth ±1
        $widened = $base;
        $widened['depth_min'] = max(1, $resolved['depth_min'] - 1);
        $widened['depth_max'] = min(10, $resolved['depth_max'] + 1);
        $tries[] = $widened + ($subDomain ? ['sub_domain' => $subDomain] : []);
        $tries[] = $widened;

        // Dernier recours : libère les exclusions concept_family (mais garde concept_id et group_id pour ne pas re-piocher la même)
        $relaxFamily = $widened;
        unset($relaxFamily['excluded_concept_families']);
        $tries[] = $relaxFamily;

        foreach ($tries as $filters) {
            $cands = $this->repo->findCandidates($filters);
            if ($cands->isNotEmpty()) {
                return $cands->first();
            }
        }

        return null;
    }

    /**
     * Charge les traductions et formate au format attendu par
     * GameServerQuestionPipeline::formatQuestion (clés correct_id, answers,
     * question_text, type, theme, sub_theme, explanation).
     */
    private function loadAndFormatQuestions(array $selected, string $language): array
    {
        $groupIds = array_values(array_filter(array_map(fn ($s) => $s['group_id'] ?? null, $selected)));

        $fallbackChain = $this->profilesCfg['translation_fallback_chain'] ?? ['fr', 'en'];
        $allLangs = array_unique(array_merge([$language], $fallbackChain));

        $groups = !empty($groupIds)
            ? QuestionGroup::with(['translations' => function ($q) use ($allLangs) {
                $q->whereIn('language', $allLangs);
            }])->whereIn('id', $groupIds)->get()->keyBy('id')
            : collect();

        $usedSeedHashes = [];
        $output = [];
        foreach ($selected as $slot) {
            $formatted = null;

            if (!empty($slot['group_id'])) {
                $group = $groups->get($slot['group_id']);
                if ($group) {
                    $translation = $this->pickTranslation($group, $language, $fallbackChain);
                    if ($translation) {
                        $formatted = $this->formatGroup($group, $translation);
                    }
                }
            }

            if ($formatted === null) {
                // Tout dernier recours : pioche dans le seed pool. Aucune
                // sortie sur l'IA, jamais — c'est la garantie du planner.
                // #93 — on transmet le segment complet (domain + depth_band +
                // sub_domain) pour piocher dans la bonne case du pool, pas
                // au hasard. Le seed pool sait dégrader proprement si une
                // case est vide (sub → band → domain → tout).
                $depthBand = null;
                $depthRange = $slot['depth_range'] ?? null;
                if (is_array($depthRange) && isset($depthRange[1])) {
                    $depthBand = self::depthToBand((int) $depthRange[1]);
                }
                $seed = $this->seedPool->pickOne($language, [
                    'domain'     => $slot['domain'] ?? null,
                    'depth_band' => $depthBand,
                    'sub_domain' => $slot['sub_domain'] ?? null,
                ], $usedSeedHashes);
                if ($seed) {
                    $usedSeedHashes[] = md5((string) $seed['question_text']);
                    $formatted = $seed;
                } else {
                    $formatted = $this->buildShortageStub($slot, $language);
                }
            }

            $output[] = $formatted;
        }

        return $output;
    }

    private function pickTranslation(QuestionGroup $group, string $language, array $fallbackChain): ?QuestionTranslation
    {
        $translations = $group->translations->keyBy('language');
        if ($translations->has($language)) {
            return $translations->get($language);
        }
        foreach ($fallbackChain as $lang) {
            if ($translations->has($lang)) {
                return $translations->get($lang);
            }
        }
        return $translations->first();
    }

    /**
     * @return array Format attendu par GameServerQuestionPipeline::formatQuestion
     */
    private function formatGroup(QuestionGroup $group, QuestionTranslation $tr): array
    {
        $answers = $tr->answersOrdered();
        // Pour un true_false, D peut être null, on laisse le pipeline le filtrer.
        $type = $group->question_type === 'true_false' ? 'true_false' : 'multiple';

        return [
            'id'             => 'qg_' . $group->id,
            'group_id'       => $group->id,
            'type'           => $type,
            'question_text'  => $tr->question_text,
            'text'           => $tr->question_text,
            'answers'        => $answers,
            'correct_index'  => $tr->correctIndex(),
            'correct_id'     => $tr->correctIndex(),
            'explanation'    => $tr->explanation,
            'saviez_vous'    => $tr->saviez_vous,
            'difficulty'     => $group->difficulty_depth,
            'theme'          => $group->domain,
            'sub_theme'      => $group->sub_domain,
            'cognitive_type' => $group->cognitive_type,
            'concept_id'     => $group->concept_id,
            'language'       => $tr->language,
        ];
    }

    /**
     * #93 — map a depth (3-10) to one of the seed depth bands declared in
     * config('question_bank_profiles.depth_rubric'). The same mapping lives
     * in QuestionService and SeedQuestionPoolService; keeping it duplicated
     * (3 lines) is preferable to creating a new service just for that.
     */
    private static function depthToBand(int $depth): ?string
    {
        if ($depth <= 0) return null;
        if ($depth <= 4)  return '3-4';
        if ($depth <= 6)  return '5-6';
        if ($depth <= 8)  return '7-8';
        return '9-10';
    }

    private function buildShortageStub(array $slot, string $language): array
    {
        return [
            'id'             => null,
            'group_id'       => null,
            'shortage'       => true,
            'cognitive_type' => $slot['cognitive_type'] ?? null,
            'sub_theme'      => $slot['sub_domain'] ?? null,
            'language'       => $language,
        ];
    }

    private function buildCompactLog(array $globalActual, array $perRoundActual): string
    {
        $g = sprintf(
            '%dR/%dRa/%dD',
            $globalActual['recognition'] ?? 0,
            $globalActual['reasoning'] ?? 0,
            $globalActual['deceptive_trap'] ?? 0,
        );
        $rounds = [];
        foreach ($perRoundActual as $r) {
            $rounds[] = sprintf(
                '%d/%d/%d',
                $r['recognition'] ?? 0,
                $r['reasoning'] ?? 0,
                $r['deceptive_trap'] ?? 0,
            );
        }
        return "{$g}, per-round " . implode(' | ', $rounds);
    }
}
