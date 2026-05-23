<?php

namespace App\Services\QuestionBank\Worker;

use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Computes per-segment deficits in the question bank.
 *
 * A "segment" is the full tuple the planner of #81 cares about:
 *   (level | boss_level, depth, domain, sub_domain, cognitive_type,
 *    question_type, language).
 *
 * The calculator iterates every official profile (Solo bands, Boss tiers,
 * Duo divisions, MJ divisions, Ligue divisions), expands "Général" into
 * the 8 sub-domains, applies the cognitive mix from
 * config/question_bank_profiles.php, and asks:
 *
 *   "How many groups does this segment need so the planner can build N
 *    distinct matches without recycling a question within T days?"
 *
 * Output is sorted by absolute deficit DESC so the worker fills the
 * scarcest segments first. The endpoint shape is intentionally simple
 * (an array of arrays) so it can be JSON-serialised straight into the
 * health endpoint.
 */
class BankNeedsCalculator
{
    public function __construct(
        private readonly QuestionBankRepository $repo
    ) {}

    /**
     * Returns deficits sorted by descending priority_score (deficit × depth
     * weight), then by deficit. Shallower depths (3-4) carry a ×4 weight so
     * they always outrank equal-deficit deep rows (9-10 weight ×0.5).
     *
     * @return array<int, array{
     *     mode: string,
     *     division: string|int|null,
     *     mode_target: array,
     *     depth_range: array{int,int},
     *     domain: string,
     *     sub_domain: string,
     *     cognitive_type: string,
     *     question_type: string,
     *     language: string,
     *     required: int,
     *     present: int,
     *     deficit: int,
     *     priority_score: int,
     *     forbidden_families: list<string>,
     * }>
     */
    public function computeDeficits(?int $limit = null): array
    {
        $config = config('question_bank_profiles');
        $worker = $config['worker'];

        $targetMatches = max(1, (int) $worker['target_matches_per_profile']);
        $languages = $worker['preferred_languages'];

        // Single GROUP BY query → hashmap. Iterating profiles × sub_domains
        // × cog_types × languages would otherwise mean ~10k SQL queries per
        // cycle. The hashmap lookup makes the rest of the function pure-PHP.
        $counts = $this->loadCountsMap($languages);

        // One extra query to detect per-segment saturated families so the
        // generator avoids deepening already-dominant concept_family trees.
        $saturatedFamilies = $this->loadSaturatedFamilies();

        $rows = [];

        foreach ($this->enumerateProfiles($config) as $profile) {
            foreach ($config['general_sub_domains'] as $subDomain) {
                $domain = $subDomain; // domain = sub_domain (consistent w/ seeder)

                foreach ($profile['cognitive_mix'] as $cogType => $shareOf100) {
                    if ($shareOf100 <= 0) {
                        continue;
                    }

                    $required = $targetMatches;

                    // depth_range is now always [d, d] (single-depth slice from
                    // enumerateProfiles). Use depth_range[1] for the family key.
                    $famKey = sprintf('%s|%s|%d', $subDomain, $cogType, $profile['depth_range'][1]);

                    foreach ($languages as $lang) {
                        $present = $this->countFromMap(
                            $counts,
                            $profile['mode_target'],
                            $profile['depth_range'],
                            $domain,
                            $subDomain,
                            $cogType,
                            $lang
                        );

                        $deficit = max(0, $required - $present);
                        if ($deficit === 0) {
                            continue;
                        }

                        $rows[] = [
                            'mode'               => $profile['mode'],
                            'division'           => $profile['division'],
                            'mode_target'        => $profile['mode_target'],
                            'depth_range'        => $profile['depth_range'],
                            'domain'             => $domain,
                            'sub_domain'         => $subDomain,
                            'cognitive_type'     => $cogType,
                            'question_type'      => 'qcm',
                            'language'           => $lang,
                            'required'           => $required,
                            'present'            => $present,
                            'deficit'            => $deficit,
                            'forbidden_families' => $saturatedFamilies[$famKey] ?? [],
                        ];
                    }
                }
            }
        }

        // Compute depth-weighted priority score: shallower depths are filled
        // before deep ones, preventing the worker from spending cycles on
        // depth=9/10 (already ≥15/segment) while depth=3/4 sits at 0-5.
        foreach ($rows as &$row) {
            $row['priority_score'] = (int) round(
                $row['deficit'] * $this->depthWeight((int) $row['depth_range'][1])
            );
        }
        unset($row);

        usort($rows, function ($a, $b) {
            $cmp = $b['priority_score'] <=> $a['priority_score'];
            return $cmp !== 0 ? $cmp : ($b['deficit'] <=> $a['deficit']);
        });

        // Auto-remediation (#99): when BankSelfHealer pinned a priority
        // segment, prepend rows whose (mode, sub_domain, language) match
        // so the worker attacks that tuple first. The rest of the sort
        // order is preserved so unrelated segments still drain by
        // largest-deficit-first.
        $rows = $this->prependPrioritySegmentRows($rows);

        if ($limit !== null) {
            $rows = array_slice($rows, 0, $limit);
        }

        return $rows;
    }

