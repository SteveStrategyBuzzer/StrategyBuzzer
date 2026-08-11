<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use Closure;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * KernelPipelineAdvancer — tapis roulant du flow canonique (2026-08-11).
 *
 * Fait avancer d'UN cran, à chaque tick, l'unique noyau en vol
 * (single-flight garanti par KernelBlueprintFactory) :
 *
 *   frame_status NULL  → questions:kernel:skeleton            (Phase 1 — squelette)
 *   draft              → questions:kernel:validate-structure  (Validation Phase 1)
 *   awaiting_content   → questions:kernel:fill-content        (Phase 1 — contenu)
 *   content_ready      → questions:kernel:validate-content --apply (Validation Phase 2)
 *                        puis réception ReadyBank au MÊME tick
 *   content_validated / partial_review / human_review / quarantine / rejected
 *                      → réception ReadyBank (DEC-052 : la quarantaine ne bloque
 *                        JAMAIS la rotation ; le contenu reste hors gameplay)
 *
 * Après réception, KernelBlueprintReadyBankReceiver émet CURRENT_KERNEL_RECEIVED
 * (outbox). ProcessKernelPipelineOutbox applique alors le RACCORDEMENT B :
 * Taxonomy.confirmConsumed() idempotent (gated par le reçu) + comptabilisation,
 * puis crée le Blueprint suivant (KRP-R11).
 *
 * Garde-fous :
 *   - advance_attempts : après MAX_STAGE_ATTEMPTS échecs consécutifs d'une même
 *     étape, frame_status = quarantine (échec EXPLICITE, loggé en error) ; le
 *     noyau est ensuite reçu (DEC-052) et la rotation continue.
 *   - Récupération : un Blueprint ENGAGED_IN_PIPELINE sans QuestionIntent
 *     (engagé avant le raccordement A) est ré-encodé depuis le curseur Taxonomy —
 *     lequel n'a pas bougé puisque confirmConsumed n'intervient qu'à la réception.
 *
 * Réutilise STRICTEMENT les commandes de phase existantes (aucune logique
 * de génération dupliquée). Ne touche AUCUN composant BankWorker.
 *
 * ⛔ KLD / KEY_STRUCTURE : SUPERSEDED — jamais invoqués ici.
 */
final class KernelPipelineAdvancer
{
    public const OUTCOME_NO_ACTIVE      = 'NO_ACTIVE_BLUEPRINT';
    public const OUTCOME_NOT_ENGAGED    = 'BLUEPRINT_NOT_ENGAGED';
    public const OUTCOME_INTENT_ENCODED = 'INTENT_ENCODED';
    public const OUTCOME_STAGE_ADVANCED = 'STAGE_ADVANCED';
    public const OUTCOME_STAGE_FAILED   = 'STAGE_FAILED';
    public const OUTCOME_QUARANTINED    = 'QUARANTINED_AFTER_RETRIES';
    public const OUTCOME_RECEIVED       = 'READY_BANK_RECEIVED';
    public const OUTCOME_BLOCKED        = 'BLOCKED_UNKNOWN_STATUS';

    /** Échecs consécutifs d'une étape avant mise en quarantaine explicite. */
    public const MAX_STAGE_ATTEMPTS = 5;

    private const INTENTS_TABLE = 'question_intents';

    /** frame_status terminaux côté Phases 1-2 → réception ReadyBank (DEC-052). */
    private const RECEIVABLE_STATUSES = [
        'content_validated',
        'partial_review',
        'human_review',
        'quarantine',
        'rejected',
    ];

    /**
     * @param Closure|null $stageRunner fn(string $command, array $params): int —
     *                                  par défaut Artisan::call (injectable en test).
     */
    public function __construct(
        private readonly KernelBlueprintRunRepository     $runRepository,
        private readonly TaxonomyNavigatorInterface       $taxonomy,
        private readonly QuestionIntentEncoder            $encoder,
        private readonly KernelBlueprintReadyBankReceiver $receiver,
        private readonly ?Closure                         $stageRunner = null,
    ) {}

