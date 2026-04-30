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
     * Returns deficits sorted by descending shortfall.
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

        $rows = [];

        foreach ($this->enumerateProfiles($config) as $profile) {
            foreach ($config['general_sub_domains'] as $subDomain) {
                $domain = $subDomain; // domain = sub_domain (consistent w/ seeder)

                foreach ($profile['cognitive_mix'] as $cogType => $shareOf100) {
                    if ($shareOf100 <= 0) {
                        continue;
                    }

                    $required = $targetMatches;

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
                            'mode' => $profile['mode'],
                            'division' => $profile['division'],
                            'mode_target' => $profile['mode_target'],
                            'depth_range' => $profile['depth_range'],
                            'domain' => $domain,
                            'sub_domain' => $subDomain,
                            'cognitive_type' => $cogType,
                            'question_type' => 'qcm',
                            'language' => $lang,
                            'required' => $required,
                            'present' => $present,
                            'deficit' => $deficit,
                        ];
                    }
                }
            }
        }

        usort($rows, fn ($a, $b) => $b['deficit'] <=> $a['deficit']);

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
            $query->where('qg.validated', true);
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
     * @return iterable<array{
     *     mode:string, division:string|int|null,
     *     mode_target:array, depth_range:array{int,int},
     *     cognitive_mix:array<string,int>
     * }>
     */
    public function enumerateProfiles(array $config): iterable
    {
        // Solo student bands (one profile per band, mid-level used as label).
        foreach ($config['student_bands'] as $band) {
            [$from, $to] = $band['levels'];
            yield [
                'mode' => 'solo',
                'division' => "{$from}-{$to}",
                'mode_target' => ['type' => 'solo_range', 'levels' => [$from, $to]],
                'depth_range' => $band['depth_range'],
                'cognitive_mix' => $config['student_cognitive_mix'],
            ];
        }

        // Boss profiles.
        foreach ($config['boss_profiles'] as $bossLevel => $bossProfile) {
            yield [
                'mode' => 'boss',
                'division' => $bossLevel,
                'mode_target' => ['type' => 'boss', 'level' => (int) $bossLevel],
                'depth_range' => [$bossProfile['depth'], $bossProfile['depth']],
                'cognitive_mix' => $bossProfile['mix'],
            ];
        }

        // Duo / MJ / Ligue divisions.
        foreach ($config['mode_mappings'] as $mode => $divisions) {
            foreach ($divisions as $division => $target) {
                if ($target['type'] === 'solo_range') {
                    $depthRange = $this->depthRangeForSoloRange($target['levels'], $config);
                    $cogMix = $config['student_cognitive_mix'];
                } else {
                    $bossProfile = $config['boss_profiles'][$target['level']] ?? null;
                    if (!$bossProfile) {
                        continue;
                    }
                    $depthRange = [$bossProfile['depth'], $bossProfile['depth']];
                    $cogMix = $bossProfile['mix'];
                }
                yield [
                    'mode' => $mode,
                    'division' => $division,
                    'mode_target' => $target,
                    'depth_range' => $depthRange,
                    'cognitive_mix' => $cogMix,
                ];
            }
        }
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
