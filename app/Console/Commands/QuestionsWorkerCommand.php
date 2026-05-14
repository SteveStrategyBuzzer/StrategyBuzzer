<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankAIGenerator;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use App\Services\QuestionBank\Worker\BankWorker;
use App\Services\QuestionBank\Worker\QualityGuards;
use App\Services\QuestionBank\Worker\WorkerRateLimiter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class QuestionsWorkerCommand extends Command
{
    protected $signature = 'questions:worker
                            {--once : Run a single cycle then exit (useful for smoke tests)}
                            {--dry : Compute deficits but never call the LLM}
                            {--cycles=0 : Stop after this many cycles (0 = infinite)}';

    protected $description = 'Continuous bank refill worker (#82). Computes per-segment deficits, generates the missing tuples via Gemini, applies quality guards, and inserts.';

    public function handle(): int
    {
        // ── Resilience: clear stale semaphore left by a SIGKILL'd previous instance ──
        // Replit checkpoints send SIGTERM then SIGKILL. If PHP was blocked inside a
        // 15-second Gemini HTTP call when SIGKILL arrived, the handler never ran and the
        // Redis semaphore was never released. Since this workflow is a singleton (Replit
        // runs exactly one instance), it is always safe to clear the lock on startup.
        $semKey = config('question_bank_profiles.worker.redis_keys.semaphore', 'qb:worker:lock');
        try {
            $stale = Redis::get($semKey);
            if ($stale) {
                Redis::del($semKey);
                $this->warn('[questions:worker] stale semaphore cleared at startup (previous SIGKILL?) — token: ' . substr($stale, 0, 32));
            }
        } catch (\Throwable $e) {
            $this->warn('[questions:worker] could not check semaphore: ' . $e->getMessage());
        }
        // ─────────────────────────────────────────────────────────────────────────────

        $repo = new QuestionBankRepository();
        $needs = new BankNeedsCalculator($repo);
        $gen = new BankAIGenerator();
        $guards = new QualityGuards($repo);
        $rate = new WorkerRateLimiter(
            (int) config('question_bank_profiles.worker.rate_per_minute', 6),
            (string) config('question_bank_profiles.worker.redis_keys.rate_bucket', 'qb:worker:rate:%s')
        );

        $worker = new BankWorker($needs, $gen, $guards, $repo, $rate);

        $maxCycles = (int) $this->option('cycles');
        if ($this->option('once')) {
            $maxCycles = 1;
        }
        $maxCycles = $maxCycles > 0 ? $maxCycles : null;

        $this->info('[questions:worker] starting'.($this->option('dry') ? ' (DRY RUN)' : '').' max_cycles='.($maxCycles ?? 'infinite'));

        $ran = $worker->run(
            maxCycles: $maxCycles,
            dryRun: (bool) $this->option('dry'),
            onCycle: function ($info) {
                $codePart = isset($info['code']) ? ' code='.$info['code'] : '';
                $segPart  = isset($info['segment']) ? ' '.json_encode([
                    'mode'  => $info['segment']['mode'] ?? null,
                    'sub'   => $info['segment']['sub_domain'] ?? null,
                    'cog'   => $info['segment']['cognitive_type'] ?? null,
                    'lang'  => $info['segment']['language'] ?? null,
                ]) : '';
                $this->line('[cycle '.$info['cycles'].'] '.$info['action'].$codePart.$segPart);
            }
        );

        $this->info("[questions:worker] stopped after {$ran} cycle(s)");
        return self::SUCCESS;
    }
}
