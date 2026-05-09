<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Progressive burn-in checkpoint audit.
 *
 * Outputs a structured snapshot of every operational dimension:
 *   1. Worker runtime (alive, last_success, throughput, rate, semaphore)
 *   2. AI providers  (Gemini/OpenAI calls, ok/fail ratio, latency)
 *   3. Coverage      (cells covered, matches_buildable, depth progression)
 *   4. Infrastructure (RAM, disk, Redis memory, queue depth)
 *   5. Quality guards (reject types, reject rate, zh/ar stability)
 *
 * Usage:
 *   php artisan questions:bank:checkpoint          — full report
 *   php artisan questions:bank:checkpoint --json   — JSON output (CI/logging)
 *   php artisan questions:bank:checkpoint --since=60  — rolling window minutes
 */
class QuestionsBankCheckpointCommand extends Command
{
    protected $signature = 'questions:bank:checkpoint
                            {--json   : Output as JSON instead of human-readable}
                            {--since= : Rolling window in minutes for rate metrics (default 60)}';

    protected $description = 'Progressive burn-in checkpoint: worker, providers, coverage, infra, quality.';

    public function handle(): int
    {
        $windowMin  = max(1, (int) ($this->option('since') ?: 60));
        $asJson     = (bool) $this->option('json');
        $now        = time();
        $tsLabel    = date('Y-m-d H:i:s');

        $report = [
            'checkpoint_at'  => $tsLabel,
            'window_minutes' => $windowMin,
            'worker'         => $this->gatherWorker($now, $windowMin),
            'providers'      => $this->gatherProviders(),
            'coverage'       => $this->gatherCoverage(),
            'infrastructure' => $this->gatherInfra(),
            'quality'        => $this->gatherQuality($now, $windowMin),
        ];

        if ($asJson) {
            $this->line(json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->printReport($report);
        return self::SUCCESS;
    }

    // =========================================================================
    // 1. Worker runtime
    // =========================================================================

    private function gatherWorker(int $now, int $windowMin): array
    {
        $keys  = config('question_bank_profiles.worker.redis_keys');

        // Last success — stored as JSON since BankWorker #82+
        // {"ts": 1234567890, "group_id": 42, "segment": {...}}
        // Older workers stored a bare integer timestamp.
        $lsRaw = Redis::get($keys['last_success']);
        $ls    = 0;
        if ($lsRaw) {
            $decoded = json_decode($lsRaw, true);
            $ls = isset($decoded['ts']) ? (int) $decoded['ts'] : (int) $lsRaw;
        }
        $lsAge = $ls > 0 ? $now - $ls : null;

        // Semaphore (alive indicator)
        $sem   = Redis::get($keys['semaphore']);
        $alive = $sem !== null;

        // Rate buckets — rolling window
        $totalCalls = 0;
        for ($i = 0; $i < $windowMin; $i++) {
            $min    = (int) floor(($now - $i * 60) / 60);
            $bucket = sprintf($keys['rate_bucket'], $min);
            $totalCalls += (int) (Redis::get($bucket) ?? 0);
        }

        // Gen counters — ok vs err rolling window
        $okTotal  = 0;
        $errTotal = 0;
        for ($i = 0; $i < $windowMin; $i++) {
            $min      = (int) floor(($now - $i * 60) / 60);
            $okTotal  += (int) (Redis::get(sprintf($keys['gen_counter_ok'],  $min)) ?? 0);
            $errTotal += (int) (Redis::get(sprintf($keys['gen_counter_err'], $min)) ?? 0);
        }

        // Groups added to DB in this window
        $dbAdded = DB::table('question_groups')
            ->where('created_at', '>=', now()->subMinutes($windowMin))
            ->count();

        // Rate override (boost mode)
        $rateOverride = Redis::get($keys['rate_override'] ?? 'qb:worker:rate_override');
        $prioritySeg  = Redis::get($keys['priority_segment'] ?? 'qb:worker:priority_segment');

        // Queue depth
        $pending = DB::table('jobs')->count();
        $failed  = DB::table('failed_jobs')->count();

        $groupsPerHour = $windowMin > 0
            ? round($dbAdded * 60 / $windowMin, 1)
            : 0;

        return [
            'alive'                 => $alive,
            'semaphore_owner'       => $sem ? substr((string)$sem, 0, 30).'…' : null,
            'last_success_ago_sec'  => $lsAge,
            'last_success_human'    => $lsAge !== null
                ? $this->humanDuration($lsAge).' ago'
                : 'NEVER',
            'api_calls_window'      => $totalCalls,
            'gen_ok_window'         => $okTotal,
            'gen_err_window'        => $errTotal,
            'err_rate_pct'          => ($okTotal + $errTotal) > 0
                ? round($errTotal / ($okTotal + $errTotal) * 100, 1)
                : null,
            'groups_added_window'   => $dbAdded,
            'groups_per_hour'       => $groupsPerHour,
            'rate_override'         => $rateOverride,
            'priority_segment'      => $prioritySeg,
            'queue_pending'         => $pending,
            'queue_failed'          => $failed,
        ];
    }

    // =========================================================================
    // 2. AI providers
    // =========================================================================

    private function gatherProviders(): array
    {
        $url = rtrim(env('QUESTION_API_URL', 'http://localhost:3000'), '/') . '/health';
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(5)->get($url);
            if (!$response->successful()) {
                return ['error' => 'health endpoint returned '.$response->status()];
            }
            $health = $response->json();
            $router = $health['ai_router'] ?? [];
            $out    = [
                'provider_order'  => $router['provider_order'] ?? [],
                'last_failover'   => $router['last_failover'] ?? null,
                'last_reject'     => $router['last_reject'] ?? null,
                'providers'       => [],
            ];
            foreach ($router['providers'] ?? [] as $name => $pdata) {
                $stats = $pdata['stats_1h'] ?? [];
                $calls = (int) ($stats['calls_1h'] ?? 0);
                $ok    = (int) ($stats['ok_1h']    ?? 0);
                $fail  = (int) ($stats['fail_1h']  ?? 0);
                $out['providers'][$name] = [
                    'configured'        => $pdata['configured'] ?? false,
                    'active'            => $pdata['active']     ?? false,
                    'keys_available'    => $pdata['keys_available'] ?? 0,
                    'quarantined_count' => count($pdata['quarantined'] ?? []),
                    'calls_1h'          => $calls,
                    'ok_1h'             => $ok,
                    'fail_1h'           => $fail,
                    'fail_rate_pct'     => $calls > 0 ? round($fail / $calls * 100, 1) : null,
                    'avg_latency_ms'    => $stats['avg_latency_ms_1h'] ?? null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // 3. Coverage
    // =========================================================================

    private function gatherCoverage(): array
    {
        // Raw cell matrix: 8 sub_domains × 3 cog_types × 8 depths
        $subDomains = ['Histoire','Sport','Géographie','Art','Cuisine','Science','Cinéma','Faune'];
        $cogTypes   = ['recognition','reasoning','deceptive_trap'];
        $depths     = [3,4,5,6,7,8,9,10];
        $target     = 10; // QB_WORKER_TARGET_MATCHES default

        // DB count per (sub_domain, cognitive_type, difficulty_depth)
        $rows = DB::table('question_groups')
            ->where('id', '>', 1)
            ->select('sub_domain', 'cognitive_type', DB::raw('difficulty_depth as depth'), DB::raw('COUNT(*) as n'))
            ->groupBy('sub_domain', 'cognitive_type', 'difficulty_depth')
            ->get()
            ->keyBy(fn($r) => "{$r->sub_domain}|{$r->cognitive_type}|{$r->depth}");

        $totalCells   = count($subDomains) * count($cogTypes) * count($depths); // 192
        $emptyCells   = 0;
        $fullCells    = 0; // present >= target
        $partialCells = 0;
        $totalGroups  = 0;
        $bySubDomain  = [];
        $byDepth      = [];
        $byDepthHigh  = []; // depth 7-10

        foreach ($subDomains as $sd) {
            $bySubDomain[$sd] = ['cells' => 0, 'full' => 0, 'empty' => 0, 'groups' => 0];
            foreach ($cogTypes as $ct) {
                foreach ($depths as $d) {
                    $key     = "{$sd}|{$ct}|{$d}";
                    $present = (int) ($rows[$key]->n ?? 0);
                    $totalGroups += $present;
                    $bySubDomain[$sd]['cells']++;
                    $bySubDomain[$sd]['groups'] += $present;
                    $byDepth[$d] = ($byDepth[$d] ?? 0) + $present;

                    if ($present === 0) {
                        $emptyCells++;
                        $bySubDomain[$sd]['empty']++;
                    } elseif ($present >= $target) {
                        $fullCells++;
                        $bySubDomain[$sd]['full']++;
                    } else {
                        $partialCells++;
                    }

                    if ($d >= 7) {
                        $byDepthHigh[$d] = ($byDepthHigh[$d] ?? 0) + $present;
                    }
                }
            }
        }

        // Matches buildable (FR, key profiles)
        $repo    = new QuestionBankRepository();
        $calc    = new BankNeedsCalculator($repo);
        $buildable = [];
        try {
            $bRows = $calc->estimateMatchesBuildable('fr');
            foreach ($bRows as $b) {
                $modeKey = $b['mode'].':'.($b['division'] ?? '');
                $buildable[$modeKey] = $b['matches_buildable'];
            }
        } catch (\Throwable $e) {
            $buildable = ['error' => $e->getMessage()];
        }

        // Validated vs not
        $validated    = DB::table('question_groups')->where('validated', true)->where('id', '>', 1)->count();
        $notValidated = DB::table('question_groups')->where('validated', false)->where('id', '>', 1)->count();

        return [
            'total_cells'         => $totalCells,
            'empty_cells'         => $emptyCells,
            'partial_cells'       => $partialCells,
            'full_cells'          => $fullCells,
            'coverage_pct'        => round(($totalCells - $emptyCells) / $totalCells * 100, 1),
            'total_groups'        => $totalGroups,
            'validated'           => $validated,
            'not_validated'       => $notValidated,
            'groups_by_depth'     => $byDepth,
            'groups_depth_7_10'   => array_sum($byDepthHigh),
            'by_sub_domain'       => array_map(fn($v) => [
                'empty'  => $v['empty'],
                'groups' => $v['groups'],
            ], $bySubDomain),
            'matches_buildable'   => $buildable,
        ];
    }

    // =========================================================================
    // 4. Infrastructure
    // =========================================================================

    private function gatherInfra(): array
    {
        // RAM
        $memRaw = @file_get_contents('/proc/meminfo');
        $ramTotal = $ramFree = $ramAvail = null;
        if ($memRaw) {
            preg_match('/MemTotal:\s+(\d+)/', $memRaw, $m); $ramTotal = isset($m[1]) ? (int)$m[1] / 1024 : null;
            preg_match('/MemFree:\s+(\d+)/',  $memRaw, $m); $ramFree  = isset($m[1]) ? (int)$m[1] / 1024 : null;
            preg_match('/MemAvailable:\s+(\d+)/', $memRaw, $m); $ramAvail = isset($m[1]) ? (int)$m[1] / 1024 : null;
        }

        // Disk
        $diskFree  = disk_free_space('/') ?: null;
        $diskTotal = disk_total_space('/') ?: null;

        // Redis memory — Laravel Redis::info() returns a nested array keyed by
        // section name (e.g. ['Memory' => [...], 'Stats' => [...]]). Flatten
        // one level so field lookups are consistent regardless of driver.
        $redisInfo = [];
        try {
            $raw = Redis::info('memory');
            $flat = [];
            foreach ($raw as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $fk => $fv) { $flat[strtolower($fk)] = $fv; }
                } else {
                    $flat[strtolower($k)] = $v;
                }
            }
            $redisInfo = [
                'used_memory_mb'     => isset($flat['used_memory'])     ? round($flat['used_memory']     / 1048576, 2) : null,
                'used_memory_rss_mb' => isset($flat['used_memory_rss']) ? round($flat['used_memory_rss'] / 1048576, 2) : null,
                'fragmentation_ratio'=> $flat['mem_fragmentation_ratio'] ?? null,
            ];
        } catch (\Throwable $e) {
            $redisInfo = ['error' => $e->getMessage()];
        }

        // Redis key count
        $redisKeys = null;
        try {
            $redisKeys = Redis::dbsize();
        } catch (\Throwable $_) {}

        // Log disk usage (storage/logs)
        $logSize = null;
        try {
            $logPath = storage_path('logs');
            if (is_dir($logPath)) {
                $out = shell_exec("du -sm {$logPath} 2>/dev/null");
                if ($out) { preg_match('/^(\d+)/', $out, $m); $logSize = isset($m[1]) ? (int)$m[1] : null; }
            }
        } catch (\Throwable $_) {}

        return [
            'ram_total_mb'   => $ramTotal !== null ? (int)round($ramTotal) : null,
            'ram_avail_mb'   => $ramAvail !== null ? (int)round($ramAvail) : null,
            'ram_used_pct'   => ($ramTotal && $ramAvail) ? round((1 - $ramAvail / $ramTotal) * 100, 1) : null,
            'disk_free_gb'   => $diskFree  !== null ? round($diskFree  / 1073741824, 1) : null,
            'disk_total_gb'  => $diskTotal !== null ? round($diskTotal / 1073741824, 1) : null,
            'disk_used_pct'  => ($diskTotal && $diskFree) ? round((1 - $diskFree / $diskTotal) * 100, 1) : null,
            'redis'          => $redisInfo,
            'redis_keys'     => $redisKeys,
            'logs_size_mb'   => $logSize,
        ];
    }

    // =========================================================================
    // 5. Quality guards
    // =========================================================================

    private function gatherQuality(int $now, int $windowMin): array
    {
        $keys    = config('question_bank_profiles.worker.redis_keys');

        // Last rejects from Redis ring buffer
        $rejects = Redis::lrange($keys['last_rejects'], 0, -1);
        $codes   = [];
        $zhShorts = 0;
        $arShorts = 0;
        $truncations = 0;
        $enMissing   = 0;
        foreach ($rejects as $raw) {
            $d = json_decode($raw, true) ?? [];
            $code = $d['code'] ?? 'unknown';
            $codes[$code] = ($codes[$code] ?? 0) + 1;
            $detail = strtolower($d['detail'] ?? '');
            if ($code === 'missing_saviez_vous') {
                if (str_starts_with($detail, 'zh')) $zhShorts++;
                if (str_starts_with($detail, 'ar')) $arShorts++;
            }
            if ($code === 'generator_error' && str_contains($detail, 'unterminated')) $truncations++;
            if ($code === 'missing_translations' && str_contains($detail, 'en')) $enMissing++;
        }

        // Reject rate from gen counters (rolling window)
        $okTotal  = 0;
        $errTotal = 0;
        for ($i = 0; $i < $windowMin; $i++) {
            $min      = (int) floor(($now - $i * 60) / 60);
            $okTotal  += (int) (Redis::get(sprintf($keys['gen_counter_ok'],  $min)) ?? 0);
            $errTotal += (int) (Redis::get(sprintf($keys['gen_counter_err'], $min)) ?? 0);
        }

        // Guard-level breakdown from last_rejects
        arsort($codes);

        // Recent DB rejections: groups with validated=false created in window
        $recentFrOnly = DB::table('question_groups')
            ->where('validated', false)
            ->where('id', '>', 1)
            ->where('created_at', '>=', now()->subMinutes($windowMin))
            ->count();

        // Depth-level quality: average question_text length per depth (proxy)
        $depthLengths = DB::table('question_groups as qg')
            ->join('question_translations as qt', fn($j) => $j->on('qt.question_group_id','=','qg.id')->where('qt.language','fr'))
            ->where('qg.id', '>', 1)
            ->select('qg.difficulty_depth', DB::raw('AVG(LENGTH(qt.question_text)) as avg_len'), DB::raw('COUNT(*) as n'))
            ->groupBy('qg.difficulty_depth')
            ->orderBy('qg.difficulty_depth')
            ->get()
            ->mapWithKeys(fn($r) => [$r->difficulty_depth => ['avg_len' => (int)round($r->avg_len), 'n' => $r->n]])
            ->all();

        return [
            'reject_codes_history'   => $codes,
            'zh_short_count'         => $zhShorts,
            'ar_short_count'         => $arShorts,
            'gemini_truncations'     => $truncations,
            'en_missing_count'       => $enMissing,
            'gen_ok_window'          => $okTotal,
            'gen_err_window'         => $errTotal,
            'guard_reject_rate_pct'  => ($okTotal + $errTotal) > 0
                ? round($errTotal / ($okTotal + $errTotal) * 100, 1)
                : null,
            'fr_only_created_window' => $recentFrOnly,
            'depth_text_length'      => $depthLengths,
        ];
    }

    // =========================================================================
    // Human-readable printer
    // =========================================================================

    private function printReport(array $r): void
    {
        $sep = str_repeat('─', 65);
        $this->line('');
        $this->line("╔═══════════════════════════════════════════════════════════════╗");
        $this->line("║  BANK WORKER CHECKPOINT  —  {$r['checkpoint_at']}        ║");
        $this->line("║  Rolling window : {$r['window_minutes']} min".str_pad('', 44 - strlen((string)$r['window_minutes']))."║");
        $this->line("╚═══════════════════════════════════════════════════════════════╝");

        // ── 1. Worker ────────────────────────────────────────────────────────
        $w = $r['worker'];
        $this->line('');
        $this->line("┌─ 1. WORKER RUNTIME ".str_repeat('─', 44).'┐');
        $alive = $w['alive'] ? '<info>ALIVE</info>' : '<error>DEAD</error>';
        $this->line("  alive            : {$alive}");
        $this->line("  last_success     : {$w['last_success_human']}");
        $this->line("  groups/h (est.)  : {$w['groups_per_hour']}");
        $this->line("  added [{$r['window_minutes']}min]   : {$w['groups_added_window']} groups");
        $this->line("  api calls [{$r['window_minutes']}m] : {$w['api_calls_window']}");
        $this->line("  gen ok/err       : {$w['gen_ok_window']} ok / {$w['gen_err_window']} err".($w['err_rate_pct'] !== null ? " ({$w['err_rate_pct']}%)" : ''));
        $this->line("  rate_override    : ".($w['rate_override'] ?? 'none'));
        $this->line("  priority_seg     : ".($w['priority_segment'] ?? 'none'));
        $this->line("  queue pending    : {$w['queue_pending']}  failed: {$w['queue_failed']}");
        $this->line("└".str_repeat('─', 64).'┘');

        // ── 2. Providers ─────────────────────────────────────────────────────
        $p = $r['providers'];
        $this->line('');
        $this->line("┌─ 2. AI PROVIDERS ".str_repeat('─', 45).'┐');
        if (isset($p['error'])) {
            $this->line("  <error>health endpoint error: {$p['error']}</error>");
        } else {
            foreach ($p['providers'] ?? [] as $name => $pd) {
                if (!$pd['configured']) continue;
                $status = $pd['active'] ? 'active' : 'inactive';
                $rate   = $pd['fail_rate_pct'] !== null ? " fail={$pd['fail_rate_pct']}%" : '';
                $lat    = $pd['avg_latency_ms'] ? " lat={$pd['avg_latency_ms']}ms" : '';
                $quar   = $pd['quarantined_count'] > 0 ? " <comment>quarantined={$pd['quarantined_count']}</comment>" : '';
                $this->line("  {$name}: {$status}  calls={$pd['calls_1h']} ok={$pd['ok_1h']} fail={$pd['fail_1h']}{$rate}{$lat}{$quar}");
            }
            $lf = $p['last_failover'] ?? null;
            $this->line("  last_failover    : ".($lf ? (is_array($lf) ? json_encode($lf) : $lf) : 'none'));
        }
        $this->line("└".str_repeat('─', 64).'┘');

        // ── 3. Coverage ──────────────────────────────────────────────────────
        $c = $r['coverage'];
        $this->line('');
        $this->line("┌─ 3. COVERAGE ".str_repeat('─', 49).'┐');
        $this->line("  cells total/empty/partial/full : {$c['total_cells']} / {$c['empty_cells']} / {$c['partial_cells']} / {$c['full_cells']}");
        $this->line("  coverage (cells ≥1 group)      : {$c['coverage_pct']}%");
        $this->line("  total groups in bank           : {$c['total_groups']}");
        $this->line("  validated / fr-only            : {$c['validated']} / {$c['not_validated']}");
        $this->line("  groups depth 7-10              : {$c['groups_depth_7_10']}");
        $this->line('');
        $this->line("  By depth:");
        foreach ($c['groups_by_depth'] ?? [] as $d => $n) {
            $bar = str_repeat('█', min(40, (int)($n / 2)));
            $this->line(sprintf("    depth %2d : %3d  %s", $d, $n, $bar));
        }
        $this->line('');
        $this->line("  By sub_domain (groups / empty cells):");
        foreach ($c['by_sub_domain'] as $sd => $sv) {
            $tag = $sv['groups'] === 0 ? '<error>' : ($sv['empty'] > 16 ? '<comment>' : '<info>');
            $end = $sv['groups'] === 0 ? '</error>' : ($sv['empty'] > 16 ? '</comment>' : '</info>');
            $this->line(sprintf("    %-12s: %3d groups  %2d empty cells %s%s%s",
                $sd, $sv['groups'], $sv['empty'], $tag,
                $sv['groups'] === 0 ? '← P0 ABSENT' : ($sv['empty'] > 16 ? '← partial' : '✓'),
                $end));
        }
        $this->line('');
        $this->line("  Matches buildable (fr):");
        $mb = $c['matches_buildable'];
        if (isset($mb['error'])) {
            $this->line("    <error>{$mb['error']}</error>");
        } else {
            $shown = 0;
            foreach ($mb as $modeKey => $n) {
                if ($shown++ > 8) { $this->line("    …"); break; }
                $tag = $n === 0 ? '<error>' : ($n < 5 ? '<comment>' : '<info>');
                $end = $n === 0 ? '</error>' : ($n < 5 ? '</comment>' : '</info>');
                $this->line(sprintf("    %-28s: %s%d%s", $modeKey, $tag, $n, $end));
            }
        }
        $this->line("└".str_repeat('─', 64).'┘');

        // ── 4. Infrastructure ────────────────────────────────────────────────
        $i = $r['infrastructure'];
        $this->line('');
        $this->line("┌─ 4. INFRASTRUCTURE ".str_repeat('─', 43).'┐');
        $this->line("  RAM  : {$i['ram_avail_mb']}MB avail / {$i['ram_total_mb']}MB total  ({$i['ram_used_pct']}% used)");
        $this->line("  Disk : {$i['disk_free_gb']}GB free / {$i['disk_total_gb']}GB total  ({$i['disk_used_pct']}% used)");
        $rd = $i['redis'];
        if (isset($rd['error'])) {
            $this->line("  Redis: <error>{$rd['error']}</error>");
        } else {
            $this->line("  Redis: {$rd['used_memory_mb']}MB used  rss={$rd['used_memory_rss_mb']}MB  frag={$rd['fragmentation_ratio']}  keys={$i['redis_keys']}");
        }
        $this->line("  Logs : ".($i['logs_size_mb'] !== null ? "{$i['logs_size_mb']}MB" : 'n/a'));
        $this->line("└".str_repeat('─', 64).'┘');

        // ── 5. Quality ───────────────────────────────────────────────────────
        $q = $r['quality'];
        $this->line('');
        $this->line("┌─ 5. QUALITY ".str_repeat('─', 50).'┐');
        $this->line("  guard reject rate [{$r['window_minutes']}min] : ".
            ($q['guard_reject_rate_pct'] !== null ? "{$q['guard_reject_rate_pct']}%" : 'n/a (no calls yet)'));
        $this->line("  fr-only created  [{$r['window_minutes']}min] : {$q['fr_only_created_window']}");
        $this->line("  gemini truncations (history)  : {$q['gemini_truncations']}");
        $this->line("  zh short (history)            : {$q['zh_short_count']}");
        $this->line("  ar short (history)            : {$q['ar_short_count']}");
        $this->line("  en missing (history)          : {$q['en_missing_count']}");
        if (!empty($q['reject_codes_history'])) {
            $this->line('');
            $this->line("  Reject codes (all history, sorted by frequency):");
            foreach ($q['reject_codes_history'] as $code => $cnt) {
                $tag = $cnt >= 3 ? '<error>' : '<comment>';
                $end = $cnt >= 3 ? '</error>' : '</comment>';
                $this->line("    {$tag}{$code}: {$cnt}{$end}");
            }
        }
        if (!empty($q['depth_text_length'])) {
            $this->line('');
            $this->line("  Question text length by depth (avg chars, FR proxy for quality):");
            foreach ($q['depth_text_length'] as $d => $dv) {
                $bar = str_repeat('▪', min(30, (int)($dv['avg_len'] / 5)));
                $this->line(sprintf("    depth %2d : avg=%3d chars  n=%d  %s", $d, $dv['avg_len'], $dv['n'], $bar));
            }
        }
        $this->line("└".str_repeat('─', 64).'┘');

        $this->line('');
    }

    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60)   return "{$seconds}s";
        if ($seconds < 3600) return round($seconds / 60).'min';
        if ($seconds < 86400) return round($seconds / 3600, 1).'h';
        return round($seconds / 86400, 1).'d';
    }
}
