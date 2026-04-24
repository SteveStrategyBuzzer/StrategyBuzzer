<?php

namespace App\Console\Commands;

use App\Models\DuoMatch;
use App\Models\MatchPerformance;
use App\Models\PlayerDuoStat;
use App\Models\PlayerStatistic;
use App\Models\ProfileStat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileStats extends Command
{
    protected $signature = 'stats:reconcile
        {--apply : Persist changes (default is dry-run)}
        {--user-id= : Reconcile a single user only}
        {--target=all : Which table(s) to reconcile (all|duo|profile|player)}';

    protected $description = 'Recompute player stats from raw sources (duo_matches, match_performances, player_statistics scope=match). Idempotent.';

    private array $totals = [
        'duo_changed' => 0,
        'duo_created' => 0,
        'profile_changed' => 0,
        'profile_created' => 0,
        'player_global_changed' => 0,
        'player_global_created' => 0,
    ];

    public function handle(): int
    {
        // Reset totals (command instance may be reused across Artisan::call invocations)
        foreach ($this->totals as $k => $_) {
            $this->totals[$k] = 0;
        }

        $apply = (bool) $this->option('apply');
        $target = $this->option('target');
        $userIdFilter = $this->option('user-id');

        if (!in_array($target, ['all', 'duo', 'profile', 'player'], true)) {
            $this->error("Invalid --target: {$target}. Use all|duo|profile|player.");
            return self::FAILURE;
        }

        $mode = $apply ? '<fg=red>APPLY</>' : '<fg=yellow>DRY-RUN</>';
        $this->line("=== stats:reconcile [{$mode}] target={$target} " . ($userIdFilter ? "user={$userIdFilter}" : 'all-users') . ' ===');

        $users = User::query()
            ->when($userIdFilter, fn($q) => $q->where('id', (int) $userIdFilter))
            ->orderBy('id')
            ->get(['id', 'email']);

        if ($users->isEmpty()) {
            $this->warn('No users to reconcile.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($users->count());
        $bar->start();

        foreach ($users as $user) {
            try {
                if ($target === 'all' || $target === 'duo') {
                    $this->reconcileDuo($user, $apply);
                }
                if ($target === 'all' || $target === 'profile') {
                    $this->reconcileProfile($user, $apply);
                }
                if ($target === 'all' || $target === 'player') {
                    $this->reconcilePlayerGlobal($user, $apply);
                }
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("User #{$user->id} failed: {$e->getMessage()}");
                Log::error('stats:reconcile error', ['user_id' => $user->id, 'exception' => $e]);
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Metric', 'Count'],
            collect($this->totals)->map(fn($v, $k) => [$k, $v])->values()->toArray()
        );

        if (!$apply) {
            $this->warn('DRY-RUN — no changes persisted. Re-run with --apply to commit.');
        } else {
            $this->info('Changes persisted.');
        }
        return self::SUCCESS;
    }

    /**
     * Reconcile PlayerDuoStat from duo_matches table.
     * Source of truth: matches with status IN ('completed','finished') AND winner_id NOT NULL.
     * NOTE: streaks (current/best_win/best_lose) are NOT recomputed (no reliable temporal source).
     */
    private function reconcileDuo(User $user, bool $apply): void
    {
        $finishedClause = function ($q) use ($user) {
            $q->whereIn('status', ['completed', 'finished'])
              ->whereNotNull('winner_id')
              ->where(function ($qq) use ($user) {
                  $qq->where('player1_id', $user->id)->orWhere('player2_id', $user->id);
              });
        };

        $total = DuoMatch::where($finishedClause)->count();
        $victories = DuoMatch::where($finishedClause)->where('winner_id', $user->id)->count();
        $defeats = $total - $victories;
        $winRate = $total > 0 ? round(($victories / $total) * 100, 2) : 0.00;
        $level = $victories;

        $current = PlayerDuoStat::where('user_id', $user->id)->first();

        $expected = [
            'total_matches' => $total,
            'victories' => $victories,
            'defeats' => $defeats,
            'win_rate' => $winRate,
            'level' => $level,
        ];

        if (!$current) {
            if ($total === 0) {
                return; // Nothing to create
            }
            $this->totals['duo_created']++;
            $this->line("  [DUO] user#{$user->id} CREATE " . json_encode($expected));
            if ($apply) {
                PlayerDuoStat::create(array_merge(['user_id' => $user->id], $expected));
            }
            return;
        }

        $diff = $this->diff($current->only(array_keys($expected)), $expected);
        if (empty($diff)) {
            return;
        }
        $this->totals['duo_changed']++;
        $this->line("  [DUO] user#{$user->id} UPDATE " . json_encode($diff));
        if ($apply) {
            $current->fill($expected)->save();
        }
    }

    /**
     * Reconcile ProfileStat from match_performances table.
     * Recomputes {mode}_matchs_joues, _victoires, _defaites, _ratio_victoire,
     * _performance_moyenne, _efficacite_joueur for solo/duo/league.
     * Solo also recomputes _matchs_3_manches and _victoires_3_manches.
     * Efficacite_joueur formula = (perf_moyenne + ratio_victoire) / 2 (current) — Étape 4 will change this.
     */
    private function reconcileProfile(User $user, bool $apply): void
    {
        $expected = ProfileStat::where('user_id', $user->id)->first()?->only([
            'solo_matchs_joues','solo_victoires','solo_defaites','solo_ratio_victoire',
            'solo_matchs_3_manches','solo_victoires_3_manches','solo_performance_moyenne','solo_efficacite_joueur',
            'duo_matchs_joues','duo_victoires','duo_defaites','duo_ratio_victoire',
            'duo_performance_moyenne','duo_efficacite_joueur',
            'league_matchs_joues','league_victoires','league_defaites','league_ratio_victoire',
            'league_performance_moyenne','league_efficacite_joueur',
        ]) ?? [];

        $hasAny = false;
        $newValues = [];

        foreach (['solo', 'duo', 'league'] as $mode) {
            $rows = MatchPerformance::where('user_id', $user->id)->where('game_mode', $mode);
            $matchs = (clone $rows)->count();
            if ($matchs > 0) { $hasAny = true; }
            $victoires = (clone $rows)->where('is_victory', true)->count();
            $defaites = $matchs - $victoires;
            $ratio = $matchs > 0 ? round(($victoires / $matchs) * 100, 2) : 0.00;
            $perfMoy = MatchPerformance::getAverageLast10($user->id, $mode);
            $efficacite = round(((float) $perfMoy + $ratio) / 2, 2);

            $newValues["{$mode}_matchs_joues"] = $matchs;
            $newValues["{$mode}_victoires"] = $victoires;
            $newValues["{$mode}_defaites"] = $defaites;
            $newValues["{$mode}_ratio_victoire"] = $ratio;
            $newValues["{$mode}_performance_moyenne"] = (float) $perfMoy;
            $newValues["{$mode}_efficacite_joueur"] = $efficacite;

            if ($mode === 'solo') {
                $solo3 = MatchPerformance::where('user_id', $user->id)
                    ->where('game_mode', 'solo')->where('rounds_played', 3);
                $newValues['solo_matchs_3_manches'] = (clone $solo3)->count();
                $newValues['solo_victoires_3_manches'] = (clone $solo3)->where('is_victory', true)->count();
            }
        }

        if (!$hasAny && empty($expected)) {
            return; // No match history & no row → nothing to do
        }

        if (empty($expected)) {
            $this->totals['profile_created']++;
            $this->line("  [PROFILE] user#{$user->id} CREATE " . json_encode(array_filter($newValues, fn($v) => $v != 0)));
            if ($apply) {
                ProfileStat::create(array_merge(['user_id' => $user->id], $newValues));
            }
            return;
        }

        // Cast existing decimals (loaded as strings) to floats for diff comparison
        $expectedNorm = $this->normalizeNumeric($expected);
        $newNorm = $this->normalizeNumeric($newValues);
        $diff = $this->diff($expectedNorm, $newNorm);
        if (empty($diff)) {
            return;
        }
        $this->totals['profile_changed']++;
        $this->line("  [PROFILE] user#{$user->id} UPDATE " . json_encode($diff));
        if ($apply) {
            ProfileStat::where('user_id', $user->id)->update($newValues);
        }
    }

    /**
     * Reconcile PlayerStatistic scope=global from PlayerStatistic scope=match aggregates.
     * For each (user, game_mode) with ≥1 match-scope row, sum counters and average %-metrics.
     */
    private function reconcilePlayerGlobal(User $user, bool $apply): void
    {
        $modes = PlayerStatistic::where('user_id', $user->id)
            ->where('scope', 'match')
            ->distinct()
            ->pluck('game_mode');

        foreach ($modes as $gameMode) {
            $matches = PlayerStatistic::where('user_id', $user->id)
                ->where('game_mode', $gameMode)
                ->where('scope', 'match')
                ->get();

            if ($matches->isEmpty()) {
                continue;
            }

            $expected = [
                'total_questions' => (int) $matches->sum('total_questions'),
                'questions_buzzed' => (int) $matches->sum('questions_buzzed'),
                'correct_answers' => (int) $matches->sum('correct_answers'),
                'wrong_answers' => (int) $matches->sum('wrong_answers'),
                'points_earned' => (int) $matches->sum('points_earned'),
                'points_possible' => (int) $matches->sum('points_possible'),
                'efficacite_partie' => round((float) $matches->avg('efficacite_partie'), 2),
                'taux_participation' => $this->safeRatio((int) $matches->sum('questions_buzzed'), (int) $matches->sum('total_questions')) * 100,
                'taux_precision' => $this->safeRatio((int) $matches->sum('correct_answers'), (int) $matches->sum('questions_buzzed')) * 100,
                'ratio_performance' => $this->safeRatio((int) $matches->sum('points_earned'), (int) $matches->sum('points_possible')) * 100,
            ];
            $expected['taux_participation'] = round($expected['taux_participation'], 2);
            $expected['taux_precision'] = round($expected['taux_precision'], 2);
            $expected['ratio_performance'] = round($expected['ratio_performance'], 2);
            // Use last-10-match avg for efficacite_joueur (matches what Étape 2 facade will expose)
            $last10 = PlayerStatistic::where('user_id', $user->id)
                ->where('game_mode', $gameMode)
                ->where('scope', 'match')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
            $expected['efficacite_joueur'] = round((float) $last10->avg('efficacite_partie'), 2);

            $current = PlayerStatistic::where('user_id', $user->id)
                ->where('game_mode', $gameMode)
                ->where('scope', 'global')
                ->first();

            if (!$current) {
                $this->totals['player_global_created']++;
                $this->line("  [PLAYER_GLOBAL] user#{$user->id} mode={$gameMode} CREATE " . json_encode($expected));
                if ($apply) {
                    PlayerStatistic::create(array_merge([
                        'user_id' => $user->id,
                        'game_mode' => $gameMode,
                        'scope' => 'global',
                    ], $expected));
                }
                continue;
            }

            $currentNorm = $this->normalizeNumeric($current->only(array_keys($expected)));
            $expectedNorm = $this->normalizeNumeric($expected);
            $diff = $this->diff($currentNorm, $expectedNorm);
            if (empty($diff)) {
                continue;
            }
            $this->totals['player_global_changed']++;
            $this->line("  [PLAYER_GLOBAL] user#{$user->id} mode={$gameMode} UPDATE " . json_encode($diff));
            if ($apply) {
                $current->fill($expected)->save();
            }
        }
    }

    private function diff(array $a, array $b): array
    {
        $out = [];
        foreach ($b as $k => $v) {
            $av = $a[$k] ?? null;
            if (is_float($v) || is_float($av)) {
                if (abs((float) $av - (float) $v) > 0.005) {
                    $out[$k] = ['from' => $av, 'to' => $v];
                }
            } else {
                if ($av != $v) {
                    $out[$k] = ['from' => $av, 'to' => $v];
                }
            }
        }
        return $out;
    }

    private function normalizeNumeric(array $row): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (is_numeric($v) && (str_contains((string) $v, '.') || str_contains($k, 'ratio') || str_contains($k, 'taux') || str_contains($k, 'efficacite') || str_contains($k, 'performance'))) {
                $out[$k] = (float) $v;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private function safeRatio(int $num, int $denom): float
    {
        return $denom > 0 ? $num / $denom : 0.0;
    }
}
