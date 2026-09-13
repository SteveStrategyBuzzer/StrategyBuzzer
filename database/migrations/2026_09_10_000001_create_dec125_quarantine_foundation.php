<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-125 persistence foundation.  This migration is additive and contains
 * no reference to kernel_pipeline_outbox.
 */
return new class extends Migration
{
    private const TYPES = [
        'QCM_RECOGNITION', 'QCM_REASONING', 'QCM_TRAP',
        'TRUE_FALSE_RECOGNITION_TRUE', 'TRUE_FALSE_RECOGNITION_FALSE',
        'TRUE_FALSE_REASONING_TRUE', 'TRUE_FALSE_REASONING_FALSE',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('kernel_blueprint_cognitive_slots', 'canonical_revision')) {
            Schema::table('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
                $table->unsignedBigInteger('canonical_revision')->default(1);
            });
        }
        DB::table('kernel_blueprint_cognitive_slots')
            ->where('canonical_revision', '<', 1)
            ->update(['canonical_revision' => 1]);

        Schema::create('kernel_quarantine_work_copies', function (Blueprint $table): void {
            $table->string('copy_id', 36)->primary();
            $table->string('blueprint_id', 36);
            $table->string('kernel_code', 23);
            $table->smallInteger('depth')->nullable();
            $table->string('domain_code', 64)->nullable();
            $table->text('subdomain_active')->nullable();
            $table->text('subject_active')->nullable();
            $table->text('dominant_idea_active')->nullable();
            $table->string('origin_phase', 64)->default('VALIDATION_PHASE1');
            $table->unsignedBigInteger('copy_version')->default(1);
            $table->string('ready_request_id', 128)->nullable();
            $table->unsignedBigInteger('ready_order')->nullable();
            $table->string('claimed_event_id', 36)->nullable();
            $table->unsignedBigInteger('claimed_version')->nullable();
            $table->string('claim_token', 128)->nullable();
            $table->string('state', 16)->default('EDITABLE');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'ready_order'], 'kqwc_fifo_idx');
            $table->unique('ready_request_id', 'kqwc_request_unique');
            $table->foreign('blueprint_id', 'kqwc_blueprint_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        Schema::create('kernel_quarantine_work_copy_slots', function (Blueprint $table): void {
            $table->string('copy_id', 36);
            $table->string('cognitive_type', 64);
            $table->unsignedBigInteger('canonical_base_revision')->default(1);
            $table->unsignedBigInteger('slot_revision')->default(1);
            $table->unsignedBigInteger('manual_revision')->default(0);
            $table->string('color', 8)->default('RED');
            $table->jsonb('source');
            $table->jsonb('creation_failure')->nullable();
            $table->jsonb('translations')->default('{}');
            $table->string('creation_status', 32)->default('EMPTY');
            $table->string('validation_status', 32)->default('NOT_VALIDATED');
            $table->jsonb('validation_findings')->default('[]');
            $table->boolean('manually_modified')->default(false);
            $table->timestamps();
            $table->primary(['copy_id', 'cognitive_type'], 'kqcs_copy_type_pk');
            $table->foreign('copy_id', 'kqcs_copy_fk')
                ->references('copy_id')->on('kernel_quarantine_work_copies')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('kernel_quarantine_slot_resumptions', function (Blueprint $table): void {
            $table->string('blueprint_id', 36);
            $table->string('copy_id', 36);
            $table->string('cognitive_type', 64);
            $table->unsignedBigInteger('resumption_number')->default(1);
            $table->unsignedBigInteger('copy_version');
            $table->unsignedBigInteger('manual_revision');
            $table->boolean('phase1_remaining')->default(true);
            $table->boolean('phase1_creation_required')->default(false);
            $table->boolean('validation_phase1_remaining')->default(true);
            $table->boolean('phase2_remaining')->default(false);
            $table->boolean('validation_phase2_remaining')->default(false);
            $table->string('current_stage', 64)->default('PHASE1');
            $table->string('state', 16)->default('ACTIVE');
            $table->string('outcome', 32)->nullable();
            $table->timestamps();
            $table->primary(['copy_id', 'cognitive_type'], 'kqsr_copy_type_pk');
            $table->foreign(['copy_id', 'cognitive_type'], 'kqsr_slot_fk')
                ->references(['copy_id', 'cognitive_type'])
                ->on('kernel_quarantine_work_copy_slots')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('blueprint_id', 'kqsr_blueprint_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        // Singleton arbitration gate.  Decisions belong to dispatches, not to
        // this row; this prevents one event's decision from becoming stale.
        Schema::create('kernel_current_kernel_route_gate', function (Blueprint $table): void {
            $table->unsignedTinyInteger('gate_id')->default(1);
            $table->string('active_copy_id', 36)->nullable();
            $table->unsignedBigInteger('active_copy_version')->nullable();
            $table->string('active_claim_token', 128)->nullable();
            $table->timestamps();
            $table->primary('gate_id');
            $table->foreign('active_copy_id', 'kcrg_copy_fk')
                ->references('copy_id')->on('kernel_quarantine_work_copies')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('kernel_current_kernel_dispatches', function (Blueprint $table): void {
            $table->string('event_id', 36)->primary();
            $table->string('blueprint_id', 36);
            $table->string('direction', 16);
            $table->string('copy_id', 36)->nullable();
            $table->unsignedBigInteger('copy_version')->nullable();
            $table->unsignedBigInteger('ready_order')->nullable();
            $table->string('claim_token', 128)->nullable();
            $table->string('state', 16)->default('READY');
            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();
            $table->index(['state', 'ready_order'], 'kckd_fifo_idx');
            $table->foreign('blueprint_id', 'kckd_blueprint_fk')
                ->references('blueprint_id')->on('kernel_blueprint_runs')
                ->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('copy_id', 'kckd_copy_fk')
                ->references('copy_id')->on('kernel_quarantine_work_copies')
                ->nullOnDelete()->cascadeOnUpdate();
        });
        DB::table('kernel_current_kernel_route_gate')->insert([
            'gate_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // SQLite has no deferred constraint triggers; repositories perform the
        // same checks there.  Its FK is still rebuilt with ON UPDATE CASCADE.
        if (DB::getDriverName() !== 'pgsql') {
            $this->rebuildSqliteCanonicalForeignKey();
            return;
        }

        $schema = (string) DB::selectOne('SELECT current_schema() AS schema_name')->schema_name;
        $qschema = '"' . str_replace('"', '""', $schema) . '"';
        $revisionFn = $qschema . '."dec125_bump_canonical_revision"';
        $sevenFn = $qschema . '."dec125_assert_quarantine_seven"';

        DB::statement("CREATE OR REPLACE FUNCTION {$revisionFn}() RETURNS trigger
            LANGUAGE plpgsql AS \$\$
            BEGIN
              IF (NEW.source, NEW.creation_failure, NEW.translations,
                  NEW.creation_status, NEW.validation_status, NEW.validation_findings)
                 IS DISTINCT FROM
                 (OLD.source, OLD.creation_failure, OLD.translations,
                  OLD.creation_status, OLD.validation_status, OLD.validation_findings) THEN
                IF NEW.canonical_revision IS DISTINCT FROM OLD.canonical_revision THEN
                  RAISE EXCEPTION 'canonical_revision is trigger managed';
                END IF;
                NEW.canonical_revision := OLD.canonical_revision + 1;
              ELSIF NEW.canonical_revision IS DISTINCT FROM OLD.canonical_revision THEN
                RAISE EXCEPTION 'canonical_revision cannot be changed alone';
              END IF;
              RETURN NEW;
            END;
            \$\$");
        DB::statement('DROP TRIGGER IF EXISTS dec125_canonical_revision ON kernel_blueprint_cognitive_slots');
        DB::statement("CREATE TRIGGER dec125_canonical_revision
            BEFORE UPDATE ON kernel_blueprint_cognitive_slots
            FOR EACH ROW EXECUTE FUNCTION {$revisionFn}()");

        DB::statement("ALTER TABLE kernel_quarantine_work_copy_slots
            ADD CONSTRAINT kqcs_cognitive_type_check CHECK (cognitive_type IN (
              'QCM_RECOGNITION','QCM_REASONING','QCM_TRAP',
              'TRUE_FALSE_RECOGNITION_TRUE','TRUE_FALSE_RECOGNITION_FALSE',
              'TRUE_FALSE_REASONING_TRUE','TRUE_FALSE_REASONING_FALSE'))");
        DB::statement("ALTER TABLE kernel_quarantine_work_copies
            ADD CONSTRAINT kqwc_state_check CHECK (state IN ('EDITABLE','READY','IN_FLIGHT'))");
        DB::statement("ALTER TABLE kernel_current_kernel_dispatches
            ADD CONSTRAINT kckd_direction_check CHECK (direction IN ('QUARANTINE','KBP'))");
        DB::statement("ALTER TABLE kernel_quarantine_work_copy_slots
            ADD CONSTRAINT kqcs_color_check CHECK (color IN ('RED','YELLOW','GREEN'))");
        DB::statement("ALTER TABLE kernel_quarantine_slot_resumptions
            ADD CONSTRAINT kqsr_state_check CHECK (state IN ('ACTIVE','TERMINATED'))");
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS kqwc_one_in_flight_idx
            ON kernel_quarantine_work_copies ((state)) WHERE state = 'IN_FLIGHT'");

        DB::statement("CREATE OR REPLACE FUNCTION {$sevenFn}() RETURNS trigger
            LANGUAGE plpgsql AS \$\$
            DECLARE n integer; id varchar;
            BEGIN
              id := COALESCE(NEW.copy_id, OLD.copy_id);
              IF id IS NULL THEN RETURN NULL; END IF;
              IF NOT EXISTS (SELECT 1 FROM kernel_quarantine_work_copies WHERE copy_id = id)
                THEN RETURN NULL; END IF;
              SELECT count(*) INTO n FROM kernel_quarantine_work_copy_slots WHERE copy_id = id;
              IF n <> 7 THEN
                RAISE EXCEPTION 'Quarantine copy must have exactly seven cognitive slots (got %)', n;
              END IF;
              RETURN NULL;
            END;
            \$\$");
        DB::statement('DROP TRIGGER IF EXISTS dec125_quarantine_seven_parent ON kernel_quarantine_work_copies');
        DB::statement("CREATE CONSTRAINT TRIGGER dec125_quarantine_seven_parent
            AFTER INSERT OR UPDATE ON kernel_quarantine_work_copies
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION {$sevenFn}()");
        DB::statement('DROP TRIGGER IF EXISTS dec125_quarantine_seven_slots ON kernel_quarantine_work_copy_slots');
        DB::statement("CREATE CONSTRAINT TRIGGER dec125_quarantine_seven_slots
            AFTER INSERT OR UPDATE OR DELETE ON kernel_quarantine_work_copy_slots
            DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION {$sevenFn}()");

        DB::statement('ALTER TABLE kernel_blueprint_cognitive_slots DROP CONSTRAINT IF EXISTS kbcs_blueprint_id_fk');
        DB::statement('ALTER TABLE kernel_blueprint_cognitive_slots ADD CONSTRAINT kbcs_blueprint_id_fk
            FOREIGN KEY (blueprint_id) REFERENCES kernel_blueprint_runs(blueprint_id)
            ON DELETE CASCADE ON UPDATE CASCADE');
        DB::statement("CREATE SEQUENCE IF NOT EXISTS {$qschema}.kernel_quarantine_ready_order_seq");
    }

    private function rebuildSqliteCanonicalForeignKey(): void
    {
        // Laravel's SQLite grammar intentionally refuses dropForeign.  Build
        // the same table from PRAGMA metadata so additive upgrades retain
        // columns introduced by later migrations while changing only this FK.
        DB::statement('PRAGMA foreign_keys = OFF');
        $columns = DB::select("PRAGMA table_info('kernel_blueprint_cognitive_slots')");
        $definitions = [];
        $primary = [];
        foreach ($columns as $column) {
            $name = '"' . str_replace('"', '""', (string) $column->name) . '"';
            $definition = $name . ' ' . ($column->type ?: 'TEXT');
            if ((int) $column->notnull === 1) {
                $definition .= ' NOT NULL';
            }
            if ($column->dflt_value !== null) {
                $definition .= ' DEFAULT ' . $column->dflt_value;
            }
            $definitions[] = $definition;
            if ((int) $column->pk > 0) {
                $primary[(int) $column->pk] = $name;
            }
        }
        ksort($primary);
        $definitions[] = 'PRIMARY KEY (' . implode(', ', $primary) . ')';
        DB::statement('ALTER TABLE kernel_blueprint_cognitive_slots RENAME TO kernel_blueprint_cognitive_slots_dec125_old');
        DB::statement('CREATE TABLE kernel_blueprint_cognitive_slots ('
            . implode(', ', $definitions)
            . ', CONSTRAINT kbcs_blueprint_id_fk FOREIGN KEY ("blueprint_id") '
            . 'REFERENCES "kernel_blueprint_runs" ("blueprint_id") '
            . 'ON DELETE CASCADE ON UPDATE CASCADE)');
        $names = array_map(static fn (object $column): string => '"' . str_replace('"', '""', (string) $column->name) . '"', $columns);
        DB::statement('INSERT INTO kernel_blueprint_cognitive_slots ('
            . implode(', ', $names) . ') SELECT ' . implode(', ', $names)
            . ' FROM kernel_blueprint_cognitive_slots_dec125_old');
        DB::statement('DROP TABLE kernel_blueprint_cognitive_slots_dec125_old');
        DB::statement('PRAGMA foreign_keys = ON');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS dec125_canonical_revision ON kernel_blueprint_cognitive_slots');
            DB::statement('DROP TRIGGER IF EXISTS dec125_quarantine_seven_parent ON kernel_quarantine_work_copies');
            DB::statement('DROP TRIGGER IF EXISTS dec125_quarantine_seven_slots ON kernel_quarantine_work_copy_slots');
            DB::statement('DROP INDEX IF EXISTS kqwc_one_in_flight_idx');
            $schema = (string) DB::selectOne('SELECT current_schema() AS schema_name')->schema_name;
            $quoted = '"' . str_replace('"', '""', $schema) . '"';
            DB::statement("DROP SEQUENCE IF EXISTS {$quoted}.kernel_quarantine_ready_order_seq");
        }
        Schema::dropIfExists('kernel_current_kernel_dispatches');
        Schema::dropIfExists('kernel_current_kernel_route_gate');
        Schema::dropIfExists('kernel_quarantine_slot_resumptions');
        Schema::dropIfExists('kernel_quarantine_work_copy_slots');
        Schema::dropIfExists('kernel_quarantine_work_copies');
        if (Schema::hasColumn('kernel_blueprint_cognitive_slots', 'canonical_revision')) {
            Schema::table('kernel_blueprint_cognitive_slots', function (Blueprint $table): void {
                $table->dropColumn('canonical_revision');
            });
        }
    }
};