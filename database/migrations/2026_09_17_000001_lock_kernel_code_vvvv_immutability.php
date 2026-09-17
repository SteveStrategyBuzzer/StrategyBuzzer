<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QuestionIntent — verrou d'immutabilité DB pour kernel_code_vvvv.
 *
 * L'audit du Bloc 1 a établi que la protection contre une réattribution de
 * VVVV n'existait qu'au niveau applicatif (whereNull dans KernelCodeEngine).
 * Cette migration ajoute une contrainte au niveau PostgreSQL : une fois
 * kernel_code_vvvv non NULL, toute UPDATE qui tenterait de le changer
 * (y compris vers NULL) est rejetée par un trigger, quelle que soit la voie
 * d'écriture (application, SQL direct, autre process).
 *
 * NULL → valeur : autorisé (première attribution).
 * valeur → même valeur : autorisé (rejeu idempotent, no-op).
 * valeur → autre valeur, ou valeur → NULL : rejeté.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
CREATE OR REPLACE FUNCTION kernel_blueprint_runs_lock_vvvv()
RETURNS trigger AS $$
BEGIN
    IF OLD.kernel_code_vvvv IS NOT NULL
       AND NEW.kernel_code_vvvv IS DISTINCT FROM OLD.kernel_code_vvvv THEN
        RAISE EXCEPTION
            'kernel_code_vvvv is immutable once assigned (blueprint_id=%): % -> %',
            OLD.blueprint_id, OLD.kernel_code_vvvv, NEW.kernel_code_vvvv;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);

        DB::statement(<<<'SQL'
CREATE TRIGGER kernel_blueprint_runs_lock_vvvv_trg
    BEFORE UPDATE ON kernel_blueprint_runs
    FOR EACH ROW
    EXECUTE FUNCTION kernel_blueprint_runs_lock_vvvv()
SQL);
    }

    public function down(): void
    {
        DB::statement(
            'DROP TRIGGER IF EXISTS kernel_blueprint_runs_lock_vvvv_trg ON kernel_blueprint_runs'
        );
        DB::statement('DROP FUNCTION IF EXISTS kernel_blueprint_runs_lock_vvvv()');
    }
};
