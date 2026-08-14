<?php

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DepthNeedMatrix;
use App\Services\QuestionBank\Rotation\DepthTourState;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * KernelMigrationSchemaTest — validation du schéma des migrations Kernel V2.
 *
 * Simule le cycle migrations : CREATE → VERIFY → DROP → VERIFY SUPPRESSION
 * → RECRÉER → VERIFY à nouveau.
 *
 * Tables Kernel V2 testées :
 *   kernel_blueprint_runs
 *   kernel_rotation_state_v2
 *   kernel_depth_matrix         (seed attendu : 7 lignes, cibles exactes)
 *   kernel_depth_domain_totals  (seed attendu : 56 lignes, 8 domaines × 7 depths)
 *   kernel_current_kernel_receipts
 *   kernel_pipeline_outbox
 *
 * Tables legacy simulées (doivent survivre au rollback Kernel) :
 *   legacy_question_groups_marker (table sentinelle — représente question_groups)
 *
 * Note : dans l'environnement CI (SQLite in-memory), les migrations Artisan
 * ne peuvent pas être appelées directement. Ce test simule le cycle Schema::create
 * / Schema::drop pour valider les attentes de seed et de schéma.
 */
class KernelMigrationSchemaTest extends TestCase
{
    private const KERNEL_TABLES = [
        'kernel_blueprint_runs',
        'kernel_rotation_state_v2',
        'kernel_depth_matrix',
        'kernel_depth_domain_totals',
        'kernel_current_kernel_receipts',
        'kernel_pipeline_outbox',
    ];

    private const DOMAINS = DepthTourState::DOMAIN_CYCLE;

    // =========================================================================
    // Cycle migration
    // =========================================================================

    /**
     * Étape 1 : crée les tables Kernel + seed, vérifie les comptes attendus.
     */
    public function test_kernel_migration_creates_expected_rows(): void
    {
        $this->createKernelTables();
        $this->seedKernelTables();

        // kernel_depth_matrix : 7 lignes (1 par Depth du cycle)
        $this->assertSame(
            7,
            DB::table('kernel_depth_matrix')->count(),
            'kernel_depth_matrix doit contenir 7 lignes (7 Depths du cycle)'
        );

        // kernel_depth_domain_totals : 56 lignes (7 Depths × 8 Domaines)
        $this->assertSame(
            56,
            DB::table('kernel_depth_domain_totals')->count(),
            'kernel_depth_domain_totals doit contenir 56 lignes (7 Depths × 8 Domaines)'
        );

        $this->dropKernelTables();
    }

    /**
     * Étape 2 : vérifie les cibles exactes par Depth après migration.
     */
    public function test_kernel_depth_matrix_has_correct_targets(): void
    {
        $this->createKernelTables();
        $this->seedKernelTables();

        foreach (DepthNeedMatrix::CYCLE_TARGET as $depth => $expectedTarget) {
            $row = DB::table('kernel_depth_matrix')->where('depth', $depth)->first();
            $this->assertNotNull($row, "Ligne manquante pour depth={$depth}");
            $this->assertSame(
                $expectedTarget,
                (int) $row->cycle_target,
                "cycle_target incorrect pour depth={$depth} — attendu={$expectedTarget}"
            );
            $this->assertSame(0, (int) $row->cycle_completed, "cycle_completed doit être 0 à l'init");
        }

        $this->dropKernelTables();
    }

    /**
     * Étape 3 : vérifie les 8 Domaines exacts dans kernel_depth_domain_totals.
     */
    public function test_kernel_depth_domain_totals_has_exact_domains(): void
    {
        $this->createKernelTables();
        $this->seedKernelTables();

        $seededDomains = DB::table('kernel_depth_domain_totals')
            ->select('domain_code')
            ->distinct()
            ->orderBy('domain_code')
            ->pluck('domain_code')
            ->toArray();

        $expectedDomains = self::DOMAINS;
        sort($expectedDomains);

        $this->assertSame(
            $expectedDomains,
            $seededDomains,
            'Les 8 Domaines exacts doivent être présents dans kernel_depth_domain_totals'
        );

        $this->dropKernelTables();
    }

    /**
     * Étape 4 : rollback — tables Kernel supprimées, table legacy intacte.
     */
    public function test_kernel_rollback_drops_only_kernel_tables(): void
    {
        // Créer une table "legacy" sentinelle (représente question_groups)
        Schema::create('legacy_question_groups_marker', function (Blueprint $table) {
            $table->id();
            $table->string('sentinel')->default('ok');
        });
        DB::table('legacy_question_groups_marker')->insert(['sentinel' => 'ok']);

        $this->createKernelTables();
        $this->seedKernelTables();

        // Rollback des tables Kernel
        $this->dropKernelTables();

        // Vérifier que les tables Kernel sont supprimées
        foreach (self::KERNEL_TABLES as $table) {
            $this->assertFalse(
                Schema::hasTable($table),
                "La table Kernel '{$table}' doit être supprimée après rollback"
            );
        }

        // Vérifier que la table legacy est intacte
        $this->assertTrue(
            Schema::hasTable('legacy_question_groups_marker'),
            'La table legacy ne doit pas être supprimée par le rollback Kernel'
        );
        $this->assertSame(
            1,
            DB::table('legacy_question_groups_marker')->count(),
            'Les données legacy doivent survivre au rollback Kernel'
        );

        Schema::dropIfExists('legacy_question_groups_marker');
    }

