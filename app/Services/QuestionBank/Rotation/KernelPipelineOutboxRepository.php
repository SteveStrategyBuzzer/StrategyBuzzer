<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * KernelPipelineOutboxRepository — accès à kernel_pipeline_outbox.
 *
 * Responsabilités (DEC-063) :
 *   - Insérer un événement CURRENT_KERNEL_RECEIVED dans l'Outbox.
 *   - Marquer un événement comme traité.
 *   - Lire un événement par event_id.
 *
 * L'Outbox est écrite dans la même transaction que la réception ReadyBank,
 * garantissant l'atomicité de la chaîne Blueprint → READY_BANK_RECEIVED → signal.
 *
 * Interdictions :
 *   - Ne traite jamais les événements (→ ApplyCurrentKernelReceivedToRotation).
 *   - Ne connaît pas la logique KRP.
 */
final class KernelPipelineOutboxRepository
{
    private const TABLE = 'kernel_pipeline_outbox';

    // =========================================================================
    // Écriture
    // =========================================================================

    /**
     * Insère un événement CURRENT_KERNEL_RECEIVED dans l'Outbox.
     *
     * Appelé dans la même transaction que KernelBlueprintReadyBankReceiver::receive().
     */
    public function insertEvent(CurrentKernelReceived $event): void
    {
        DB::table(self::TABLE)->insert([
            'event_id'      => $event->eventId,
            'event_type'    => $event->eventType,
            'payload'       => json_encode($event->toPayload(), JSON_UNESCAPED_UNICODE),
            'processed_at'  => null,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * Marque un événement comme traité.
     * NO-OP si l'événement est déjà traité ou absent.
     */
    public function markProcessed(string $eventId): void
    {
        DB::table(self::TABLE)
            ->where('event_id', $eventId)
            ->whereNull('processed_at')
            ->update([
                'processed_at' => now(),
                'updated_at'   => now(),
            ]);
    }

    // =========================================================================
    // Lecture
    // =========================================================================

    /**
     * Retourne un événement par event_id, ou null s'il est absent.
     */
    public function findByEventId(string $eventId): ?object
    {
        return DB::table(self::TABLE)
            ->where('event_id', $eventId)
            ->first();
    }

    /**
     * Retourne les événements non traités du type donné (pour polling / rejeu).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function findPending(string $eventType = 'CURRENT_KERNEL_RECEIVED', int $limit = 50): \Illuminate\Support\Collection
    {
        return DB::table(self::TABLE)
            ->where('event_type', $eventType)
            ->whereNull('processed_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();
    }
}
