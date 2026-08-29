<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Fusion additive des bassins de séquence DEC-121 v2.2.
 *
 * Cette migration intervient après l'élargissement structurel en CHAR(3).
 * Elle ne touche exclusivement qu'à kernel_code_sequences et ne réécrit
 * aucun kernel_code historique.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM kernel_code_sequences
        WHERE char_length(btrim(domain_code)) = 2
          AND btrim(domain_code) NOT IN (
              'GE', 'HI', 'FA', 'AR', 'SP', 'CI', 'CU', 'SC'
          )
    ) THEN
        RAISE EXCEPTION
            'Migration DEC-121 refusée : code domaine legacy inconnu détecté';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM kernel_code_sequences
        WHERE next_value < 0
    ) THEN
        RAISE EXCEPTION
            'Migration DEC-121 refusée : next_value négatif détecté';
    END IF;

    IF EXISTS (
        SELECT 1
        FROM kernel_code_sequences
        WHERE next_value > 1679616
    ) THEN
        RAISE EXCEPTION
            'Migration DEC-121 refusée : next_value dépasse la capacité du suffixe VVVV';
    END IF;
END
$$
SQL);

            DB::statement(<<<'SQL'
WITH legacy_map(legacy_code, official_code) AS (
    VALUES
        ('GE', 'GEO'),
        ('HI', 'HIS'),
        ('FA', 'FAU'),
        ('AR', 'ART'),
        ('SP', 'SPO'),
        ('CI', 'CIN'),
        ('CU', 'CUI'),
        ('SC', 'SCI')
),
legacy_counters AS (
    SELECT
        sequence_row.depth,
        mapping.official_code,
        MAX(sequence_row.next_value) AS next_value
    FROM kernel_code_sequences AS sequence_row
    INNER JOIN legacy_map AS mapping
        ON btrim(sequence_row.domain_code) = mapping.legacy_code
    GROUP BY
        sequence_row.depth,
        mapping.official_code
)
INSERT INTO kernel_code_sequences (
    depth,
    domain_code,
    next_value,
    created_at,
    updated_at
)
SELECT
    depth,
    official_code,
    next_value,
    NOW(),
    NOW()
FROM legacy_counters
ON CONFLICT (depth, domain_code)
DO UPDATE SET
    next_value = GREATEST(
        kernel_code_sequences.next_value,
        EXCLUDED.next_value
    ),
    updated_at = NOW()
SQL);

            DB::table('kernel_code_sequences')
                ->whereIn(DB::raw('btrim(domain_code)'), [
                    'GE', 'HI', 'FA', 'AR', 'SP', 'CI', 'CU', 'SC',
                ])
                ->delete();
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Rollback DEC-121 refusé : la fusion par MAX et la suppression '
            . 'des lignes legacy sont irréversibles sans perte.'
        );
    }
};