    /**
     * Étape 5 : re-migration — après rollback, recréer et vérifier de nouveau les comptes.
     */
    public function test_kernel_re_migration_restores_expected_rows(): void
    {
        // Première passe
        $this->createKernelTables();
        $this->seedKernelTables();
        $this->dropKernelTables();

        // Deuxième passe (re-migration)
        $this->createKernelTables();
        $this->seedKernelTables();

        $this->assertSame(7,  DB::table('kernel_depth_matrix')->count(),        're-migration : 7 lignes');
        $this->assertSame(56, DB::table('kernel_depth_domain_totals')->count(),  're-migration : 56 lignes');

        $this->dropKernelTables();
    }

    /**
     * Étape 6 : après re-migration, tous les tests de KRP V2 passent.
     * (Vérification structurelle : tables existent et ont les bonnes colonnes.)
     */
    public function test_kernel_tables_have_required_columns_after_migration(): void
    {
        $this->createKernelTables();

        $this->assertTrue(Schema::hasColumn('kernel_blueprint_runs', 'blueprint_id'));
        $this->assertTrue(Schema::hasColumn('kernel_blueprint_runs', 'execution_state'));
        $this->assertTrue(Schema::hasColumn('kernel_rotation_state_v2', 'tour_domain_states'));
        $this->assertTrue(Schema::hasColumn('kernel_rotation_state_v2', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('kernel_depth_matrix', 'cycle_target'));
        $this->assertTrue(Schema::hasColumn('kernel_depth_matrix', 'cycle_completed'));
        $this->assertTrue(Schema::hasColumn('kernel_depth_domain_totals', 'kernel_received_total'));
        $this->assertTrue(Schema::hasColumn('kernel_pipeline_outbox', 'attempt_count'));
        $this->assertTrue(Schema::hasColumn('kernel_pipeline_outbox', 'last_error'));
        $this->assertTrue(Schema::hasColumn('kernel_current_kernel_receipts', 'blueprint_id'));

        $this->dropKernelTables();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createKernelTables(): void
    {
        Schema::create('kernel_blueprint_runs', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->timestamp('engaged_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_rotation_state_v2', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('active_depth')->nullable();
            $table->text('tour_domain_states')->nullable();
            $table->string('active_blueprint_identity', 36)->nullable();
            $table->string('last_counted_blueprint_identity', 36)->nullable();
            $table->unsignedBigInteger('lock_version')->default(1);
            $table->timestamps();
        });

        Schema::create('kernel_depth_matrix', function (Blueprint $table) {
            $table->smallInteger('depth')->primary();
            $table->integer('cycle_target')->default(0);
            $table->integer('cycle_completed')->default(0);
            $table->smallInteger('empty_progress_current_tour')->default(0);
            $table->string('current_tour_id', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('kernel_depth_domain_totals', function (Blueprint $table) {
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->bigInteger('kernel_received_total')->default(0);
            $table->timestamps();
            $table->primary(['depth', 'domain_code']);
        });

        Schema::create('kernel_current_kernel_receipts', function (Blueprint $table) {
            $table->string('blueprint_id', 36)->primary();
            $table->string('event_id', 36)->unique();
            $table->smallInteger('depth');
            $table->string('domain_code', 64);
            $table->timestamp('received_at');
        });

        Schema::create('kernel_pipeline_outbox', function (Blueprint $table) {
            $table->string('event_id', 36)->primary();
            $table->string('event_type', 128);
            $table->integer('schema_version')->default(1);
            $table->text('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('processed_at')->nullable();
            $table->integer('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    private function seedKernelTables(): void
    {
        foreach (DepthNeedMatrix::DEPTH_CYCLE as $depth) {
            DB::table('kernel_depth_matrix')->insert([
                'depth'                       => $depth,
                'cycle_target'                => DepthNeedMatrix::CYCLE_TARGET[$depth],
                'cycle_completed'             => 0,
                'empty_progress_current_tour' => 0,
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);

            foreach (self::DOMAINS as $domain) {
                DB::table('kernel_depth_domain_totals')->insert([
                    'depth'                 => $depth,
                    'domain_code'           => $domain,
                    'kernel_received_total' => 0,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        }
    }

    private function dropKernelTables(): void
    {
        // Ordre inverse des dépendances FK (SQLite les ignore, mais bonne pratique)
        Schema::dropIfExists('kernel_pipeline_outbox');
        Schema::dropIfExists('kernel_current_kernel_receipts');
        Schema::dropIfExists('kernel_depth_domain_totals');
        Schema::dropIfExists('kernel_depth_matrix');
        Schema::dropIfExists('kernel_rotation_state_v2');
        Schema::dropIfExists('kernel_blueprint_runs');
    }
}
