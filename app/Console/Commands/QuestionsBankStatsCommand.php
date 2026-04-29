<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Console\Command;

class QuestionsBankStatsCommand extends Command
{
    protected $signature = 'questions:bank:stats
                            {--language= : Only count groups that have a translation in this language}
                            {--domain= : Only count groups in this domain}';

    protected $description = 'Show depth report of the persistent question bank, grouped by tuple (level/boss, domain, sub-domain, cognitive_type, depth, question_type).';

    public function handle(QuestionBankRepository $repo): int
    {
        $rows = $repo->depthReport(
            $this->option('language') ?: null,
            $this->option('domain') ?: null
        );

        if (empty($rows)) {
            $this->warn('Bank is empty for the requested filters.');
            return self::SUCCESS;
        }

        $this->table(
            ['Level', 'Boss', 'Depth', 'Domain', 'Sub-domain', 'Cog', 'QType', 'Total', 'Validated'],
            array_map(function ($r) {
                return [
                    $r['difficulty_level'] ?? '-',
                    $r['boss_level'] ?? '-',
                    $r['difficulty_depth'],
                    $r['domain'],
                    $r['sub_domain'],
                    $r['cognitive_type'],
                    $r['question_type'],
                    $r['group_count'],
                    $r['validated_count'],
                ];
            }, $rows)
        );

        $this->info(sprintf('%d aggregation rows shown.', count($rows)));
        return self::SUCCESS;
    }
}
