<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankAIGenerator;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use App\Services\QuestionBank\Worker\BankWorker;
use App\Services\QuestionBank\Worker\QualityGuards;
use App\Services\QuestionBank\Worker\WorkerRateLimiter;
use Illuminate\Console\Command;

class QuestionsWorkerCommand extends Command
{
    protected $signature = 'questions:worker
                            {--once : Run a single cycle then exit (useful for smoke tests)}
                            {--dry : Compute deficits but never call the LLM}
                            {--cycles=0 : Stop after this many cycles (0 = infinite)}';

    protected $description = 'Continuous bank refill worker (#82). Computes per-segment deficits, generates the missing tuples via Gemini, applies quality guards, and inserts.';

    public function handle(): int
    {
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
                $this->line('[cycle '.$info['cycles'].'] '.$info['action'].(isset($info['segment']) ? ' '.json_encode([
                    'mode' => $info['segment']['mode'] ?? null,
                    'sub_domain' => $info['segment']['sub_domain'] ?? null,
                    'cog' => $info['segment']['cognitive_type'] ?? null,
                    'lang' => $info['segment']['language'] ?? null,
                ]) : ''));
            }
        );

        $this->info("[questions:worker] stopped after {$ran} cycle(s)");
        return self::SUCCESS;
    }
}
