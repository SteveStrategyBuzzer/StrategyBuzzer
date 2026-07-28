<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelBlueprintReadyBankReceiver — reçoit canoniquement un Blueprint en ReadyBank.
 *
 * Responsabilités (DEC-063, spec section 11) :
 *   - Accepter le Blueprint terminé (ENGAGED_IN_PIPELINE).
 *   - Dans une seule transaction atomique :
 *       1. Passer l'état Blueprint → READY_BANK_RECEIVED.
 *       2. Écrire l'événement CURRENT_KERNEL_RECEIVED dans kernel_pipeline_outbox.
 *   - Retourner l'événement créé (pour orchestration aval).
 *
 * Règle de non-jouabilité (DEC-052) :
 *   Un Blueprint est comptabilisé dès sa réception canonique, même si des slots
 *   sont FAIL ou en correction. La quarantaine ne bloque jamais la rotation.
 *
 * Interdictions :
 *   - Ne traite pas le signal (→ ApplyCurrentKernelReceivedToRotation).
 *   - Ne modifie jamais depth, domain, ni aucun slot métier du Blueprint.
 *   - N'émet aucun signal lorsque le Blueprint est NOT_ENGAGED_PRODUCTION_ON_HOLD.
 *
 * @throws RuntimeException STOP si le Blueprint n'a pas de blueprint_id,
 *                          ou s'il n'est pas dans l'état ENGAGED_IN_PIPELINE.
 */
final class KernelBlueprintReadyBankReceiver
{
    private const RUNS_TABLE   = 'kernel_blueprint_runs';
    private const OUTBOX_TABLE = 'kernel_pipeline_outbox';

    public function __construct(
        private readonly KernelBlueprintRunRepository    $runRepository,
        private readonly KernelPipelineOutboxRepository  $outboxRepository,
    ) {}

    /**
     * Reçoit un Blueprint terminé et génère le signal CURRENT_KERNEL_RECEIVED.
     *
     * Préconditions :
     *   - $blueprint->blueprint_id est non-null.
     *   - $blueprint->depth est non-null.
     *   - $blueprint->domain est non-null.
     *   - L'enregistrement dans kernel_blueprint_runs est en état ENGAGED_IN_PIPELINE.
     *
     * @throws RuntimeException si le Blueprint est invalide ou dans un état incorrect.
     */
    public function receive(KernelBlueprint $blueprint): CurrentKernelReceived
    {
        if ($blueprint->blueprint_id === null) {
            throw new RuntimeException(
                '[KernelBlueprintReadyBankReceiver] STOP — blueprint_id manquant.'
            );
        }

        if ($blueprint->depth === null || $blueprint->domain === null) {
            throw new RuntimeException(
                '[KernelBlueprintReadyBankReceiver] STOP — depth ou domain manquant '
                . "sur le Blueprint {$blueprint->blueprint_id}."
            );
        }

        // ── Vérifier l'état ENGAGED_IN_PIPELINE ──────────────────────────────
        $run = $this->runRepository->findById($blueprint->blueprint_id);

        if ($run === null) {
            throw new RuntimeException(
                '[KernelBlueprintReadyBankReceiver] STOP — aucun enregistrement '
                . "dans kernel_blueprint_runs pour blueprint_id={$blueprint->blueprint_id}."
            );
        }

        if ($run->execution_state !== 'ENGAGED_IN_PIPELINE') {
            throw new RuntimeException(
                '[KernelBlueprintReadyBankReceiver] STOP — état inattendu '
                . "{$run->execution_state} pour blueprint_id={$blueprint->blueprint_id}. "
                . 'Attendu : ENGAGED_IN_PIPELINE.'
            );
        }

        // ── Construire l'événement ────────────────────────────────────────────
        $occurredAt = now()->toIso8601String();
        $event      = new CurrentKernelReceived(
            eventId:     (string) Str::uuid(),
            blueprintId: $blueprint->blueprint_id,
            depth:       (int) $blueprint->depth,
            domain:      (string) $blueprint->domain,
            occurredAt:  $occurredAt,
        );

        // ── Transaction atomique : READY_BANK_RECEIVED + Outbox ──────────────
        DB::transaction(function () use ($blueprint, $event, $occurredAt) {
            $this->runRepository->markReadyBankReceived(
                $blueprint->blueprint_id,
                $occurredAt
            );

            $this->outboxRepository->insertEvent($event);
        });

        return $event;
    }
}
