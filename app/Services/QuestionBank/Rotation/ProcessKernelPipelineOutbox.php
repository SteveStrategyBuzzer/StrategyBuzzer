<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * ProcessKernelPipelineOutbox — frontière CURRENT_KERNEL_RECEIVED → nouveau Blueprint.
 *
 * Contrat actif : CURRENT_KERNEL_RECEIVED ne va pas directement à KRP.
 * L'événement autorise la création du Blueprint suivant ; ce nouveau Blueprint
 * est ensuite remis à KernelRotationPlanner par KernelPipelineOrchestrator.
 *
 * La transaction englobe création/rotation + processed_at afin qu'un crash entre
 * les deux n'autorise jamais deux Blueprints pour le même événement Outbox.
 *
 * Si KRP est BLOCKED ou attend encore DEPTH_EXHAUSTED, le signal reste rejouable :
 * processed_at n'est pas écrit et aucun nouveau Blueprint n'est conservé.
 */
final class ProcessKernelPipelineOutbox
{
    private const OUTBOX_TABLE = 'kernel_pipeline_outbox';
    private const EVENT_TYPE = 'CURRENT_KERNEL_RECEIVED';
    private const MAX_ATTEMPTS = 5;
    private const DEFAULT_BATCH = 10;

    public const OUTCOME_PROCESSED = 'PROCESSED';
    public const OUTCOME_NO_OP = 'NO_OP';
    public const OUTCOME_ERROR = 'ERROR';
    public const OUTCOME_ALREADY_PROCESSED = 'ALREADY_PROCESSED';

    public function __construct(
        private readonly KernelPipelineOrchestrator $orchestrator,
        private readonly KernelPipelineOutboxRepository $outboxRepo,
    ) {}

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
     * @return array{event_id:string,outcome:string,orchestrator_status?:string,error?:string}
     */
    private function processOne(object $row): array
    {
        if ($row->processed_at !== null) {
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_ALREADY_PROCESSED];
        }

        $incremented = DB::table(self::OUTBOX_TABLE)
            ->where('event_id', $row->event_id)
            ->whereNull('processed_at')
            ->where('attempt_count', (int) $row->attempt_count)
            ->update([
                'attempt_count' => (int) $row->attempt_count + 1,
                'updated_at' => now(),
            ]);

        if ($incremented === 0) {
            return ['event_id' => $row->event_id, 'outcome' => self::OUTCOME_NO_OP];
        }

        try {
            $payload = json_decode((string) $row->payload, true);
            if (!is_array($payload)) {
                throw new RuntimeException("Payload JSON invalide pour event_id={$row->event_id}");
            }

            // Validation structurelle seulement. Le signal ne devient jamais une
            // commande directe vers KernelRotationPlanner.
            $event = CurrentKernelReceived::fromPayload($payload);

            $orchResult = DB::transaction(function () use ($row, $event): array {
                $result = $this->orchestrator->run();

                if (in_array($result['status'], [
                    KernelPipelineOrchestrator::STATUS_BLOCKED,
                    KernelPipelineOrchestrator::STATUS_AWAITING_DEPTH_EXHAUSTED,
                ], true)) {
                    throw new RuntimeException(
                        '[CURRENT_KERNEL_RECEIVED] Déclenchement différé : état KRP '
                        . $result['status'] . '. Événement conservé pour reprise.'
                    );
                }

                DB::table(self::OUTBOX_TABLE)
                    ->where('event_id', $row->event_id)
                    ->whereNull('processed_at')
                    ->update([
                        'processed_at' => now(),
                        'last_error' => null,
                        'updated_at' => now(),
                    ]);

                Log::info('[ProcessKernelPipelineOutbox] CURRENT_KERNEL_RECEIVED traité.', [
                    'event_id' => $row->event_id,
                    'blueprint_id' => $event->blueprintId,
                    'orchestrator_status' => $result['status'],
                ]);

                return $result;
            });

            return [
                'event_id' => $row->event_id,
                'outcome' => self::OUTCOME_PROCESSED,
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
                'error' => $e->getMessage(),
            ]);

            return [
                'event_id' => $row->event_id,
                'outcome' => self::OUTCOME_ERROR,
                'error' => $e->getMessage(),
            ];
        }
    }
}
