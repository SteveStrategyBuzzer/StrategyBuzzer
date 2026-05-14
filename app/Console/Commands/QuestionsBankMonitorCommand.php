<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * questions:bank:monitor
 *
 * Runtime observation dashboard for PATCH GROUP 5 (Worker Density Targeting).
 * Shows the 4 KPIs the team tracks after the strategy change:
 *
 *   KPI-1  Depth growth      — groups at depth 3/4/5 vs baseline T=0
 *   KPI-2  Family diversity  — unique families, new ones, saturation flags
 *   KPI-3  Overlap proxy     — most-reused groups (high usage_count)
 *   KPI-4  Worker health     — last cycle timestamp, generation rate (1h)
 */
class QuestionsBankMonitorCommand extends Command
{
    protected $signature = 'questions:bank:monitor
        {--sub_domain= : Filter a single sub_domain}
        {--lang=       : Filter a single language for translation counts}
        {--guards      : Show only KPI-5 guard statistics (fast)}
        {--reset-stats : Delete all-time guard stats from Redis before display}';

    protected $description = 'Runtime observation dashboard — depth growth, family diversity, overlap proxy, worker health.';

    // Baseline captured at T=0 (2026-05-13, after PATCH GROUP 5 deploy).
    private const BASELINE = [
        3  => 4,
        4  => 144,
        5  => 147,
        6  => 288,
        7  => 432,
        8  => 432,
        9  => 432,
        10 => 269,
    ];

    // Worker writes this key to Redis after every successful cycle.
    private const REDIS_LAST_SUCCESS = 'qb_worker:last_success';

