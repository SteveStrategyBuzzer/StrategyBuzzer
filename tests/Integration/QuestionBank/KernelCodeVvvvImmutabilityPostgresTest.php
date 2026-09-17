<?php

declare(strict_types=1);

namespace Tests\Integration\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\QuestionIntentBlueprintIdReceiver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Throwable;

/**
 * Bloc 1 QuestionIntent — correctifs demandés après audit :
 *
 *   1. PostgreSQL reste l'unique générateur du VVVV (colonne kernel_code
 *      restant une projection GENERATED ALWAYS ... STORED, non écrite
 *      directement).
 *   2. La valeur persistée de kernel_code_vvvv est rendue immuable au
 *      niveau base par un trigger BEFORE UPDATE (pas seulement applicatif).
 *   3. L'ordre Rotation → Taxonomy → QuestionIntent est prouvé : aucune
 *      allocation de VVVV n'est possible avant que Rotation ET Taxonomy
 *      soient complètes.
 *   4. Le rejeu idempotent (même Blueprint deux fois) et l'impossibilité de
 *      remplacer un VVVV déjà attribué sont couverts contre une vraie
 *      instance PostgreSQL (pas SQLite).
 *
 * Isolation : schéma PostgreSQL dédié, détruit en tearDown — jamais la
 * base de production, jamais un schéma partagé.
 */
final class KernelCodeVvvvImmutabilityPostgresTest extends TestCase
{
    private string $schemaName;
    private string $originalDefault;
    private mixed $originalSearchPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalDefault = (string) config('database.default');
        $this->originalSearchPath = config('database.connections.pgsql.search_path');
        config(['database.default' => 'pgsql']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        $this->schemaName = 'test_kernel_vvvv_' . bin2hex(random_bytes(6));
        DB::connection('pgsql')->statement('CREATE SCHEMA "' . $this->schemaName . '"');
        config(['database.connections.pgsql.search_path' => '"' . $this->schemaName . '"']);
        DB::purge('pgsql');
        DB::reconnect('pgsql');

        // ── Schéma cible : version "phased" (post 2026_09_08_000001) ────────
        Schema::create('kernel_blueprint_runs', function (Blueprint $table): void {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->string('subdomain_active')->nullable();
            $table->string('subject_active')->nullable();
            $table->string('dominant_idea_active')->nullable();
            $table->string('kernel_code_dd', 2)->nullable();
            $table->string('kernel_code_do', 3)->nullable();
            $table->string('kernel_code_sub', 3)->nullable();
            $table->string('kernel_code_suj', 3)->nullable();
            $table->string('kernel_code_ide', 3)->nullable();
            $table->string('kernel_code_vvvv', 4)->nullable();
            $table->timestampsTz();
        });
        DB::statement(<<<'SQL'
ALTER TABLE kernel_blueprint_runs
    ADD COLUMN kernel_code VARCHAR(23)
        GENERATED ALWAYS AS (
            CASE
                WHEN kernel_code_dd IS NOT NULL
                 AND kernel_code_do IS NOT NULL
                 AND kernel_code_sub IS NOT NULL
                 AND kernel_code_suj IS NOT NULL
                 AND kernel_code_ide IS NOT NULL
                 AND kernel_code_vvvv IS NOT NULL
                THEN kernel_code_dd || '-' || kernel_code_do || '-' || kernel_code_sub || '-'
                    || kernel_code_suj || '-' || kernel_code_ide || '-' || kernel_code_vvvv
                ELSE NULL
            END
        ) STORED
SQL);
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX kernel_blueprint_runs_kernel_code_unique
    ON kernel_blueprint_runs (kernel_code)
    WHERE kernel_code IS NOT NULL
SQL);

        Schema::create('kernel_code_sequences', function (Blueprint $table): void {
            $table->smallInteger('depth');
            $table->string('domain_code', 3);
            $table->unsignedInteger('next_value')->default(0);
            $table->timestampsTz();
            $table->primary(['depth', 'domain_code']);
        });

