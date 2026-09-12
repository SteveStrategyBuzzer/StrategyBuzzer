<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Authorizes the persistent, phased KernelBlueprint contract.
 *
 * This migration intentionally does not backfill or create a replacement
 * table. A historical writable kernel_code makes the transition ambiguous, so
 * the guard must run before the first DDL statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM kernel_blueprint_runs
        WHERE kernel_code IS NOT NULL
    ) THEN
        RAISE EXCEPTION
            'Phased KernelBlueprint migration aborted: existing kernel_code values require an explicit cutover';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM kernel_blueprint_runs
        WHERE (
            (depth IS NOT NULL)::integer
          + (domain_code IS NOT NULL)::integer
          ) > 0
    ) THEN
        RAISE EXCEPTION
            'Phased KernelBlueprint migration aborted: partial Rotation fields require an explicit cutover';
    END IF;
END
$$
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE kernel_blueprint_runs
    DROP COLUMN kernel_code,
    ADD COLUMN subdomain_active TEXT NULL,
    ADD COLUMN subject_active TEXT NULL,
    ADD COLUMN dominant_idea_active TEXT NULL,
    ADD COLUMN kernel_code_dd VARCHAR(2) NULL,
    ADD COLUMN kernel_code_do VARCHAR(3) NULL,
    ADD COLUMN kernel_code_sub VARCHAR(3) NULL,
    ADD COLUMN kernel_code_suj VARCHAR(3) NULL,
    ADD COLUMN kernel_code_ide VARCHAR(3) NULL,
    ADD COLUMN kernel_code_vvvv VARCHAR(4) NULL,
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

        DB::statement(<<<'SQL'
ALTER TABLE kernel_blueprint_runs
    ADD CONSTRAINT kernel_blueprint_runs_execution_state_chk
        CHECK (execution_state IN (
            'CREATED_UNENGAGED',
            'ENGAGED_IN_PIPELINE',
            'READY_BANK_RECEIVED',
            'NOT_ENGAGED_PRODUCTION_ON_HOLD'
        )),
    ADD CONSTRAINT kernel_blueprint_runs_rotation_progress_chk
        CHECK (
            (depth IS NULL AND domain_code IS NULL
                AND kernel_code_dd IS NULL AND kernel_code_do IS NULL)
            OR
            (depth IS NOT NULL AND domain_code IS NOT NULL
                AND kernel_code_dd IS NOT NULL AND kernel_code_do IS NOT NULL)
        ),
    ADD CONSTRAINT kernel_blueprint_runs_rotation_format_chk
        CHECK (
            (kernel_code_dd IS NULL OR kernel_code_dd ~ '^[0-9]{2}$')
            AND (kernel_code_do IS NULL OR kernel_code_do ~ '^[A-Z0-9]{3}$')
        ),
    ADD CONSTRAINT kernel_blueprint_runs_taxonomy_progress_chk
        CHECK (
            (subdomain_active IS NULL AND subject_active IS NULL AND dominant_idea_active IS NULL)
            OR
            (subdomain_active IS NOT NULL AND subject_active IS NOT NULL AND dominant_idea_active IS NOT NULL)
        ),
    ADD CONSTRAINT kernel_blueprint_runs_taxonomy_nonempty_chk
        CHECK (
            (subdomain_active IS NULL OR btrim(subdomain_active) <> '')
            AND (subject_active IS NULL OR btrim(subject_active) <> '')
            AND (dominant_idea_active IS NULL OR btrim(dominant_idea_active) <> '')
        ),
    ADD CONSTRAINT kernel_blueprint_runs_segment_progress_chk
        CHECK (
            (kernel_code_sub IS NULL AND kernel_code_suj IS NULL AND kernel_code_ide IS NULL)
            OR
            (kernel_code_sub IS NOT NULL AND kernel_code_suj IS NOT NULL
                AND kernel_code_ide IS NOT NULL)
        ),
    ADD CONSTRAINT kernel_blueprint_runs_segment_format_chk
        CHECK (
            (kernel_code_sub IS NULL OR kernel_code_sub ~ '^[A-Z0-9]{3}$')
            AND (kernel_code_suj IS NULL OR kernel_code_suj ~ '^[A-Z0-9]{3}$')
            AND (kernel_code_ide IS NULL OR kernel_code_ide ~ '^[A-Z0-9]{3}$')
            AND (kernel_code_vvvv IS NULL OR kernel_code_vvvv ~ '^[0-9A-Z]{4}$')
        ),
    ADD CONSTRAINT kernel_blueprint_runs_taxonomy_segment_ownership_chk
        CHECK (
            (subdomain_active IS NULL AND subject_active IS NULL AND dominant_idea_active IS NULL
                AND kernel_code_sub IS NULL AND kernel_code_suj IS NULL
                AND kernel_code_ide IS NULL AND kernel_code_vvvv IS NULL)
            OR
            (subdomain_active IS NOT NULL AND subject_active IS NOT NULL AND dominant_idea_active IS NOT NULL
                AND kernel_code_sub IS NOT NULL AND kernel_code_suj IS NOT NULL
                AND kernel_code_ide IS NOT NULL)
        )
SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
ALTER TABLE kernel_blueprint_runs
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_taxonomy_segment_ownership_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_segment_format_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_segment_progress_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_taxonomy_nonempty_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_taxonomy_progress_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_rotation_format_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_rotation_progress_chk,
    DROP CONSTRAINT IF EXISTS kernel_blueprint_runs_execution_state_chk
SQL);
        DB::statement('DROP INDEX IF EXISTS kernel_blueprint_runs_kernel_code_unique');
        DB::statement(<<<'SQL'
ALTER TABLE kernel_blueprint_runs
    DROP COLUMN IF EXISTS kernel_code,
    DROP COLUMN IF EXISTS kernel_code_vvvv,
    DROP COLUMN IF EXISTS kernel_code_ide,
    DROP COLUMN IF EXISTS kernel_code_suj,
    DROP COLUMN IF EXISTS kernel_code_sub,
    DROP COLUMN IF EXISTS kernel_code_do,
    DROP COLUMN IF EXISTS kernel_code_dd,
    DROP COLUMN IF EXISTS dominant_idea_active,
    DROP COLUMN IF EXISTS subject_active,
    DROP COLUMN IF EXISTS subdomain_active,
    ADD COLUMN kernel_code VARCHAR(23) NULL
SQL);
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX kernel_blueprint_runs_kernel_code_unique
    ON kernel_blueprint_runs (kernel_code)
    WHERE kernel_code IS NOT NULL
SQL);
    }
};