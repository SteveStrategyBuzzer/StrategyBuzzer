<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\Listeners\ApplyCurrentKernelReceivedToRotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessKernelPipelineOutbox — processeur de l'Outbox du pipeline Kernel.
 *
 * Responsabilités (DEC-063, Section 5 du contrat d'audit) :
 *   1. Sélectionner les événements CURRENT_KERNEL_RECEIVED non traités.
 *   2. Verrouiller un événement (lockForUpdate) pour éviter deux traitements simultanés.
 *   3. Reconstruire CurrentKernelReceived depuis le payload JSON.
 *   4. Appeler ApplyCurrentKernelReceivedToRotation::applyCount() — comptabilisation idempotente.
 *   5. Appeler KernelPipelineOrchestrator::run() — déclenche le Blueprint suivant.
 *   6. Marquer l'événement traité (processed_at) UNIQUEMENT après les deux succès.
 *   7. En cas d'exception : conserver l'événement non traité, incrémenter attempt_count, sauver last_error.
 *   8. Permettre le rejeu (processed_at NULL + attempt_count < MAX_ATTEMPTS).
 *
 * RÈGLE KRP-R11 :
 *   Après une production engagée ayant atteint ReadyBank, CURRENT_KERNEL_RECEIVED
 *   est le seul déclencheur autorisant la création du Blueprint suivant.
 *   Ce processeur est le composant qui exécute ce déclenchement.
 *
 * Interdictions :
 *   - N'appelle aucun composant BankWorker.
 *   - Ne crée jamais de Blueprint sans passer par KernelBlueprintFactory (via Orchestrator).
 *   - Ne modifie jamais la logique de comptabilisation (→ ApplyCurrentKernelReceivedToRotation).
 */
final class ProcessKernelPipelineOutbox
{
    private const OUTBOX_TABLE    = 'kernel_pipeline_outbox';
    private const EVENT_TYPE      = 'CURRENT_KERNEL_RECEIVED';
    private const MAX_ATTEMPTS    = 5;
    private const DEFAULT_BATCH   = 10;

    public const OUTCOME_PROCESSED          = 'PROCESSED';
    public const OUTCOME_NO_OP              = 'NO_OP';
    public const OUTCOME_ERROR              = 'ERROR';
    public const OUTCOME_ALREADY_PROCESSED  = 'ALREADY_PROCESSED';

    public function __construct(
        private readonly ApplyCurrentKernelReceivedToRotation $listener,
        private readonly KernelPipelineOrchestrator           $orchestrator,
        private readonly KernelPipelineOutboxRepository       $outboxRepo,
    ) {}

    /**
     * Traite un lot d'événements en attente.
     *
     * @return array<int, array{
     *     event_id: string,
     *     outcome: string,
     *     orchestrator_status?: string,
     *     error?: string
     * }>
     */
    public function process(int $batchSize = self::DEFAULT_BATCH): array
    {
        $pending = $this->outboxRepo->findPending(self::EVENT_TYPE, $batchSize);
        $results = [];

        foreach ($pending as $row) {
            // Filtrer les événements ayant déjà atteint la limite de tentatives
            if ((int) $row->attempt_count >= self::MAX_ATTEMPTS) {
                continue;
            }

            $results[] = $this->processOne($row);
        }

        return $results;
    }

    /**
     * Traite un seul événement Outbox.
     *
     * Séquence :
     *   1. Incrémenter attempt_count en début de traitement (verrou optimiste)
     *   2. Reconstruire l'événement
     *   3. applyCount() — comptabilisation idempotente
     *   4. orchestrator::run() — Blueprint suivant
     *   5. Marquer processed_at (succès total)
     *   6. En cas d'erreur : sauver last_error (attempt_count déjà incrémenté)
     *
     * @return array{event_id: string, outcome: string, orchestrator_status?: string, error?: string}
     */
    private function processOne(object $row): array
    {
        // ── Vérification avant traitement ─────────────────────────────────────
        if ($row->processed_at !== null) {
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_ALREADY_PROCESSED];
        }

        // ── Verrou optimiste : incrémenter attempt_count immédiatement ─────────
        // Cela agit comme un verrou léger. En cas de crash après cet incrément,
        // l'événement reste rejouable (attempt_count < MAX_ATTEMPTS).
        $incremented = DB::table(self::OUTBOX_TABLE)
            ->where('event_id', $row->event_id)
            ->whereNull('processed_at')
            ->where('attempt_count', (int) $row->attempt_count) // vérifie qu'aucun autre worker n'a commencé
            ->update([
                'attempt_count' => (int) $row->attempt_count + 1,
                'updated_at'    => now(),
            ]);

        if ($incremented === 0) {
            // Un autre worker a pris cet événement entre la lecture et l'incrément
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_NO_OP];
        }

        try {
            // ── 1. Reconstruire l'événement depuis le payload ─────────────────
            $payload = json_decode((string) $row->payload, true);

            if (! is_array($payload)) {
                throw new \RuntimeException(
                    "Payload JSON invalide pour event_id={$row->event_id}"
                );
            }

            $event = CurrentKernelReceived::fromPayload($payload);

            // ── 2. Comptabilisation idempotente ───────────────────────────────
            $this->listener->applyCount($event);

            // ── 3. Déclencher le Blueprint suivant (KRP-R11) ──────────────────
            $orchResult = $this->orchestrator->run($event->domain);

            Log::info('[ProcessKernelPipelineOutbox] Événement traité.', [
                'event_id'            => $row->event_id,
                'blueprint_id'        => $event->blueprintId,
                'orchestrator_status' => $orchResult['status'],
            ]);

            // ── 4. Marquer traité UNIQUEMENT après succès complet ─────────────
            DB::table(self::OUTBOX_TABLE)
                ->where('event_id', $row->event_id)
                ->update([
                    'processed_at' => now(),
                    'updated_at'   => now(),
                ]);

            return [
                'event_id'            => $row->event_id,
                'outcome'             => self::OUTCOME_PROCESSED,
                'orchestrator_status' => $orchResult['status'],
            ];

        } catch (Throwable $e) {
            // ── Conserver l'erreur pour rejeu — attempt_count déjà incrémenté ─
            DB::table(self::OUTBOX_TABLE)
                ->where('event_id', $row->event_id)
                ->update([
                    'last_error' => mb_substr($e->getMessage(), 0, 2000),
                    'updated_at' => now(),
                ]);

            Log::error('[ProcessKernelPipelineOutbox] Échec traitement.', [
                'event_id' => $row->event_id,
                'error'    => $e->getMessage(),
            ]);

            return [
                'event_id' => $row->event_id,
                'outcome'  => self::OUTCOME_ERROR,
                'error'    => $e->getMessage(),
            ];
        }
    }
}
