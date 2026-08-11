<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use App\Services\QuestionBank\Taxonomy\ValidationDominantIdeas;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * questions:kernel:rotate
 *
 * Point d'entrée V2 pour déclencher une rotation Kernel.
 *
 * Ce que cette commande fait :
 *   1. Instancie KernelBlueprintFactory + KernelRotationPlanner V2 + TaxonomyOrchestrator
 *   2. Appelle KernelPipelineOrchestrator::run()
 *   3. Affiche le statut résultant (ENGAGED | PRODUCTION_ON_HOLD)
 *
 * Ce que cette commande NE fait PAS :
 *   - Ne touche pas au pipeline BankWorker
 *   - N'appelle pas plan() ni initialize() (interface legacy DEPRECATED)
 *   - Ne génère pas de contenu (→ questions:kernel:fill-content)
 *   - Ne traduit pas
 */
class QuestionsKernelRotateCommand extends Command
{
    protected $signature = 'questions:kernel:rotate
        {--previous-domain= : Domaine du Blueprint précédent (pour avancement du DomainCycle)}
        {--dry-run          : Afficher l\'état actuel sans créer de Blueprint}';

    protected $description = 'V2 — Déclenche une rotation Kernel via KernelPipelineOrchestrator (KernelBlueprintFactory + KRP V2 + Taxonomy).';

    public function handle(
        KernelBlueprintFactory $factory,
        KernelRotationPlanner  $planner,
    ): int {
        $taxonomy = new TaxonomyOrchestrator(
            new TaxonomyBankRepository(),
            new TaxonomyGeminiClient(),
            new ValidationDominantIdeas(),
        );
        $previousDomain = $this->option('previous-domain') ?: null;
        $dryRun         = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   ROTATION V2 — KernelPipelineOrchestrator                     ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        if ($previousDomain !== null) {
            $this->line("  Domaine précédent : <fg=cyan>{$previousDomain}</>");
        } else {
            $this->line('  Domaine précédent : <fg=yellow>non fourni</> (premier appel ou reprise)');
        }
        $this->line('');

        if ($dryRun) {
            $this->line('<fg=yellow>[DRY-RUN]</> Aucune rotation effectuée.');
            $this->line('Relancer sans --dry-run pour créer un Blueprint.');
            return self::SUCCESS;
        }

        $orchestrator = new KernelPipelineOrchestrator($factory, $planner, $taxonomy);

        try {
            $result = $orchestrator->run($previousDomain);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $status    = $result['status'];
        $blueprint = $result['blueprint'];

        $this->line("  Statut       : <fg=cyan;options=bold>{$status}</>");
        $this->line("  blueprint_id : {$blueprint->blueprint_id}");

        if ($status === KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED) {
            $this->line("  depth        : {$blueprint->depth}");
            $this->line("  domain       : {$blueprint->domain}");
            $this->line("  subdomain    : " . ($blueprint->subdomain_active ?? '<fg=yellow>non rempli</>'));
            $this->line("  subject      : " . ($blueprint->subject_active ?? '<fg=yellow>non rempli</>'));
            $this->line("  idée dom.    : " . ($blueprint->dominant_idea_active ?? '<fg=yellow>non rempli</>'));
            $this->line('');
            $this->info('✅  Blueprint ENGAGED_IN_PIPELINE — pipeline Kernel peut continuer.');
        } elseif ($status === 'PRODUCTION_ON_HOLD') {
            $this->line('');
            $this->warn('⏸  PRODUCTION_ON_HOLD — aucun Depth ne requiert de production actuellement.');
            $this->line('  Blueprint classé NOT_ENGAGED_PRODUCTION_ON_HOLD.');
        }

        $this->line('');

        return $status === 'ENGAGED' ? self::SUCCESS : self::SUCCESS;
    }
}