    /**
     * Fait avancer le noyau en vol d'UN cran.
     *
     * @return array{outcome: string, blueprint_id?: string, intent_id?: int, stage?: string, detail?: string}
     */
    public function advance(): array
    {
        $run = $this->runRepository->findActive();

        if ($run === null) {
            return ['outcome' => self::OUTCOME_NO_ACTIVE];
        }

        if ($run->execution_state !== 'ENGAGED_IN_PIPELINE') {
            // CREATED_UNENGAGED : l'engagement appartient à KernelPipelineOrchestrator.
            return ['outcome' => self::OUTCOME_NOT_ENGAGED, 'blueprint_id' => (string) $run->blueprint_id];
        }

        $intent = DB::table(self::INTENTS_TABLE)
            ->where('blueprint_id', $run->blueprint_id)
            ->first();

        if ($intent === null) {
            return $this->recoverMissingIntent($run);
        }

        $status = $intent->frame_status;

        if ($status === null || $status === '') {
            return $this->runStage($run, $intent, 'questions:kernel:skeleton');
        }

        return match ($status) {
            'draft'            => $this->runStage($run, $intent, 'questions:kernel:validate-structure'),
            'awaiting_content' => $this->runStage($run, $intent, 'questions:kernel:fill-content'),
            'content_ready'    => $this->validateThenReceive($run, $intent),
            default            => in_array($status, self::RECEIVABLE_STATUSES, true)
                ? $this->receive($run, $intent, (string) $status)
                : $this->blocked($run, $intent, (string) $status),
        };
    }

    // =========================================================================
    // Étapes Phases 1-2 — délégation aux commandes canoniques existantes
    // =========================================================================

    private function runStage(object $run, object $intent, string $command, array $extra = []): array
    {
        $exit = $this->callStage($command, ['intent_id' => $intent->id] + $extra);

        if ($exit === 0) {
            $this->resetAttempts((int) $intent->id);

            return [
                'outcome'      => self::OUTCOME_STAGE_ADVANCED,
                'blueprint_id' => (string) $run->blueprint_id,
                'intent_id'    => (int) $intent->id,
                'stage'        => $command,
            ];
        }

        return $this->registerFailure($run, $intent, $command, $exit);
    }

    /**
     * Validation Phase 2 puis réception ReadyBank au même tick.
     *
     * Policy A/B : frame_status reste content_ready + exit 0 → réception directe.
     * Policy C/D : --apply écrit partial_review → réception (DEC-052).
     * Échec dur : frame_status inchangé + exit ≠ 0 → advance_attempts++.
     */
    private function validateThenReceive(object $run, object $intent): array
    {
        $exit = $this->callStage('questions:kernel:validate-content', [
            'intent_id' => $intent->id,
            '--apply'   => true,
        ]);

        $fresh = DB::table(self::INTENTS_TABLE)->where('id', $intent->id)->first();

        if ($fresh !== null && $fresh->frame_status !== 'content_ready') {
            return $this->receive($run, $fresh, (string) $fresh->frame_status);
        }

        if ($exit === 0) {
            return $this->receive($run, $intent, 'content_ready');
        }

        return $this->registerFailure($run, $intent, 'questions:kernel:validate-content', $exit);
    }

    // =========================================================================
    // Réception ReadyBank — fin du parcours du noyau (raccordement B amont)
    // =========================================================================

    private function receive(object $run, object $intent, string $status): array
    {
        if ($status !== 'content_ready' && $status !== 'content_validated') {
            Log::warning(
                '[KernelPipelineAdvancer] Réception ReadyBank d\'un noyau non nominal '
                . '(DEC-052 — la quarantaine ne bloque jamais la rotation).',
                [
                    'blueprint_id' => (string) $run->blueprint_id,
                    'intent_id'    => (int) $intent->id,
                    'frame_status' => $status,
                ]
            );
        }

        $blueprint               = new KernelBlueprint();
        $blueprint->blueprint_id = (string) $run->blueprint_id;
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);

        $event = $this->receiver->receive($blueprint);

        Log::info('[KernelPipelineAdvancer] CURRENT_KERNEL_RECEIVED émis.', [
            'blueprint_id' => (string) $run->blueprint_id,
            'event_id'     => $event->eventId,
            'frame_status' => $status,
        ]);

