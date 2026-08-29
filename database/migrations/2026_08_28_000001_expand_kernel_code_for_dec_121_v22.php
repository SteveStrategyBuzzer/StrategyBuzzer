<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Élargissement structurel DEC-121 v2.2.
 *
 * Aucune donnée legacy n'est réécrite : les anciens codes de 22 caractères et
 * les anciens bassins domaine de 2 caractères restent inchangés.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE kernel_blueprint_runs '
            . 'ALTER COLUMN kernel_code TYPE VARCHAR(23)'
        );

        DB::statement(
            'ALTER TABLE kernel_code_sequences '
            . 'ALTER COLUMN domain_code TYPE CHAR(3)'
        );
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM kernel_blueprint_runs
        WHERE kernel_code IS NOT NULL
          AND char_length(kernel_code) > 22
    ) THEN
        RAISE EXCEPTION 'Rollback DEC-121 v2.2 refusé : kernel_code contient des valeurs de 23 caractères';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM kernel_code_sequences
        WHERE char_length(trim(trailing FROM domain_code)) > 2
    ) THEN
        RAISE EXCEPTION 'Rollback DEC-121 v2.2 refusé : domain_code contient des valeurs de 3 caractères';
    END IF;
END
$$
SQL);

        DB::statement(
            'ALTER TABLE kernel_code_sequences '
            . 'ALTER COLUMN domain_code TYPE CHAR(2)'
        );

        DB::statement(
            'ALTER TABLE kernel_blueprint_runs '
            . 'ALTER COLUMN kernel_code TYPE VARCHAR(22)'
        );
    }
};