<?php

namespace App\Console\Commands;

use App\Services\QuestionBank\MatchQuestionPlanner;
use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Console\Command;

class QuestionsPlanDryrunCommand extends Command
{
    protected $signature = 'questions:plan:dryrun
                            {--mode=duo : solo|boss|duo|mj_auto|ligue}
                            {--division=intermediaire : Division name for duo/mj_auto/ligue, or numeric level for solo/boss}
                            {--total=30 : Total questions in the match}
                            {--rounds=3 : Number of rounds}
                            {--language=fr : Player language code}
                            {--domain=general : Domain name (general = orchestrator over 8 sub-domains)}
                            {--simulate : Actually try to pick from the bank to show realised composition}';

    protected $description = 'Show what a match plan looks like for the given mode/division: profile resolution, expected quotas (global + per round), sub-domain quotas, and gap report against the actual bank.';

    public function handle(): int
    {
        $mode = $this->option('mode');
        $division = $this->option('division');
        $total = (int) $this->option('total');
        $rounds = (int) $this->option('rounds');
        $language = $this->option('language');
        $domain = $this->option('domain');

        $planner = new MatchQuestionPlanner();
        $repo = new QuestionBankRepository();

        try {
            $proj = $planner->projectPlan($mode, $division, $total, $rounds, $domain);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->line("<info>Mode</info>:        {$mode}");
        $this->line("<info>Division</info>:    {$division}");
        $this->line("<info>Total</info>:       {$total}");
        $this->line("<info>Rounds</info>:      {$rounds}");
        $this->line("<info>Language</info>:    {$language}");
        $this->line("<info>Domain</info>:      {$domain}");

        $resolved = $proj['resolved_target'];
        $this->newLine();
        $this->line('<comment>Resolved profile:</comment>');
        $this->line('  type        : ' . $resolved['type']);
        if (($resolved['type'] ?? '') === 'boss') {
            $this->line('  boss_level  : ' . $resolved['level']);
        } else {
            $this->line('  level_range : ' . implode('-', $resolved['levels']));
        }
        $this->line('  depth_range : ' . implode('-', $proj['depth_range']));
        $this->line('  cognitive   : ' . json_encode($proj['cognitive_mix'], JSON_UNESCAPED_UNICODE));

        $this->newLine();
        $this->line('<comment>Global composition (largest-remainder):</comment>');
        foreach ($proj['global_composition'] as $cog => $n) {
            $this->line("  {$cog}: {$n}");
        }

        $this->newLine();
        $this->line('<comment>Per-round composition:</comment>');
        $headers = array_merge(['Round'], array_keys($proj['global_composition']));
        $body = [];
        foreach ($proj['per_round_composition'] as $round => $byType) {
            $row = ['#' . $round];
            foreach (array_keys($proj['global_composition']) as $cog) {
                $row[] = $byType[$cog] ?? 0;
            }
            $body[] = $row;
        }
        $this->table($headers, $body);

        $this->newLine();
        $this->line('<comment>Sub-domain quotas:</comment>');
        foreach ($proj['sub_domain_quotas'] as $sd => $n) {
            $this->line("  {$sd}: {$n}");
        }

        $this->newLine();
        $this->line('<comment>Bank availability per (sub-domain × cognitive × depth) for language ' . $language . ':</comment>');
        $availability = [];
        foreach ($proj['sub_domain_quotas'] as $sd => $needTotal) {
            foreach ($proj['global_composition'] as $cog => $needCog) {
                $count = $repo->countMatching([
                    'mode_target' => $resolved,
                    'depth_range' => $proj['depth_range'],
                    'cognitive_type' => $cog,
                    'sub_domain' => $sd,
                    'language' => $language,
                    'require_validated' => true,
                ]);
                $availability[] = [$sd, $cog, $count];
            }
        }
        $this->table(['Sub-domain', 'Cognitive', 'Available'], $availability);

        if ($this->option('simulate')) {
            $this->newLine();
            $this->line('<comment>Simulation: actually building plan against bank...</comment>');
            $plan = $planner->buildPlan($mode, $division, $total, $rounds, $language, $domain);
            $this->line('  served       : ' . count($plan['questions']) . ' / ' . $total);
            $realComposition = [];
            foreach ($plan['questions'] as $q) {
                $realComposition[$q['cognitive_type']] = ($realComposition[$q['cognitive_type']] ?? 0) + 1;
            }
            $this->line('  realised cog : ' . json_encode($realComposition, JSON_UNESCAPED_UNICODE));
            $this->line('  sub-domains  : ' . json_encode($plan['sub_domain_distribution'], JSON_UNESCAPED_UNICODE));
            if (!empty($plan['issues'])) {
                $this->warn('  issues       : ' . implode(', ', $plan['issues']));
            }
        }

        return self::SUCCESS;
    }
}
