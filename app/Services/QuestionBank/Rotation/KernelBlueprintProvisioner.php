<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Events\CurrentKernelReceived;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Unique KBP boundary. It owns request idempotence, while the Factory remains
 * the internal mechanism that builds the empty persistent aggregate.
 */
final class KernelBlueprintProvisioner
{
    private const REFERENCES_TABLE = 'kernel_blueprint_request_refs';
    private const RUNS_TABLE = 'kernel_blueprint_runs';

    public function __construct(
        private readonly KernelBlueprintFactory $factory = new KernelBlueprintFactory(),
    ) {}

    /**
     * Production boundary: the stable CURRENT_KERNEL_RECEIVED event id is the
     * only request reference accepted here.
     */
    public function provisionForCurrentKernelReceived(CurrentKernelReceived $event): string
    {
        return $this->provision($event->eventId);
    }

    /**
     * Test boundary. The requesting phase is deliberately input-only: it is
     * neither persisted nor returned and grants no intellectual precondition.
     */
    public function provisionForTest(string $technicalReference, string $requestingPhase): string
    {
        if (! app()->runningUnitTests() || ! str_starts_with($technicalReference, 'test:')) {
            throw new RuntimeException('[KBP] Entrée de test refusée hors contexte PHPUnit isolé.');
        }

        if ($requestingPhase === '') {
            throw new RuntimeException('[KBP] Phase demandeuse de test requise.');
        }

        return $this->provision($technicalReference);
    }

    /**
     * Deletes only a test context owned by its external technical reference.
     * Parent deletion cascades to the seven slots and to the request binding.
     */
    public function cleanupTestContext(string $blueprintId, string $technicalReference): void
    {
        if (! app()->runningUnitTests() || ! str_starts_with($technicalReference, 'test:')) {
            throw new RuntimeException('[KBP] Nettoyage de test refusé hors contexte PHPUnit isolé.');
        }

        $this->assertNonPublicSchema();

        DB::transaction(function () use ($blueprintId, $technicalReference): void {
            $binding = DB::table(self::REFERENCES_TABLE)
                ->where('request_reference', $technicalReference)
                ->lockForUpdate()
                ->first();

            if ($binding === null) {
                return;
            }

            if ((string) $binding->blueprint_id !== $blueprintId) {
                throw new RuntimeException('[KBP] Nettoyage de contexte test non autorisé.');
            }

            DB::table(self::RUNS_TABLE)
                ->where('blueprint_id', $blueprintId)
                ->delete();
        });
    }

    private function provision(string $requestReference): string
    {
        if ($requestReference === '' || strlen($requestReference) > 128) {
            throw new RuntimeException('[KBP] request_reference invalide.');
        }

        $existing = $this->findBlueprintId($requestReference);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($requestReference): string {
                // Re-check under the transaction so ordinary replays avoid a
                // needless Factory invocation.
                $existing = $this->findBlueprintId($requestReference);
                if ($existing !== null) {
                    return $existing;
                }

                $blueprint = $this->factory->create();

                DB::table(self::REFERENCES_TABLE)->insert([
                    'request_reference' => $requestReference,
                    'blueprint_id' => $blueprint->blueprint_id,
                ]);

                return $blueprint->blueprint_id;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // A competing request with this reference may have committed while
            // this complete transaction was rolled back. It is a replay, not
            // the one-active-blueprint conflict.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $winner = $this->findBlueprintId($requestReference);
                if ($winner !== null) {
                    return $winner;
                }

                usleep(10_000);
            }

            throw $exception;
        }
    }

    private function findBlueprintId(string $requestReference): ?string
    {
        $blueprintId = DB::table(self::REFERENCES_TABLE)
            ->where('request_reference', $requestReference)
            ->value('blueprint_id');

        return $blueprintId === null ? null : (string) $blueprintId;
    }

    private function assertNonPublicSchema(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('[KBP] Nettoyage de test exige un schéma PostgreSQL isolé.');
        }

        $schema = DB::selectOne('SELECT current_schema() AS schema_name');
        if (($schema->schema_name ?? null) === 'public') {
            throw new RuntimeException('[KBP] Nettoyage de test refusé dans le schéma public.');
        }
    }
}