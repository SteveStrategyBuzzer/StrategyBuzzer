<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Rotation\KernelRotationStateRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\TaxonomyPipelineBridge;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * questions:kernel:rotate
 *
 * Point d'entrée KRP v4.0 pour déclencher une rotation Kernel.
 *
 * Ce que cette commande fait :
 *   1. Instancie KernelBlueprintFactory + KernelRotationPlanner + état KRP
 *   2. Appelle KernelPipelineOrchestrator::run()
 *   3. Affiche le statut résultant (ROTATION_ASSIGNED | PRODUCTION_ON_HOLD)
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne touche pas au pipeline BankWorker
 *   - N'appelle pas initialize() ni plan() (interface legacy SUPPRIMÉE en V3)
 *   - Ne génère pas de contenu (→ questions:kernel:fill-content)
 *   - Ne traduit pas
 */
class QuestionsKernelRotateCommand extends Command
{
    protected $signature = 'questions:kernel:rotate
        {--dry-run : Afficher l\'état actuel sans créer de Blueprint}';

    protected $description = 'KRP v4.0 — Crée le prochain Blueprint et lui attribue depth + domain.';

    public function handle(
        KernelBlueprintFactory $factory,
        KernelRotationPlanner  $planner,
        KernelRotationStateRepository $stateRepository,
        TaxonomyOrchestrator $taxonomy,
        TaxonomyBankRepository $taxonomyRepository,
        KernelCodeEngine $kernelCodeEngine,
    ): int {
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   ROTATION KRP v4.0 — Factory → Blueprint → KRP               ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        if ($dryRun) {
            $this->line('<fg=yellow>[DRY-RUN]</> Aucune rotation effectuée.');
            $this->line('Relancer sans --dry-run pour créer un Blueprint.');
            return self::SUCCESS;
        }

        $orchestrator = new KernelPipelineOrchestrator(
            $factory,
            $planner,
            $stateRepository,
            new TaxonomyPipelineBridge($taxonomy, $taxonomyRepository, $planner, $kernelCodeEngine),
        );

        try {
            $result = $orchestrator->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $status    = $result['status'];
        $blueprint = $result['blueprint'];

        $this->line("  Statut : <fg=cyan;options=bold>{$status}</>");

        if ($status === KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED && $blueprint !== null) {
            $this->line("  blueprint_id : {$blueprint->blueprint_id}");
            $this->line("  depth        : {$blueprint->depth}");
            $this->line("  domain       : {$blueprint->domain}");
            $this->line("  subdomain    : " . ($blueprint->subdomain_active ?? '<fg=yellow>non rempli</>'));
            $this->line("  subject      : " . ($blueprint->subject_active ?? '<fg=yellow>non rempli</>'));
            $this->line("  idée dom.    : " . ($blueprint->dominant_idea_active ?? '<fg=yellow>non rempli</>'));
            $this->line('');
            $this->info('✅  Blueprint ENGAGED_IN_PIPELINE — pipeline Kernel peut continuer.');
        } elseif ($status === KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD) {
            $this->line('');
            $this->warn('⏸  PRODUCTION_ON_HOLD — aucun Depth ne requiert de production actuellement.');
            $this->line('  Aucun Blueprint engagé.');
        }

        $this->line('');

        return self::SUCCESS;
    }
}