    /**
     * Reads `qb:worker:priority_segment` (written by BankSelfHealer) and
     * pulls every matching deficit row to the front of the list. Match
     * is on language + mode_target (boss vs. solo + level falls inside
     * the band) + sub_domain (case-insensitive). The "general" theme
     * matches every sub-domain so a global dry alert still surfaces.
     */
    private function prependPrioritySegmentRows(array $rows): array
    {
        try {
            $key = (string) config('question_bank_profiles.worker.redis_keys.priority_segment');
            if ($key === '') {
                return $rows;
            }
            $raw = Redis::get($key);
            if (!is_string($raw) || $raw === '') {
                return $rows;
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return $rows;
            }
            $until = (int) ($decoded['until_ts'] ?? 0);
            if ($until > 0 && $until <= time()) {
                return $rows;
            }
            $segment = $decoded['segment'] ?? null;
            if (!is_array($segment)) {
                return $rows;
            }

            $theme = isset($segment['theme']) ? (string) $segment['theme'] : null;
            $language = isset($segment['language']) ? (string) $segment['language'] : null;
            $isBoss = (bool) ($segment['is_boss'] ?? false);
            $niveau = (int) ($segment['niveau'] ?? 0);

            $matches = [];
            $rest = [];
            foreach ($rows as $row) {
                if ($this->rowMatchesPrioritySegment($row, $theme, $language, $isBoss, $niveau)) {
                    $matches[] = $row;
                } else {
                    $rest[] = $row;
                }
            }
            return array_merge($matches, $rest);
        } catch (Throwable $e) {
            Log::warning('[BankNeedsCalculator] priority segment read failed (non-fatal)', [
                'error' => $e->getMessage(),
            ]);
            return $rows;
        }
    }

