<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class QuestionsBankReportCommand extends Command
{
    protected $signature = 'questions:bank:report {--language= : Restrict report to a single language}';
    protected $description = 'Daily structured-log report of the question bank state (#82).';

    public function handle(): int
    {
        $repo = new QuestionBankRepository();
        $needs = new BankNeedsCalculator($repo);
        $language = $this->option('language');

        // 1. At-risk segments (deficit > 0).
        $deficits = $needs->computeDeficits(limit: 25);

        // 2. Most consumed groups (top 10 by usage_count).
        $mostConsumed = DB::table('question_groups')
            ->select('id', 'domain', 'sub_domain', 'cognitive_type', 'usage_count')
            ->orderByDesc('usage_count')
            ->limit(10)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->toArray();

        // 3. Fill rate per language (validated count).
        $fillByLang = DB::table('question_translations')
            ->select('language', DB::raw('COUNT(*) as translation_count'))
            ->groupBy('language')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->language => (int) $r->translation_count])
            ->toArray();

        // 4. Buildable matches per profile (compact).
        $buildable = $needs->estimateMatchesBuildable($language);

        // Output to stdout AND structured log.
        $report = [
            'reported_at' => now()->toIso8601String(),
            'at_risk_segments_count' => count($deficits),
            'top_at_risk' => array_slice($deficits, 0, 5),
            'most_consumed' => $mostConsumed,
            'fill_by_language' => $fillByLang,
            'buildable_summary' => array_map(fn ($r) => [
                'mode' => $r['mode'],
                'division' => $r['division'],
                'language' => $r['language'],
                'matches_buildable' => $r['matches_buildable'],
            ], array_slice($buildable, 0, 20)),
            'last_worker_success' => $this->jsonRedis(config('question_bank_profiles.worker.redis_keys.last_success')),
        ];

        Log::channel(config('logging.default'))->info('[questions:bank:report]', $report);

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return self::SUCCESS;
    }

    private function jsonRedis(string $key): ?array
    {
        $raw = Redis::get($key);
        if (!$raw) return null;
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
