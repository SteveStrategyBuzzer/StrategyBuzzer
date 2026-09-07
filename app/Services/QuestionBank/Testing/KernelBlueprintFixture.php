<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\Rotation\KernelBlueprintFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Frontière d'écriture réservée aux scénarios KBP isolés.
 *
 * La Factory de production crée le parent et ses sept slots. Cette fixture
 * complète ensuite la projection persistante et le graphe Taxonomy minimal
 * dans une transaction englobante. Elle ne doit jamais être utilisée comme
 * chemin de production.
 */
final class KernelBlueprintFixture
{
    /** @var array<string, array{occurrence_id: int, subdomain_id: int, subject_id: int, idea_id: int}> */
    private array $ownedGraphs = [];

    public function __construct(
        private readonly KernelBlueprintFactory $factory = new KernelBlueprintFactory(),
    ) {}

    public function create(KernelBlueprintManualPreconditions $scenario): string
    {
        $this->assertIsolatedSchema();
        $scenario->validate();

        $graph = DB::transaction(function () use ($scenario): array {
            $created = $this->factory->create();
            $blueprintId = $created->blueprint_id;
            if ($blueprintId === null) {
                throw new RuntimeException('La KernelBlueprintFactory n’a pas retourné de blueprint_id.');
            }

            $updated = DB::table('kernel_blueprint_runs')
                ->where('blueprint_id', $blueprintId)
                ->where('execution_state', 'CREATED_UNENGAGED')
                ->update([
                    'execution_state' => 'ENGAGED_IN_PIPELINE',
                    'depth' => $scenario->depth,
                    'domain_code' => $scenario->domain,
                    'kernel_code' => $scenario->kernelCode,
                    'engaged_at' => now(),
                    'updated_at' => now(),
                ]);
            if ($updated !== 1) {
                throw new RuntimeException('Le parent Factory du Blueprint manuel est introuvable.');
            }

            $now = now();
            $occurrenceId = DB::table('taxonomy_v11_occurrences')->insertGetId([
                'depth' => $scenario->depth, 'domain_code' => $scenario->domain,
                'ordinal' => 1, 'status' => 'OPEN', 'consecutive_technical_failures' => 0,
                'last_error' => null, 'exhausted_at' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $subdomainId = DB::table('taxonomy_v11_subdomains')->insertGetId([
                'occurrence_id' => $occurrenceId, 'subdomain_name' => $scenario->subdomainActive,
                'status' => 'ACTIVE', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $subjectId = DB::table('taxonomy_v11_subjects')->insertGetId([
                'subdomain_id' => $subdomainId, 'subject_name' => $scenario->subjectActive,
                'status' => 'AVAILABLE', 'idea_attempt_count' => 0, 'idea_generation_exhausted' => false,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $ideaId = DB::table('taxonomy_v11_ideas')->insertGetId([
                'subject_id' => $subjectId, 'idea_value' => $scenario->dominantIdeaActive,
                'validation_status' => 'PASS', 'fail_reason' => null, 'fail_conflict_with' => null,
                'status' => 'CONSUMED', 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('taxonomy_v11_blueprint_assignments')->insert([
                'blueprint_id' => $blueprintId, 'occurrence_id' => $occurrenceId,
                'subdomain_id' => $subdomainId, 'subject_id' => $subjectId, 'idea_id' => $ideaId,
                'depth' => $scenario->depth, 'domain_code' => $scenario->domain,
                'subdomain_active' => $scenario->subdomainActive,
                'subject_active' => $scenario->subjectActive,
                'dominant_idea_active' => $scenario->dominantIdeaActive,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            return [
                'blueprint_id' => $blueprintId,
                'occurrence_id' => $occurrenceId,
                'subdomain_id' => $subdomainId,
                'subject_id' => $subjectId,
                'idea_id' => $ideaId,
            ];
        });

        $blueprintId = $graph['blueprint_id'];
        $this->ownedGraphs[$blueprintId] = [
            'occurrence_id' => $graph['occurrence_id'],
            'subdomain_id' => $graph['subdomain_id'],
            'subject_id' => $graph['subject_id'],
            'idea_id' => $graph['idea_id'],
        ];

        return $blueprintId;
    }

    /** Deletes only the isolated fixture graph, in FK-safe order. */
    public function cleanup(string $blueprintId): void
    {
        $this->assertIsolatedSchema();
        $graph = $this->ownedGraphs[$blueprintId] ?? null;
        if ($graph === null) {
            throw new RuntimeException("La fixture ne possède pas le Blueprint {$blueprintId}.");
        }

        DB::transaction(function () use ($blueprintId, $graph): void {
            DB::table('taxonomy_v11_blueprint_assignments')
                ->where('blueprint_id', $blueprintId)
                ->where('occurrence_id', $graph['occurrence_id'])
                ->where('subdomain_id', $graph['subdomain_id'])
                ->where('subject_id', $graph['subject_id'])
                ->where('idea_id', $graph['idea_id'])
                ->delete();
            DB::table('taxonomy_v11_ideas')->where('id', $graph['idea_id'])->delete();
            DB::table('taxonomy_v11_subjects')->where('id', $graph['subject_id'])->delete();
            DB::table('taxonomy_v11_subdomains')->where('id', $graph['subdomain_id'])->delete();
            DB::table('taxonomy_v11_occurrences')->where('id', $graph['occurrence_id'])->delete();
            DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->delete();
        });
        unset($this->ownedGraphs[$blueprintId]);
    }

    private function assertIsolatedSchema(): void
    {
        $schema = DB::selectOne('SELECT current_schema() AS schema_name')->schema_name ?? null;
        if (! is_string($schema) || trim($schema) === '' || strtolower($schema) === 'public') {
            throw new RuntimeException('KernelBlueprintFixture exige un schéma isolé non-public.');
        }
    }
}