    private function rowMatchesPrioritySegment(
        array $row,
        ?string $theme,
        ?string $language,
        bool $isBoss,
        int $niveau
    ): bool {
        if ($language !== null && $language !== '' && ($row['language'] ?? null) !== $language) {
            return false;
        }
        $modeTarget = $row['mode_target'] ?? [];
        $type = $modeTarget['type'] ?? null;
        if ($isBoss) {
            if ($type !== 'boss') {
                return false;
            }
            if ($niveau > 0 && (int) ($modeTarget['level'] ?? 0) !== $niveau) {
                return false;
            }
        } else {
            if ($type !== 'solo_range') {
                return false;
            }
            if ($niveau > 0) {
                $levels = $modeTarget['levels'] ?? [0, 0];
                $from = (int) ($levels[0] ?? 0);
                $to = (int) ($levels[1] ?? 0);
                if ($niveau < $from || $niveau > $to) {
                    return false;
                }
            }
        }

        if ($theme !== null && $theme !== '' && mb_strtolower($theme) !== 'general' && mb_strtolower($theme) !== 'général') {
            $rowSub = mb_strtolower((string) ($row['sub_domain'] ?? ''));
            if ($rowSub !== mb_strtolower($theme)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Estimates how many full matches the bank can build today for each
     * profile, without recycling. Used by the health endpoint to surface
     * "you have ~17 Duo intermédiaire matches before rupture".
     *
     * @return array<int, array{
     *     mode: string,
     *     division: string|int|null,
     *     language: string,
     *     matches_buildable: int,
     *     limiting_segment: array{cognitive_type:string, sub_domain:string, present:int}|null,
     * }>
     */
    public function estimateMatchesBuildable(?string $language = null): array
    {
        $config = config('question_bank_profiles');
        $worker = $config['worker'];
        $languages = $language ? [$language] : $worker['preferred_languages'];

        // Same single-query strategy as computeDeficits, but restricted to
        // validated rows only (a match can't be built from unvalidated AI
        // candidates).
        $counts = $this->loadCountsMap($languages, validatedOnly: true);

        $out = [];

        foreach ($this->enumerateProfiles($config) as $profile) {
            foreach ($languages as $lang) {
                $minBuildable = PHP_INT_MAX;
                $limiting = null;

                foreach ($profile['cognitive_mix'] as $cogType => $shareOf100) {
                    if ($shareOf100 <= 0) {
                        continue;
                    }

                    foreach ($config['general_sub_domains'] as $subDomain) {
                        $present = $this->countFromMap(
                            $counts,
                            $profile['mode_target'],
                            $profile['depth_range'],
                            $subDomain,
                            $subDomain,
                            $cogType,
                            $lang
                        );

                        if ($present < $minBuildable) {
                            $minBuildable = $present;
                            $limiting = [
                                'cognitive_type' => $cogType,
                                'sub_domain' => $subDomain,
                                'present' => $present,
                            ];
                        }
                    }
                }

                $out[] = [
                    'mode' => $profile['mode'],
                    'division' => $profile['division'],
                    'language' => $lang,
                    'matches_buildable' => $minBuildable === PHP_INT_MAX ? 0 : $minBuildable,
                    'limiting_segment' => $limiting,
                ];
            }
        }

        return $out;
    }

    /**
     * Loads counts for every (segment × language) tuple that has at least
     * one row in the bank in a single GROUP BY query. Returns a nested
     * hashmap keyed by composite string for O(1) lookups.
     */
    private function loadCountsMap(array $languages, bool $validatedOnly = false): array
    {
        $query = DB::table('question_groups as qg')
            ->join('question_translations as qt', 'qt.question_group_id', '=', 'qg.id')
            ->whereIn('qt.language', $languages)
            ->select([
                'qg.difficulty_level',
                'qg.boss_level',
                'qg.difficulty_depth',
                'qg.sub_domain',
                'qg.cognitive_type',
                'qt.language',
                DB::raw('COUNT(*) as n'),
            ])
            ->groupBy('qg.difficulty_level', 'qg.boss_level', 'qg.difficulty_depth', 'qg.sub_domain', 'qg.cognitive_type', 'qt.language');

        if ($validatedOnly) {
            // LOT 3-A : seuls les groupes ready_bank certifiés comptent
            // dans le calcul des besoins. Les groupes non certifiés
            // (post_review_status IS NULL) ne sont pas comptabilisés.
            $query->where('qg.post_review_status', 'ready_bank');
        }

        $rows = $query->get();

        $map = [];
        foreach ($rows as $r) {
            $key = sprintf(
                '%s|%s|%d|%s|%s|%s',
                $r->difficulty_level === null ? 'null' : (string) $r->difficulty_level,
                $r->boss_level === null ? 'null' : (string) $r->boss_level,
                (int) $r->difficulty_depth,
                $r->sub_domain,
                $r->cognitive_type,
                $r->language,
            );
            $map[$key] = (int) $r->n;
        }
        return $map;
    }

    private function countFromMap(
        array $map,
        array $modeTarget,
        array $depthRange,
        string $domain,
        string $subDomain,
        string $cogType,
        string $language
    ): int {
        // We have to fold over every level in mode_target and every depth
        // in depth_range, summing matching rows. With 1-9 levels max and
        // 1-3 depths max, this is at worst 27 hashmap reads per call.
        $levels = [];
        $bossLevels = [];
        if (($modeTarget['type'] ?? null) === 'solo_range') {
            $levels = range($modeTarget['levels'][0], $modeTarget['levels'][1]);
        } elseif (($modeTarget['type'] ?? null) === 'boss') {
            $bossLevels = [(int) $modeTarget['level']];
        }

        $depths = range($depthRange[0], $depthRange[1]);

        $total = 0;
        foreach ($depths as $depth) {
            if (!empty($levels)) {
                foreach ($levels as $lvl) {
                    $key = sprintf('%d|null|%d|%s|%s|%s', $lvl, $depth, $subDomain, $cogType, $language);
                    $total += $map[$key] ?? 0;
                }
            }
            foreach ($bossLevels as $bl) {
                $key = sprintf('null|%d|%d|%s|%s|%s', $bl, $depth, $subDomain, $cogType, $language);
                $total += $map[$key] ?? 0;
            }
        }
        return $total;
    }

    /**
     * Enumerates every profile the worker / health endpoint cares about.
     *
     * Student bands and solo_range mode-mappings are expanded into one row
     * PER individual depth value so the worker can target depth=3 separately
     * from depth=4 instead of lumping them into a single [3,4] pool that
     * always resolves to depth_range[1]=4. Boss profiles already carry a
     * single depth so they are yielded as-is.
     *
     * @return iterable<array{
     *     mode:string, division:string|int|null,
     *     mode_target:array, depth_range:array{int,int},
     *     cognitive_mix:array<string,int>
     * }>
     */
    public function enumerateProfiles(array $config): iterable
    {
        // Solo student bands — one row per individual depth so the worker
        // targets depth=3 (currently 0 groups) before depth=4 (5 groups).
        foreach ($config['student_bands'] as $band) {
            [$from, $to] = $band['levels'];
            foreach (range($band['depth_range'][0], $band['depth_range'][1]) as $depth) {
                yield [
                    'mode'          => 'solo',
                    'division'      => "{$from}-{$to}",
                    'mode_target'   => ['type' => 'solo_range', 'levels' => [$from, $to]],
                    'depth_range'   => [$depth, $depth],
                    'cognitive_mix' => $config['student_cognitive_mix'],
                ];
            }
        }

        // Boss profiles — already single-depth, no expansion needed.
        foreach ($config['boss_profiles'] as $bossLevel => $bossProfile) {
            yield [
                'mode'          => 'boss',
                'division'      => $bossLevel,
                'mode_target'   => ['type' => 'boss', 'level' => (int) $bossLevel],
                'depth_range'   => [$bossProfile['depth'], $bossProfile['depth']],
                'cognitive_mix' => $bossProfile['mix'],
            ];
        }

        // Duo / MJ / Ligue divisions.
        // solo_range divisions: expand per-depth (same logic as student bands).
        // boss divisions: already single-depth.
        foreach ($config['mode_mappings'] as $mode => $divisions) {
            foreach ($divisions as $division => $target) {
                if ($target['type'] === 'solo_range') {
                    $baseRange = isset($target['depth_range'])
                        ? $target['depth_range']
                        : $this->depthRangeForSoloRange($target['levels'], $config);
                    $cogMix    = $config['student_cognitive_mix'];
                    foreach (range($baseRange[0], $baseRange[1]) as $depth) {
                        yield [
                            'mode'          => $mode,
                            'division'      => $division,
                            'mode_target'   => $target,
                            'depth_range'   => [$depth, $depth],
                            'cognitive_mix' => $cogMix,
                        ];
                    }
                } else {
                    $bossProfile = $config['boss_profiles'][$target['level']] ?? null;
                    if (!$bossProfile) {
                        continue;
                    }
                    yield [
                        'mode'          => $mode,
                        'division'      => $division,
                        'mode_target'   => $target,
                        'depth_range'   => [$bossProfile['depth'], $bossProfile['depth']],
                        'cognitive_mix' => $bossProfile['mix'],
                    ];
                }
            }
        }
    }

    /**
     * Priority multiplier for a given depth value. Shallower depths get a
     * higher multiplier so deficit rows for depth=3/4 always outrank
     * equal-deficit rows for depth=9/10 in the priority sort.
     *
     * Weights:
     *   depth ≤ 4  → ×4.0  (bands 1-9, 11-19 — nearly empty, most impact)
     *   depth ≤ 6  → ×3.0  (bands 21-39 — also thin)
     *   depth ≤ 8  → ×1.0  (bands 40-69 — already at target)
     *   depth > 8  → ×0.5  (bands 70-99 — de-prioritise depth 9-10)
     */
    private function depthWeight(int $depth): float
    {
        return match (true) {
            $depth <= 4 => 4.0,
            $depth <= 6 => 3.0,
            $depth <= 8 => 1.0,
            default     => 0.5,
        };
    }

    /**
     * Returns, per sub_domain, the list of concept_family names that already
     * hold ≥ 12 validated groups ACROSS ALL depths and cognitive_types in
     * that sub-domain. Checked at sub_domain granularity (not per exact
     * segment) because dominant families like "mammalian-anatomical-adaptation"
     * (23 groups total in Faune) are spread thin across 12 sub-cells, making
     * per-cell detection fail. The sub_domain-level check correctly flags
     * families that are globally dominant and should not be deepened further.
     *
     * The returned list is keyed by "sub_domain|cognitive_type|depth" so it
     * plugs into the per-segment row without changing the caller contract —
     * every segment of a given sub_domain inherits the same family blocklist.
     *
     * @return array<string, list<string>>
     */
    private function loadSaturatedFamilies(): array
    {
        $threshold = 12;

        // Aggregate at (sub_domain, concept_family) level — depth and
        // cognitive_type intentionally excluded so dominant cross-segment
        // families are caught regardless of where their groups live.
        $rows = DB::table('question_groups')
            ->select(
                'sub_domain',
                'concept_family',
                DB::raw('COUNT(*) as n')
            )
            ->whereNotNull('concept_family')
            ->where('concept_family', '!=', '')
            ->where('validated', true)
            ->groupBy('sub_domain', 'concept_family')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get();

        // Build (sub_domain → saturated families) map first.
        $bySubDomain = [];
        foreach ($rows as $r) {
            $bySubDomain[$r->sub_domain][] = (string) $r->concept_family;
        }

        if (empty($bySubDomain)) {
            return [];
        }

        // Fan out to all (sub_domain|cognitive_type|depth) keys so the
        // per-segment row lookup works without changing the caller contract.
        $config   = config('question_bank_profiles');
        $cogTypes = $config['cognitive_types'] ?? ['recognition', 'reasoning', 'deceptive_trap'];

        $map = [];
        foreach ($bySubDomain as $subDomain => $families) {
            foreach ($cogTypes as $cog) {
                for ($depth = 3; $depth <= 10; $depth++) {
                    $key       = sprintf('%s|%s|%d', $subDomain, $cog, $depth);
                    $map[$key] = $families;
                }
            }
        }

        return $map;
    }

    private function depthRangeForSoloRange(array $levels, array $config): array
    {
        [$from, $to] = $levels;
        foreach ($config['student_bands'] as $band) {
            [$bf, $bt] = $band['levels'];
            if ($from >= $bf && $to <= $bt) {
                return $band['depth_range'];
            }
        }
        // Fallback: use the band of the lowest level.
        foreach ($config['student_bands'] as $band) {
            [$bf, $bt] = $band['levels'];
            if ($from >= $bf && $from <= $bt) {
                return $band['depth_range'];
            }
        }
        return [5, 6];
    }
}
