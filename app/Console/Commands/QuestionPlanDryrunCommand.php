<?php

namespace App\Console\Commands;

use App\Services\MatchQuestionPlanner;
use Illuminate\Console\Command;

class QuestionPlanDryrunCommand extends Command
{
    protected $signature = 'questions:plan:dryrun
        {--mode=solo : solo|duo|mj|ligue|boss}
        {--division= : Niveau Solo (entier) ou nom de division (string)}
        {--total=30 : Nombre total de questions}
        {--rounds=3 : Nombre de manches}
        {--language=fr : Langue cible}
        {--domain=general : Domaine cible}';

    protected $description = 'Affiche un plan de match construit par MatchQuestionPlanner sans le persister utilement (audit uniquement).';

    public function handle(MatchQuestionPlanner $planner): int
    {
        $mode = (string) $this->option('mode');
        $division = $this->option('division');
        $total = (int) $this->option('total');
        $rounds = (int) $this->option('rounds');
        $language = (string) $this->option('language');
        $domain = (string) $this->option('domain');

        if ($division === null) {
            $this->error('Option --division requise (ex: --division=intermediaire ou --division=35).');
            return self::FAILURE;
        }

        $levelOrDivision = is_numeric($division) ? (int) $division : (string) $division;

        $this->info("Mode : {$mode}");
        $this->info("Division/level : {$division}");
        $this->info("Total questions : {$total} | Manches : {$rounds} | Langue : {$language} | Domaine : {$domain}");
        $this->line('');

        try {
            $plan = $planner->buildPlan($mode, $levelOrDivision, $total, $rounds, $language, [
                'domain' => $domain,
            ]);
        } catch (\Throwable $e) {
            $this->error("Échec construction plan : " . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('=== Quotas globaux (cibles) ===');
        $this->table(
            ['cognitive_type', 'target', 'actual'],
            array_map(
                fn ($code) => [
                    $code,
                    $plan['global_quotas'][$code] ?? 0,
                    $plan['composition_actual'][$code] ?? 0,
                ],
                ['recognition', 'reasoning', 'deceptive_trap']
            )
        );

        $this->line('');
        $this->line('=== Quotas par manche (cibles vs actual) ===');
        $headers = ['round', 'recog target', 'recog actual', 'reas target', 'reas actual', 'trap target', 'trap actual'];
        $rowsData = [];
        for ($r = 1; $r <= $rounds; $r++) {
            $tg = $plan['per_round_quotas'][$r] ?? [];
            $ac = $plan['per_round_actual'][$r] ?? [];
            $rowsData[] = [
                $r,
                $tg['recognition'] ?? 0, $ac['recognition'] ?? 0,
                $tg['reasoning'] ?? 0,   $ac['reasoning'] ?? 0,
                $tg['deceptive_trap'] ?? 0, $ac['deceptive_trap'] ?? 0,
            ];
        }
        $this->table($headers, $rowsData);

        if (!empty($plan['subdomain_quotas'])) {
            $this->line('');
            $this->line('=== Sous-domaines (général) ===');
            $rows = [];
            foreach ($plan['subdomain_quotas'] as $sub => $target) {
                $rows[] = [
                    $sub,
                    $target,
                    ($plan['subdomain_actual'][$sub] ?? 0),
                ];
            }
            $this->table(['sub_domain', 'target', 'actual'], $rows);
        }

        $this->line('');
        $this->line('=== Disponibilité dans la banque (résumé) ===');
        $shortages = $plan['shortages'] ?? [];
        if (empty($shortages)) {
            $this->info('Aucun manque — la banque a couvert tous les slots.');
        } else {
            $this->warn(count($shortages) . ' slots non couverts par la banque :');
            foreach ($shortages as $s) {
                $this->line(sprintf(
                    '  manche %d  cog=%-15s  sub=%-12s  depth=%s',
                    $s['round'],
                    $s['cognitive_type'],
                    $s['sub_domain'] ?? '*',
                    json_encode($s['depth_range'])
                ));
            }
        }

        $this->line('');
        $this->line('=== Plan ID (audit) : ' . $plan['plan_id']);

        return self::SUCCESS;
    }
}
