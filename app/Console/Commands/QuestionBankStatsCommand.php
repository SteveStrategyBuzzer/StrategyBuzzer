<?php

namespace App\Console\Commands;

use App\Services\QuestionBankRepository;
use Illuminate\Console\Command;

class QuestionBankStatsCommand extends Command
{
    protected $signature = 'questions:bank:stats
        {--language= : Filtre par langue}
        {--domain= : Filtre par domaine}';

    protected $description = 'Affiche la profondeur de la banque par tuple (language, level/boss, domain, sub_domain, cognitive_type, depth, question_type)';

    public function handle(QuestionBankRepository $repo): int
    {
        $rows = $repo->depthStats();
        $language = $this->option('language');
        $domain = $this->option('domain');

        if ($language) {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['language'] ?? null) === $language));
        }
        if ($domain) {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['domain'] ?? null) === $domain));
        }

        if (empty($rows)) {
            $this->warn('Banque vide pour le filtre demandé.');
            return self::SUCCESS;
        }

        $this->table(
            ['lang', 'solo', 'boss', 'domain', 'sub', 'cog', 'depth', 'qtype', 'count'],
            array_map(function ($r) {
                return [
                    $r['language'] ?? '',
                    $r['difficulty_level'] ?? '-',
                    $r['boss_level'] ?? '-',
                    $r['domain'] ?? '',
                    $r['sub_domain'] ?? '',
                    $r['cognitive_type'] ?? '',
                    $r['difficulty_depth'] ?? '',
                    $r['question_type'] ?? '',
                    $r['cnt'] ?? 0,
                ];
            }, $rows)
        );

        $total = array_sum(array_column($rows, 'cnt'));
        $this->info("Total lignes : " . count($rows) . "   Total questions : {$total}");

        return self::SUCCESS;
    }
}