    public function handle(): int
    {
        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════╗');
        $this->line('║    StrategyBuzzer — Bank Monitor  [PATCH GROUP 5 + QUALITÉ] ║');
        $this->line('╚══════════════════════════════════════════════════════════════╝');
        $this->line('  Snapshot at: ' . now()->format('Y-m-d H:i:s T'));
        $this->line('');

        if ($this->option('guards')) {
            $this->kpi5GuardStats();
        } else {
            $this->kpi1DepthGrowth();
            $this->kpi2FamilyDiversity();
            $this->kpi3OverlapProxy();
            $this->kpi4WorkerHealth();
            $this->kpi5GuardStats();
        }

        $this->line('');
        $this->line('  Run again anytime: php artisan questions:bank:monitor');
        $this->line('  Guard stats only : php artisan questions:bank:monitor --guards');
        $this->line('  Reset all-time   : php artisan questions:bank:monitor --guards --reset-stats');
        $this->line('');

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI-1  Depth growth
    // ─────────────────────────────────────────────────────────────────
    private function kpi1DepthGrowth(): void
    {
        $this->line('┌─ KPI-1  DEPTH GROWTH ─────────────────────────────────────────┐');

        $sub = $this->option('sub_domain');

        $query = DB::table('question_groups')
            ->select('difficulty_depth', DB::raw('COUNT(*) as n'))
            ->where('validated', true);

        if ($sub) {
            $query->where('sub_domain', $sub);
        }

        $rows = $query
            ->groupBy('difficulty_depth')
            ->orderBy('difficulty_depth')
            ->get()
            ->keyBy('difficulty_depth');

        $totalNow      = 0;
        $totalBaseline = 0;

        $this->line(sprintf(
            '  %-7s %7s %7s %7s  %s',
            'depth', 'T=0', 'now', 'delta', 'bar'
        ));
        $this->line('  ' . str_repeat('─', 60));

        foreach (range(3, 10) as $d) {
            $now      = (int) ($rows->get($d)->n ?? 0);
            $baseline = $sub ? 0 : (self::BASELINE[$d] ?? 0);
            $delta    = $now - $baseline;
            $bar      = str_repeat('█', min(40, (int) ($now / 8)));
            $prio     = $d <= 4 ? ' ← PRIORITY' : ($d <= 6 ? ' ← fill' : '');
            $sign     = $delta >= 0 ? "+{$delta}" : (string) $delta;

            $totalNow      += $now;
            $totalBaseline += $baseline;

            $this->line(sprintf(
                '  depth=%-2d %7d %7d %7s  %s%s',
                $d, $baseline, $now, $sign, $bar, $prio
            ));
        }

        $totalDelta = $totalNow - $totalBaseline;
        $totalSign  = $totalDelta >= 0 ? "+{$totalDelta}" : (string) $totalDelta;
        $this->line('  ' . str_repeat('─', 60));
        $this->line(sprintf(
            '  %-7s %7d %7d %7s',
            'TOTAL', $totalBaseline, $totalNow, $totalSign
        ));
        $this->line('└───────────────────────────────────────────────────────────────┘');
        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI-2  Family diversity
    // ─────────────────────────────────────────────────────────────────
    private function kpi2FamilyDiversity(): void
    {
        $this->line('┌─ KPI-2  FAMILY DIVERSITY ─────────────────────────────────────┐');

        // Unique families total and per depth-band
        $byDepth = DB::table('question_groups')
            ->select('difficulty_depth', DB::raw('COUNT(DISTINCT concept_family) as uniq_fam'), DB::raw('COUNT(*) as grps'))
            ->where('validated', true)
            ->whereNotNull('concept_family')
            ->where('concept_family', '!=', '')
            ->groupBy('difficulty_depth')
            ->orderBy('difficulty_depth')
            ->get();

        $totalUniq = DB::table('question_groups')
            ->where('validated', true)
            ->whereNotNull('concept_family')
            ->where('concept_family', '!=', '')
            ->distinct('concept_family')
            ->count('concept_family');

        $this->line("  Familles uniques dans la banque : {$totalUniq}");
        $this->line('');
        $this->line(sprintf('  %-7s %12s %10s %12s', 'depth', 'uniq_fam', 'groupes', 'grp/fam'));
        $this->line('  ' . str_repeat('─', 50));

        foreach ($byDepth as $r) {
            $ratio = $r->uniq_fam > 0 ? round($r->grps / $r->uniq_fam, 1) : 0;
            $this->line(sprintf(
                '  depth=%-2d %12d %10d %12.1f',
                $r->difficulty_depth, $r->uniq_fam, $r->grps, $ratio
            ));
        }

        // Top 10 families (potential saturation flags)
        $top = DB::table('question_groups')
            ->select('concept_family', DB::raw('COUNT(*) as n'))
            ->where('validated', true)
            ->whereNotNull('concept_family')
            ->where('concept_family', '!=', '')
            ->groupBy('concept_family')
            ->orderByDesc('n')
            ->limit(12)
            ->get();

        $this->line('');
        $this->line('  Top familles par nb de groupes (⚠ = ≥12, bloquée par worker) :');
        foreach ($top as $f) {
            $flag = $f->n >= 12 ? ' ⚠ SATURÉE' : '';
            $this->line(sprintf('    %-52s %3d%s', $f->concept_family, $f->n, $flag));
        }

        // Familles nouvelles à depth ≤ 6 (shallow = fruit du PATCH GROUP 5)
        $shallowFams = DB::table('question_groups')
            ->select('concept_family', 'difficulty_depth', DB::raw('MIN(created_at) as first_seen'))
            ->where('validated', true)
            ->where('difficulty_depth', '<=', 6)
            ->whereNotNull('concept_family')
            ->where('concept_family', '!=', '')
            ->groupBy('concept_family', 'difficulty_depth')
            ->orderBy('first_seen', 'desc')
            ->limit(10)
            ->get();

        $this->line('');
        $this->line('  Dernières familles créées à depth ≤ 6 (nouvelles générations) :');
        if ($shallowFams->isEmpty()) {
            $this->line('    (aucune encore — worker démarre)');
        }
        foreach ($shallowFams as $f) {
            $this->line(sprintf(
                '    depth=%d  %-50s  %s',
                $f->difficulty_depth, $f->concept_family, substr($f->first_seen, 0, 16)
            ));
        }

        $this->line('└───────────────────────────────────────────────────────────────┘');
        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI-3  Overlap proxy (most reused groups)
    // ─────────────────────────────────────────────────────────────────
    private function kpi3OverlapProxy(): void
    {
        $this->line('┌─ KPI-3  OVERLAP PROXY (groupes les plus réutilisés) ──────────┐');

        // Groups used ≥ 3 times (proxy for between-game overlap)
        $overused = DB::table('question_groups')
            ->where('validated', true)
            ->where('usage_count', '>=', 3)
            ->count();

        $total = DB::table('question_groups')->where('validated', true)->count();

        $pct = $total > 0 ? round($overused / $total * 100, 1) : 0;

        $this->line("  Groupes réutilisés ≥3 fois : {$overused} / {$total} ({$pct}%)");
        $this->line('  (objectif : ce pourcentage baisse à mesure que la banque s\'épaissit)');
        $this->line('');

        // Distribution usage_count
        $dist = DB::table('question_groups')
            ->select('usage_count', DB::raw('COUNT(*) as n'))
            ->where('validated', true)
            ->groupBy('usage_count')
            ->orderBy('usage_count')
            ->limit(10)
            ->get();

        $this->line('  Distribution usage_count :');
        foreach ($dist as $d) {
            $bar = str_repeat('▪', min(30, (int) ($d->n / 3)));
            $this->line(sprintf('    used=%-3d : %4d groupes  %s', $d->usage_count, $d->n, $bar));
        }

        // Top 10 most reused with depth info
        $top = DB::table('question_groups')
            ->select('id', 'sub_domain', 'cognitive_type', 'difficulty_depth', 'concept_family', 'usage_count')
            ->where('validated', true)
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get();

        $this->line('');
        $this->line('  Top 10 groupes les plus utilisés :');
        $this->line(sprintf('  %-6s %-7s %-14s %-12s %-40s %s', 'id', 'depth', 'sub_domain', 'cog_type', 'concept_family', 'used'));
        $this->line('  ' . str_repeat('─', 90));
        foreach ($top as $r) {
            $this->line(sprintf(
                '  %-6d %-7d %-14s %-12s %-40s %d',
                $r->id,
                $r->difficulty_depth,
                substr($r->sub_domain, 0, 13),
                substr($r->cognitive_type, 0, 11),
                substr($r->concept_family ?? '-', 0, 39),
                $r->usage_count
            ));
        }

        $this->line('└───────────────────────────────────────────────────────────────┘');
        $this->line('');
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI-4  Worker health
    // ─────────────────────────────────────────────────────────────────
    private function kpi4WorkerHealth(): void
    {
        $this->line('┌─ KPI-4  WORKER HEALTH ────────────────────────────────────────┐');

        // Groups created in last hour
        $lastHour = DB::table('question_groups')
            ->where('validated', true)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $last24h = DB::table('question_groups')
            ->where('validated', true)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        $this->line("  Groupes validés créés (1h)  : {$lastHour}");
        $this->line("  Groupes validés créés (24h) : {$last24h}");

        // Breakdown by depth for last 24h
        $byDepth24h = DB::table('question_groups')
            ->select('difficulty_depth', DB::raw('COUNT(*) as n'))
            ->where('validated', true)
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('difficulty_depth')
            ->orderBy('difficulty_depth')
            ->get();

        if ($byDepth24h->isNotEmpty()) {
            $this->line('');
            $this->line('  Créés dans les 24h par depth :');
            foreach ($byDepth24h as $r) {
                $prio = $r->difficulty_depth <= 4 ? ' ← PRIORITY ✓' : ($r->difficulty_depth <= 6 ? ' ← fill ✓' : '');
                $this->line(sprintf('    depth=%-2d : %3d groupes%s', $r->difficulty_depth, $r->n, $prio));
            }
        }

        // Languages being generated (last 24h via question_translations)
        $langActivity = DB::table('question_translations')
            ->join('question_groups', 'question_groups.id', '=', 'question_translations.question_group_id')
            ->select('question_translations.language', DB::raw('COUNT(*) as n'))
            ->where('question_groups.created_at', '>=', now()->subDay())
            ->groupBy('question_translations.language')
            ->orderBy('question_translations.language')
            ->get();

        if ($langActivity->isNotEmpty()) {
            $this->line('');
            $this->line('  Langues actives (24h) :');
            foreach ($langActivity as $l) {
                $this->line(sprintf('    %-5s : %d nouvelles traductions', $l->language, $l->n));
            }
        }

        // ── Heartbeat & semaphore status (resilience observability) ─────────────
        $this->line('');

        // Heartbeat: set by worker every cycle with 90s TTL.
        $heartbeatAge = null;
        $heartbeatStatus = '⚠  ABSENT (worker arrêté ou bloqué >90s)';
        try {
            $hbRaw = Redis::get('qb:worker:heartbeat');
            if ($hbRaw && is_numeric($hbRaw)) {
                $heartbeatAge    = time() - (int) $hbRaw;
                $heartbeatStatus = $heartbeatAge <= 30
                    ? '✅ VIVANT  (' . $heartbeatAge . 's ago)'
                    : ($heartbeatAge <= 90
                        ? '🟡 LENT    (' . $heartbeatAge . 's ago — cycle long ou backoff)'
                        : '⚠  STALE   (' . $heartbeatAge . 's ago — TTL expiré, worker mort?)');
            }
        } catch (\Throwable) {}

        // Semaphore: should exist while worker is alive.
        $semKey    = config('question_bank_profiles.worker.redis_keys.semaphore', 'qb:worker:lock');
        $semStatus = '(absent — worker non démarré ou arrêt propre)';
        try {
            $semVal = Redis::get($semKey);
            $semTtl = $semVal ? Redis::ttl($semKey) : null;
            if ($semVal && $semTtl !== null) {
                $semStatus = 'TENU — TTL=' . $semTtl . 's  token=' . substr($semVal, 0, 28) . '…';
            }
        } catch (\Throwable) {}

        $this->line('  Heartbeat worker        : ' . $heartbeatStatus);
        $this->line('  Semaphore Redis         : ' . $semStatus);

        // Most recent group created_at
        $newest = DB::table('question_groups')
            ->where('validated', true)
            ->max('created_at');
        $this->line('  Dernier groupe créé à   : ' . ($newest ?? 'N/A'));

        $this->line('└───────────────────────────────────────────────────────────────┘');
    }

    // ─────────────────────────────────────────────────────────────────
    // KPI-5  Guard statistics (PATCH GROUP QUALITÉ CONTENU)
    //
    // NOTE: We read each guard code directly via Redis::get('qb:worker:guard_stats:CODE')
    // instead of using Redis::keys() + Redis::get(rawKey). The Laravel Redis facade applies
    // the connection prefix automatically on both set and get, so using raw keys returned by
    // KEYS would double-apply the prefix and always return null.
    // ─────────────────────────────────────────────────────────────────
    private function kpi5GuardStats(): void
    {
        $this->line('┌─ KPI-5  GUARD STATISTICS (rejections par type) ───────────────┐');

        // All known guard codes — exact strings returned by QualityGuards::evaluate().
        // Key = Redis key suffix (= the 'code' field); value = human label.
        $allCodes = [
            // Existing guards (guards 1-10 range)
            'missing_translations'     => 'Guard 01 — traduction manquante',
            'missing_saviez_vous'      => 'Guard 02 — saviez_vous absent/court',
            'answer_key_misaligned'    => 'Guard 03 — clé réponse incorrecte',
            'dup_concept_id'           => 'Guard 04 — concept_id dupliqué',
            'concept_family_share'     => 'Guard 05 — famille trop dominante',
            'text_similarity'          => 'Guard 06 — similarité texte',
            'cognitive_mismatch'       => 'Guard 07 — incohérence cognitive',
            'depth_incoherent'         => 'Guard 08 — profondeur incohérente',
            'correct_answer_overused'  => 'Guard 09 — réponse correcte surexposée',
            'saviez_vous_off_topic'    => 'Guard 10 — saviez_vous hors sujet',
            // New guards 11-14 (PATCH GROUP QUALITÉ CONTENU)
            'question_too_long'        => 'Guard 11 — question trop longue  ★',
            'answer_too_long'          => 'Guard 12 — réponse trop longue   ★',
            'saviez_vous_too_long'     => 'Guard 13 — saviez_vous trop long ★',
            'negative_framing'         => 'Guard 14 — formulation négative  ★',
        ];

        $qualityCodes = [
            'question_too_long', 'answer_too_long',
            'saviez_vous_too_long', 'negative_framing',
        ];

        // Optional reset — delete each known key directly (avoids KEYS prefix issue).
        if ($this->option('reset-stats')) {
            try {
                foreach (array_keys($allCodes) as $code) {
                    Redis::del('qb:worker:guard_stats:' . $code);
                    Redis::del('qb:worker:guard_stats:session:' . $code);
                }
                Redis::del('qb:worker:guard_stats:_total');
                Redis::del('qb:worker:guard_stats:session:_total');
                $this->line('  ⚠  Compteurs all-time réinitialisés.');
            } catch (\Throwable) {
                $this->line('  ⚠  Impossible de réinitialiser (Redis indisponible).');
            }
        }

        // Session start timestamp
        $sessionTs = null;
        try {
            $raw = Redis::get('qb:worker:guard_stats:session_ts');
            if ($raw && is_numeric($raw)) {
                $sessionTs = now()->createFromTimestamp((int) $raw)->format('Y-m-d H:i:s T');
            }
        } catch (\Throwable) {}

        $this->line('  Session démarrée à : ' . ($sessionTs ?? '(worker non encore démarré)'));
        $this->line('');

        // Read counters for each known code directly.
        $stats = [];
        $allTotal     = 0;
        $sessionTotal = 0;

        foreach (array_keys($allCodes) as $code) {
            try {
                $all     = (int) (Redis::get('qb:worker:guard_stats:' . $code) ?? 0);
                $session = (int) (Redis::get('qb:worker:guard_stats:session:' . $code) ?? 0);
            } catch (\Throwable) {
                $all = $session = 0;
            }
            $stats[$code] = ['all' => $all, 'session' => $session];
            $allTotal     += $all;
            $sessionTotal += $session;
        }

        // Also check if there are unknown codes in the rolling reject list
        // (guards added later or misspelled), just for the recent-reject display.
        $rejectKey = config('question_bank_profiles.worker.redis_keys.last_rejects', 'qb:worker:last_rejects');
        $recent    = [];
        try {
            $recent = Redis::lrange($rejectKey, 0, 9) ?: [];
        } catch (\Throwable) {}

        // Display
        $this->line(sprintf(
            '  %-38s %8s %12s',
            'guard_code', 'all-time', 'cette session'
        ));
        $this->line('  ' . str_repeat('─', 64));

        $this->line('  — Guards qualité contenu (★ PATCH QUALITÉ) :');
        foreach ($qualityCodes as $code) {
            $v   = $stats[$code];
            $pct = $allTotal > 0 ? round($v['all'] / $allTotal * 100, 1) : 0.0;
            $bar = $sessionTotal > 0 ? str_repeat('▪', min(18, (int) round($v['session'] / $sessionTotal * 18))) : '';
            $this->line(sprintf(
                '    %-36s %6d (%4.1f%%)  %6d  %s',
                $code, $v['all'], $pct, $v['session'], $bar
            ));
        }

        $this->line('');
        $this->line('  — Autres guards :');
        foreach ($stats as $code => $v) {
            if (in_array($code, $qualityCodes, true)) {
                continue;
            }
            $pct = $allTotal > 0 ? round($v['all'] / $allTotal * 100, 1) : 0.0;
            $bar = $sessionTotal > 0 ? str_repeat('▪', min(18, (int) round($v['session'] / $sessionTotal * 18))) : '';
            $this->line(sprintf(
                '    %-36s %6d (%4.1f%%)  %6d  %s',
                $code, $v['all'], $pct, $v['session'], $bar
            ));
        }

        $this->line('  ' . str_repeat('─', 64));
        $this->line(sprintf(
            '  %-38s %6d         %6d',
            'TOTAL rejections', $allTotal, $sessionTotal
        ));

        // Last 10 rejects
        if (!empty($recent)) {
            $this->line('');
            $this->line('  Derniers rejets (heure · code · sous-domaine · détail) :');
            foreach ($recent as $rawEntry) {
                $item = json_decode($rawEntry, true);
                if (!$item) {
                    continue;
                }
                $ts  = isset($item['ts']) ? date('H:i:s', $item['ts']) : '?';
                $cd  = str_pad($item['code'] ?? '?', 28);
                $sub = str_pad($item['segment']['sub_domain'] ?? '?', 14);
                $det = mb_substr($item['detail'] ?? '', 0, 55);
                $this->line(sprintf('    %s  %s  %s  %s', $ts, $cd, $sub, $det));
            }
        }

        $this->line('└───────────────────────────────────────────────────────────────┘');
    }
}
