<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * questions:kernel:rotate
 *
 * Point d'entrée KRP v4.0 pour déclencher une rotation Kernel.
 *
 * Ce que cette commande fait :
 * Cette commande ne crée aucun Blueprint: cette responsabilité appartient à
 * CURRENT_KERNEL_RECEIVED traité par questions:kernel:process-outbox.
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

    protected $description = 'KRP — observe la rotation; la création passe exclusivement par l’outbox.';

    public function handle(): int {
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->line('╔══════════════════════════════════════════════════════════════════╗');
        $this->line('║   ROTATION KRP v4.0 — Factory → Blueprint → KRP               ║');
        $this->line('╚══════════════════════════════════════════════════════════════════╝');
        $this->line('');

        if ($dryRun) {
            $this->line('<fg=yellow>[DRY-RUN]</> Aucune rotation effectuée.');
            $this->line('La création est réservée à questions:kernel:process-outbox.');
            return self::SUCCESS;
        }

        $this->error(
            'Création directe refusée: émettre CURRENT_KERNEL_RECEIVED puis exécuter '
            . 'questions:kernel:process-outbox.'
        );

        return self::FAILURE;
    }
}
