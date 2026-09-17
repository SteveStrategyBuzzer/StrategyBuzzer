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
        private readonly KernelBlueprintRunRepository $runs = new KernelBlueprintRunRepository(),
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
     * Test boundary. The external scenario chooses the first phase after KBP
     * returns the id; no phase, destination or precondition enters KBP.
     */
    public function provisionForTest(string $requestReference): string
    {
        if (! app()->runningUnitTests() || ! str_starts_with($requestReference, 'test:')) {
            throw new RuntimeException('[KBP] Entrée de test refusée hors contexte PHPUnit isolé.');
        }

        return $this->provision($requestReference);
    }

    /**
     * Deletes a Blueprint identified only by the id retained by the external
     * test scenario. Parent deletion cascades to slots and request binding.
     */
    public function cleanupTestContext(string $blueprintId): void
    {
        if (! app()->runningUnitTests()) {
            throw new RuntimeException('[KBP] Nettoyage de test refusé hors contexte PHPUnit isolé.');
        }

        $this->assertNonPublicSchema();

        DB::transaction(function () use ($blueprintId): void {
            $this->runs->deleteById($blueprintId);
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
            // Same reference: the losing transaction is fully rolled back,
            // then observes the winner's binding after its commit.
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $winner = $this->findBlueprintId($requestReference);
                if ($winner !== null) {
                    return $winner;
                }

                usleep(10_000);
            }

            // Different reference: never adopt the already-active Blueprint.
            throw new RuntimeException(
                '[KBP] Un Blueprint actif existe déjà pour une autre demande.',
                previous: $exception,
            );
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