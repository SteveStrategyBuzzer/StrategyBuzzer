<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessKernelPipelineOutbox — processeur de l'Outbox du pipeline Kernel.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * IMPLÉMENTATION CANONIQUE CURRENT_KERNEL_RECEIVED (V3.2 — 2026-08-14)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * Séquence pour chaque événement CURRENT_KERNEL_RECEIVED :
 *   1. Sélectionner les événements non traités (attempt_count < MAX_ATTEMPTS).
 *   2. Verrou optimiste (incrémenter attempt_count) — évite traitement parallèle.
 *   3. Reconstruire CurrentKernelReceived depuis le payload JSON.
 *   4. KernelRotationPlanner::receiveKernelReceivedV2() — source unique de vérité :
 *        a. idempotence blueprint_id
 *        b. receipt INSERT
 *        c. kernel_received_total +1
 *        d. lecture pending_depth_exhausted_depth
 *        e. éventuelle transition Depth (applyDepthTransition)
 *        f. éventuel depth_state = PRODUCTION_ON_HOLD
 *   5. KernelPipelineOrchestrator::run() — déclenche le Blueprint suivant.
 *   6. Marquer processed_at UNIQUEMENT après succès complet.
 *   7. En cas d'exception : conserver non traité, sauver last_error, laisser rejouable.
 *
 * ═══════════════════════════════════════════════════════════════════════════════
 * UNE RESPONSABILITÉ = UN PROPRIÉTAIRE = UNE IMPLÉMENTATION (DEC-093)
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * ApplyCurrentKernelReceivedToRotation::applyCount() est DÉSACTIVÉ de ce chemin
 * (marqué @deprecated). Toute la logique métier CKR vit dans receiveKernelReceivedV2.
 *
 * Interdictions :
 *   - N'appelle jamais applyCount() (chemin V2 désactivé — DEC-093).
 *   - Ne crée jamais de Blueprint sans passer par KernelBlueprintFactory (via Orchestrator).
 *   - N'invoque aucun composant BankWorker.
 */
final class ProcessKernelPipelineOutbox
{
    private const OUTBOX_TABLE  = 'kernel_pipeline_outbox';
    private const EVENT_TYPE    = 'CURRENT_KERNEL_RECEIVED';
    private const MAX_ATTEMPTS  = 5;
    private const DEFAULT_BATCH = 10;

    public const OUTCOME_PROCESSED         = 'PROCESSED';
    public const OUTCOME_NO_OP             = 'NO_OP';
    public const OUTCOME_ERROR             = 'ERROR';
    public const OUTCOME_ALREADY_PROCESSED = 'ALREADY_PROCESSED';

    public function __construct(
        private readonly KernelRotationPlanner        $planner,
        private readonly KernelPipelineOrchestrator   $orchestrator,
        private readonly KernelPipelineOutboxRepository $outboxRepo,
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
     *   1. Incrémenter attempt_count (verrou optimiste)
     *   2. Reconstruire l'événement depuis payload
     *   3. planner->receiveKernelReceivedV2() — CKR canonique (idempotence + compteur + transition)
     *   4. orchestrator->run() — Blueprint suivant
     *   5. Marquer processed_at (succès total)
     *   6. En cas d'erreur : sauver last_error
     *
     * @return array{event_id: string, outcome: string, orchestrator_status?: string, error?: string}
     */
    private function processOne(object $row): array
    {
        if ($row->processed_at !== null) {
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_ALREADY_PROCESSED];
        }

        // ── Verrou optimiste : incrémenter attempt_count ───────────────────────
        $incremented = DB::table(self::OUTBOX_TABLE)
            ->where('event_id', $row->event_id)
            ->whereNull('processed_at')
            ->where('attempt_count', (int) $row->attempt_count)
            ->update([
                'attempt_count' => (int) $row->attempt_count + 1,
                'updated_at'    => now(),
            ]);

        if ($incremented === 0) {
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_NO_OP];
        }

        try {
            // ── 1. Reconstruire l'événement ───────────────────────────────────
            $payload = json_decode((string) $row->payload, true);

            if (! is_array($payload)) {
                throw new \RuntimeException(
                    "Payload JSON invalide pour event_id={$row->event_id}"
                );
            }

            $event = CurrentKernelReceived::fromPayload($payload);

            // ── 2. CKR canonique (DEC-093) — source unique de vérité ─────────
            // Atomiquement : idempotence → receipt → compteur → pending check → transition
            $this->planner->receiveKernelReceivedV2(
                $event->blueprintId,
                $event->depth,
                $event->domain,
            );

            // ── 3. Blueprint suivant (KRP-R11) ────────────────────────────────
            $orchResult = $this->orchestrator->run();

            Log::info('[ProcessKernelPipelineOutbox] Événement traité.', [
                'event_id'            => $row->event_id,
                'blueprint_id'        => $event->blueprintId,
                'orchestrator_status' => $orchResult['status'],
            ]);

            // ── 4. Marquer traité UNIQUEMENT après succès total ───────────────
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
