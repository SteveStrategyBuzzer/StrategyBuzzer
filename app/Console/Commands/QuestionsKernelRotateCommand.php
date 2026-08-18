<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use App\Services\QuestionBank\Rotation\KernelPipelineOrchestrator;
use App\Services\QuestionBank\Rotation\KernelRotationPlanner;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * questions:kernel:rotate
 *
 * Point d'entrée du module 02 uniquement :
 * KernelBlueprintFactory → KernelRotationPlanner → depth + domain.
 */
class QuestionsKernelRotateCommand extends Command
{
    protected $signature = 'questions:kernel:rotate
        {--dry-run : Afficher sans créer de Blueprint}';

    protected $description = 'Module 02 — crée un Blueprint et lui assigne le prochain depth + domain.';

    public function handle(
        KernelBlueprintFactory $factory,
        KernelRotationPlanner $planner,
    ): int {
        if ((bool) $this->option('dry-run')) {
            $this->line('[DRY-RUN] Aucune rotation effectuée.');
            return self::SUCCESS;
        }

        $orchestrator = new KernelPipelineOrchestrator($factory, $planner);

        try {
            $result = $orchestrator->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $status = $result['status'];
        $blueprint = $result['blueprint'];

        $this->line("Statut : {$status}");

        if ($status === KernelPipelineOrchestrator::STATUS_ROTATION_ASSIGNED && $blueprint !== null) {
            $this->line("blueprint_id : {$blueprint->blueprint_id}");
            $this->line("depth        : {$blueprint->depth}");
            $this->line("domain       : {$blueprint->domain}");
            $this->info('Module 02 terminé pour ce Blueprint — prêt pour Taxonomy.');
        } elseif ($status === KernelPipelineOrchestrator::STATUS_PRODUCTION_ON_HOLD) {
            $this->warn('PRODUCTION_ON_HOLD — toutes les cibles globales sont satisfaites.');
        } elseif ($status === KernelPipelineOrchestrator::STATUS_AWAITING_DEPTH_EXHAUSTED) {
            $this->warn('AWAITING_DEPTH_EXHAUSTED — attente du signal Taxonomy pour fermer le tour courant.');
        } elseif ($status === KernelPipelineOrchestrator::STATUS_BLOCKED) {
            $this->error('BLOCKED — persistance KRP en incident terminal.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
