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
        if (! $this->kernelCodeIsGenerated()) {
            DB::statement(
                'ALTER TABLE kernel_blueprint_runs '
                . 'ALTER COLUMN kernel_code TYPE VARCHAR(23)'
            );
        }

        DB::statement(
            'ALTER TABLE kernel_code_sequences '
            . 'ALTER COLUMN domain_code TYPE VARCHAR(3)'
        );
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM kernel_code_sequences
        WHERE char_length(domain_code) > 2
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

        if (! $this->kernelCodeIsGenerated()) {
            DB::statement(
                'ALTER TABLE kernel_blueprint_runs '
                . 'ALTER COLUMN kernel_code TYPE VARCHAR(22)'
            );
        }
    }

    private function kernelCodeIsGenerated(): bool
    {
        $column = DB::selectOne(<<<'SQL'
SELECT is_generated
FROM information_schema.columns
WHERE table_schema = current_schema()
  AND table_name = 'kernel_blueprint_runs'
  AND column_name = 'kernel_code'
SQL);

        if ($column === null) {
            throw new RuntimeException(
                'Migration DEC-121 refusée : kernel_blueprint_runs.kernel_code est absente'
            );
        }

        return $column->is_generated === 'ALWAYS';
    }
};