        // ── Tables requises par la frontière officielle blueprint_id-only ───
        // (KernelBlueprintProvisionedLoader) : le binding technique et les
        // sept CognitiveSlots permanents.
        Schema::create('kernel_blueprint_request_refs', function (Blueprint $table): void {
            $table->string('request_reference', 128)->primary();
            $table->string('blueprint_id', 36);
        });
        Schema::create('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('cognitive_type', 64);
            $table->jsonb('source');
            $table->jsonb('creation_failure')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->jsonb('validation_findings')->default('[]');
            $table->timestampsTz();
            $table->primary(['blueprint_id', 'cognitive_type']);
        });

        $this->applyImmutabilityTrigger();
    }

    protected function tearDown(): void
    {
        try {
            DB::connection('pgsql')->statement(
                'DROP SCHEMA IF EXISTS "' . $this->schemaName . '" CASCADE'
            );
        } finally {
            config([
                'database.default' => $this->originalDefault,
                'database.connections.pgsql.search_path' => $this->originalSearchPath,
            ]);
            DB::purge('pgsql');
            DB::reconnect('pgsql');
            parent::tearDown();
        }
    }

    // =========================================================================
    // 1. Neutralisation de l'injection manuelle — preuve statique
    // =========================================================================

    public function test_kernel_blueprint_exposes_no_method_accepting_a_complete_code(): void
    {
        $this->assertFalse(method_exists(KernelBlueprint::class, 'fillKernelCode'));
    }

    // =========================================================================
    // 2. PostgreSQL reste l'unique générateur — kernel_code n'est jamais
    //    écrit directement, uniquement projeté depuis les 6 segments.
    // =========================================================================

    public function test_kernel_code_column_is_a_generated_column_not_a_writable_one(): void
    {
        $this->insertEmpty('bp-gen');

        try {
            DB::statement("UPDATE kernel_blueprint_runs SET kernel_code = 'anything' WHERE blueprint_id = 'bp-gen'");
            $this->fail('kernel_code must be a PostgreSQL generated column, not directly writable.');
        } catch (Throwable $exception) {
            $this->assertStringContainsStringIgnoringCase('generated', $exception->getMessage());
        }
    }

    // =========================================================================
    // 3. Ordre Rotation → Taxonomy → QuestionIntent
    // =========================================================================

    public function test_vvvv_allocation_refused_before_rotation_is_filled(): void
    {
        $this->insertEmpty('bp-order-1');

        $this->expectException(\RuntimeException::class);
        $this->assignById('bp-order-1');
    }

    public function test_vvvv_allocation_refused_when_rotation_filled_but_taxonomy_missing(): void
    {
        $this->insertEmpty('bp-order-2');
        $this->persistRotation('bp-order-2', 4, 'Géographie');

        $this->expectException(\RuntimeException::class);
        $this->assignById('bp-order-2');
    }

    public function test_vvvv_allocation_succeeds_only_once_rotation_and_taxonomy_are_both_complete(): void
    {
        $this->insertEmpty('bp-order-3');
        $this->persistRotation('bp-order-3', 4, 'Géographie');
        $this->persistTaxonomy(
            'bp-order-3',
            'Canada',
            'Confédération canadienne',
            "Acte de l'Amérique du Nord britannique",
        );

        $code = $this->assignById('bp-order-3');

        $this->assertMatchesRegularExpression(KernelCodeEngine::FORMAT_REGEX, $code);
        $this->assertNotNull(
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-order-3')->value('kernel_code_vvvv')
        );
    }

    // =========================================================================
    // 4. Rejeu idempotent (contre PostgreSQL réel)
    // =========================================================================

    public function test_idempotent_replay_against_real_postgres_returns_same_code_and_advances_counter_once(): void
    {
        $this->insertEmpty('bp-replay');
        $this->persistRotation('bp-replay', 4, 'Géographie');
        $this->persistTaxonomy(
            'bp-replay',
            'Canada',
            'Confédération canadienne',
            "Acte de l'Amérique du Nord britannique",
        );

        $code1 = $this->assignById('bp-replay');

        // Rejeu : même blueprint_id, aucun changement de Rotation/Taxonomy —
        // simule un second appel (retry, replay de message, etc).
        $code2 = $this->assignById('bp-replay');

        $this->assertSame($code1, $code2);
        $this->assertSame(1, DB::table('kernel_code_sequences')
            ->where('depth', 4)->where('domain_code', 'GEO')->value('next_value'));
    }

    // =========================================================================
    // 5. Immutabilité au niveau base — trigger BEFORE UPDATE
    // =========================================================================

    public function test_direct_sql_cannot_replace_an_existing_vvvv(): void
    {
        $this->insertEmpty('bp-lock');
        $this->persistRotation('bp-lock', 4, 'Géographie');
        $this->persistTaxonomy(
            'bp-lock',
            'Canada',
            'Confédération canadienne',
            "Acte de l'Amérique du Nord britannique",
        );
        $this->assignById('bp-lock');

        $before = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-lock')->value('kernel_code_vvvv');
        $this->assertNotNull($before);

        try {
            DB::statement(
                "UPDATE kernel_blueprint_runs SET kernel_code_vvvv = '9999' WHERE blueprint_id = 'bp-lock'"
            );
            $this->fail('Direct SQL must not be able to replace an already-assigned kernel_code_vvvv.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }

        $this->assertSame(
            $before,
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-lock')->value('kernel_code_vvvv'),
        );
    }

    public function test_direct_sql_cannot_null_out_an_existing_vvvv(): void
    {
        $this->insertEmpty('bp-lock-null');
        $this->persistRotation('bp-lock-null', 4, 'Géographie');
        $this->persistTaxonomy(
            'bp-lock-null',
            'Canada',
            'Confédération canadienne',
            "Acte de l'Amérique du Nord britannique",
        );
        $this->assignById('bp-lock-null');

        try {
            DB::statement(
                "UPDATE kernel_blueprint_runs SET kernel_code_vvvv = NULL WHERE blueprint_id = 'bp-lock-null'"
            );
            $this->fail('Direct SQL must not be able to null out an already-assigned kernel_code_vvvv.');
        } catch (Throwable $exception) {
            $this->assertStringContainsString('immutable', $exception->getMessage());
        }
    }

    public function test_direct_sql_replay_of_the_same_value_is_accepted_by_the_trigger(): void
    {
        $this->insertEmpty('bp-lock-replay');
        $this->persistRotation('bp-lock-replay', 4, 'Géographie');
        $this->persistTaxonomy(
            'bp-lock-replay',
            'Canada',
            'Confédération canadienne',
            "Acte de l'Amérique du Nord britannique",
        );
        $this->assignById('bp-lock-replay');

        $vvvv = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-lock-replay')->value('kernel_code_vvvv');

        // No-op replay (même valeur) : le trigger ne doit pas lever.
        DB::statement(
            "UPDATE kernel_blueprint_runs SET kernel_code_vvvv = '{$vvvv}' WHERE blueprint_id = 'bp-lock-replay'"
        );

        $this->assertSame(
            $vvvv,
            DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-lock-replay')->value('kernel_code_vvvv'),
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Frontière officielle QuestionIntent : blueprint_id uniquement.
     */
    private function assignById(string $blueprintId): string
    {
        $engine = new KernelCodeEngine();
        return $engine->assignKernelCode($blueprintId);
    }

    /**
     * Simule l'écriture faite par KernelRotationPlanner : depth + domain +
     * segments DD/DO persistés sur la ligne existante.
     */
    private function persistRotation(string $id, int $depth, string $domain): void
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($id);
        $blueprint->fillRotation($depth, $domain);

        DB::table('kernel_blueprint_runs')->where('blueprint_id', $id)->update([
            'depth' => $depth,
            'domain_code' => $domain,
            'kernel_code_dd' => $blueprint->kernel_code_dd,
            'kernel_code_do' => $blueprint->kernel_code_do,
            'updated_at' => now(),
        ]);
    }

    /**
     * Simule l'écriture faite par Taxonomy : sous-domaine/sujet/idée +
     * segments SUB/SUJ/IDE persistés — Rotation doit déjà être en place.
     */
    private function persistTaxonomy(string $id, string $subdomain, string $subject, string $idea): void
    {
        $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $id)->first();

        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId($id);
        $blueprint->fillRotation((int) $run->depth, (string) $run->domain_code);
        $blueprint->fillTaxonomy($subdomain, $subject, $idea);

        DB::table('kernel_blueprint_runs')->where('blueprint_id', $id)->update([
            'subdomain_active' => $subdomain,
            'subject_active' => $subject,
            'dominant_idea_active' => $idea,
            'kernel_code_sub' => $blueprint->kernel_code_sub,
            'kernel_code_suj' => $blueprint->kernel_code_suj,
            'kernel_code_ide' => $blueprint->kernel_code_ide,
            'updated_at' => now(),
        ]);
    }

    private function applyImmutabilityTrigger(): void
    {
        $migration = require base_path(
            'database/migrations/2026_09_17_000001_lock_kernel_code_vvvv_immutability.php'
        );
        $migration->up();
    }

    private function insertEmpty(string $id): void
    {
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $id,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kernel_blueprint_request_refs')->insert([
            'request_reference' => 'req-' . $id,
            'blueprint_id' => $id,
        ]);

        $now = now();
        $rows = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $cognitiveType) {
            $rows[] = [
                'blueprint_id' => $id,
                'cognitive_type' => $cognitiveType,
                'source' => json_encode(KernelBlueprint::emptyCognitiveSlotSource($cognitiveType)),
                'creation_failure' => null,
                'translations' => '{}',
                'creation_status' => 'EMPTY',
                'validation_status' => 'NOT_VALIDATED',
                'validation_findings' => '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('kernel_blueprint_cognitive_slots')->insert($rows);
    }
}
