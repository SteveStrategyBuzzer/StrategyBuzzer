<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\Listeners;

use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;

/**
 * ApplyCurrentKernelReceivedToRotation — listener de l'événement CURRENT_KERNEL_RECEIVED.
 *
 * Responsabilités (DEC-063 + RACCORDEMENT B du flow canonique 2026-08-11) :
 *   - Vérifier l'idempotence via kernel_current_kernel_receipts (PK blueprint_id)
 *   - Si non comptabilisé : confirmer la consommation Taxonomy (confirmConsumed)
 *     + insérer le reçu + incrémenter kernel_received_total[depth][domain]
 *   - Marquer l'événement Outbox comme traité
 *
 * RACCORDEMENT B : la consommation Taxonomy est confirmée UNIQUEMENT ici — après
 * acceptation ReadyBank (CURRENT_KERNEL_RECEIVED), jamais au simple passage dans
 * QuestionIntent. Le reçu sert de gate d'idempotence : une double réception du
 * même Blueprint ne produit qu'UN SEUL avancement du curseur Taxonomy.
 *
 * Tout est exécuté dans une transaction atomique.
 * Rejouable : un événement déjà comptabilisé ne produit aucun second incrément.
 */
final class ApplyCurrentKernelReceivedToRotation
{
    private const RECEIPTS_TABLE = 'kernel_current_kernel_receipts';
    private const OUTBOX_TABLE   = 'kernel_pipeline_outbox';
    private const STATE_V2_TABLE = 'kernel_rotation_state_v2';

    public function __construct(
        private readonly TaxonomyNavigatorInterface $taxonomy,
    ) {}

    /**
     * Comptabilisation idempotente du noyau reçu (sans marquer l'Outbox).
     *
     * Peut être appelé seul lorsque le marquage Outbox est géré par l'appelant
     * (ex : ProcessKernelPipelineOutbox qui marque processed_at APRÈS création
     * du Blueprint suivant).
     *
     * Idempotent : un même blueprint_id ne produit qu'un seul incrément,
     * quel que soit le nombre d'appels.
     */
    public function applyCount(CurrentKernelReceived $event): void
    {
        DB::transaction(function () use ($event) {

            // ── Idempotence ───────────────────────────────────────────────────
            $alreadyReceived = DB::table(self::RECEIPTS_TABLE)
                ->where('blueprint_id', $event->blueprintId)
                ->exists();

            if (! $alreadyReceived) {
                // ── RACCORDEMENT B : consommation Taxonomy, gated par le reçu ──
                // Double réception du même Blueprint = UN SEUL avancement.
                $this->taxonomy->confirmConsumed((int) $event->depth, (string) $event->domain);

                // Insérer le reçu de comptabilisation
                DB::table(self::RECEIPTS_TABLE)->insert([
                    'blueprint_id' => $event->blueprintId,
                    'event_id'     => $event->eventId,
                    'depth'        => $event->depth,
                    'domain_code'  => $event->domain,
                    'received_at'  => $event->occurredAt,
                ]);

                // Incrémenter kernel_received_total
                (new DepthNeedMatrix())->incrementKernelReceived(
                    $event->depth,
                    $event->domain
                );

                // Mettre à jour last_counted_blueprint_identity
                DB::table(self::STATE_V2_TABLE)
                    ->whereNotNull('id')
                    ->update([
                        'last_counted_blueprint_identity' => $event->blueprintId,
                        'updated_at'                      => now(),
                    ]);
            }
        });
    }

    /**
     * Comptabilisation + marquage Outbox dans une seule transaction.
     *
     * Usage : appel direct (sans ProcessKernelPipelineOutbox).
     * Pour le flux Outbox avec déclenchement du Blueprint suivant,
     * utiliser applyCount() + KernelPipelineOrchestrator::run() +
     * marquage manuel de processed_at via ProcessKernelPipelineOutbox.
     */
    public function handle(CurrentKernelReceived $event): void
    {
        $this->applyCount($event);

        // ── Marquer l'événement Outbox comme traité ───────────────────────────
        if ($event->eventId !== '') {
            DB::table(self::OUTBOX_TABLE)
                ->where('event_id', $event->eventId)
                ->whereNull('processed_at')
                ->update([
                    'processed_at' => now(),
                    'updated_at'   => now(),
                ]);
        }
    }
}