        return [
            'outcome'      => self::OUTCOME_RECEIVED,
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => (int) $intent->id,
            'detail'       => 'frame_status=' . $status,
        ];
    }

    // =========================================================================
    // Récupération — Blueprint engagé sans QuestionIntent (pré-raccordement A)
    // =========================================================================

    private function recoverMissingIntent(object $run): array
    {
        $territory = $this->taxonomy->peekNext((int) $run->depth, (string) $run->domain_code);

        if ($territory === null) {
            throw new RuntimeException(
                '[KernelPipelineAdvancer] STOP — Blueprint ' . $run->blueprint_id
                . ' ENGAGED_IN_PIPELINE sans QuestionIntent et bassin Taxonomy vide ('
                . $run->depth . '×' . $run->domain_code . ') : incohérence à résoudre manuellement.'
            );
        }

        $blueprint               = new KernelBlueprint();
        $blueprint->blueprint_id = (string) $run->blueprint_id;
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);
        $blueprint->fillTaxonomy(
            $territory['sub_domain']                                          ?? '',
            $territory['subject']                                             ?? '',
            $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '',
        );

        $intentId = $this->encoder->encode($blueprint);

        Log::info('[KernelPipelineAdvancer] QuestionIntent ré-encodé pour un Blueprint engagé sans intent.', [
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => $intentId,
        ]);

        return [
            'outcome'      => self::OUTCOME_INTENT_ENCODED,
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => $intentId,
        ];
    }

    // =========================================================================
    // Échecs — explicites, bornés, jamais silencieux
    // =========================================================================

    private function registerFailure(object $run, object $intent, string $stage, int $exit): array
    {
        $attempts = (int) ($intent->advance_attempts ?? 0) + 1;

        if ($attempts >= self::MAX_STAGE_ATTEMPTS) {
            DB::table(self::INTENTS_TABLE)->where('id', $intent->id)->update([
                'frame_status'     => 'quarantine',
                'advance_attempts' => $attempts,
                'updated_at'       => now(),
            ]);

            Log::error(
                '[KernelPipelineAdvancer] Étape en échec répété → quarantine '
                . '(réception DEC-052 au prochain tick).',
                [
                    'blueprint_id' => (string) $run->blueprint_id,
                    'intent_id'    => (int) $intent->id,
                    'stage'        => $stage,
                    'attempts'     => $attempts,
                ]
            );

            return [
                'outcome'      => self::OUTCOME_QUARANTINED,
                'blueprint_id' => (string) $run->blueprint_id,
                'intent_id'    => (int) $intent->id,
                'stage'        => $stage,
                'detail'       => "attempts={$attempts}",
            ];
        }

        DB::table(self::INTENTS_TABLE)->where('id', $intent->id)->update([
            'advance_attempts' => $attempts,
            'updated_at'       => now(),
        ]);

        Log::warning('[KernelPipelineAdvancer] Étape en échec — nouvel essai au prochain tick.', [
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => (int) $intent->id,
            'stage'        => $stage,
            'exit'         => $exit,
            'attempts'     => $attempts,
        ]);

        return [
            'outcome'      => self::OUTCOME_STAGE_FAILED,
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => (int) $intent->id,
            'stage'        => $stage,
            'detail'       => "exit={$exit} attempts={$attempts}",
        ];
    }

    private function blocked(object $run, object $intent, string $status): array
    {
        Log::error('[KernelPipelineAdvancer] frame_status inconnu — aucune progression possible.', [
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => (int) $intent->id,
            'frame_status' => $status,
        ]);

        return [
            'outcome'      => self::OUTCOME_BLOCKED,
            'blueprint_id' => (string) $run->blueprint_id,
            'intent_id'    => (int) $intent->id,
            'detail'       => 'frame_status=' . $status,
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function callStage(string $command, array $params): int
    {
        $runner = $this->stageRunner
            ?? static fn (string $cmd, array $p): int => (int) Artisan::call($cmd, $p);

        return (int) $runner($command, $params);
    }

    private function resetAttempts(int $intentId): void
    {
        DB::table(self::INTENTS_TABLE)
            ->where('id', $intentId)
            ->where('advance_attempts', '>', 0)
            ->update(['advance_attempts' => 0, 'updated_at' => now()]);
    }
}
