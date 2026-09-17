<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #182 — PostgreSQL guards for identity-bearing question-bank tables.
 *
 * This migration is deliberately separate from the legacy-key conversion.
 * It constrains only columns that carry canonical creator identity.
 */
return new class extends Migration
{
    private const DEPTHS = '2, 4, 6, 7, 8, 9, 10';
    private const DEPTH_CODES = "'02', '04', '06', '07', '08', '09', '10'";
    private const DOMAINS = "'GEO', 'HIS', 'FAU', 'ART', 'SPO', 'CIN', 'CUI', 'SCI'";

    public function up(): void
    {
        DB::transaction(function (): void {
            $this->assertTablesExist();
            $this->assertExistingRowsAreCanonical();
            $this->assertConstraintNamesAreAvailable();
            $this->addConstraints();
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            foreach ($this->constraints() as $table => $constraints) {
                foreach ($constraints as $constraint) {
                    DB::statement(sprintf(
                        'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                        $table,
                        $constraint
                    ));
                }
            }
        });
    }

    private function assertTablesExist(): void
    {
        foreach (array_keys($this->constraints()) as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Contraintes #182 interrompues : table {$table} absente."
                );
            }
        }
    }

    private function assertExistingRowsAreCanonical(): void
    {
        $checks = [
            [
                'table' => 'kernel_depth_domain_totals',
                'where' => "(depth NOT IN (" . self::DEPTHS . ')'
                    . " OR domain_code NOT IN (" . self::DOMAINS . ')'
                    . ' OR kernel_received_total < 0)',
                'identity' => "depth, domain_code, kernel_received_total",
            ],
            [
                'table' => 'kernel_blueprint_runs',
                'where' => '(depth IS NOT NULL AND depth NOT IN (' . self::DEPTHS . ')'
                    . ' OR domain_code IS NOT NULL AND domain_code NOT IN (' . self::DOMAINS . ')'
                    . ' OR kernel_code_dd IS NOT NULL AND kernel_code_dd NOT IN ('
                    . self::DEPTH_CODES . ')'
                    . ' OR kernel_code_do IS NOT NULL AND kernel_code_do NOT IN ('
                    . self::DOMAINS . '))',
                'identity' => "blueprint_id, depth, domain_code, kernel_code_dd, kernel_code_do",
            ],
            [
                'table' => 'kernel_code_sequences',
                'where' => '(depth NOT IN (' . self::DEPTHS . ')'
                    . ' OR domain_code NOT IN (' . self::DOMAINS . ')'
                    . ' OR next_value < 0)',
                'identity' => "depth, domain_code, next_value",
            ],
            [
                'table' => 'taxonomy_v11_occurrences',
                'where' => '(depth NOT IN (' . self::DEPTHS . ')'
                    . ' OR domain_code NOT IN (' . self::DOMAINS . '))',
                'identity' => "id, depth, domain_code",
            ],
            [
                'table' => 'taxonomy_v11_terminal_facts',
                'where' => '(depth NOT IN (' . self::DEPTHS . ')'
                    . ' OR domain_code NOT IN (' . self::DOMAINS . '))',
                'identity' => "id, depth, domain_code",
            ],
            [
                'table' => 'taxonomy_v11_blueprint_assignments',
                'where' => '(depth NOT IN (' . self::DEPTHS . ')'
                    . ' OR domain_code NOT IN (' . self::DOMAINS . '))',
                'identity' => "blueprint_id, depth, domain_code",
            ],
        ];

        foreach ($checks as $check) {
            $row = DB::selectOne(
                'SELECT ' . $check['identity']
                . ' FROM ' . $check['table']
                . ' WHERE ' . $check['where'] . ' LIMIT 1'
            );

            if ($row !== null) {
                throw new RuntimeException(
                    "Contraintes #182 interrompues : valeur d'identité invalide "
                    . "dans {$check['table']} : " . json_encode($row, JSON_THROW_ON_ERROR)
                );
            }
        }
    }

    private function addConstraints(): void
    {
        foreach ($this->constraintSql() as $table => $constraints) {
            foreach ($constraints as $name => $expression) {
                DB::statement(
                    "ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})"
                );
            }
        }
    }

    private function assertConstraintNamesAreAvailable(): void
    {
        foreach ($this->constraints() as $table => $constraints) {
            foreach ($constraints as $constraint) {
                $existing = DB::selectOne(
                    'SELECT c.conname, n.nspname, t.relname '
                    . 'FROM pg_constraint c '
                    . 'JOIN pg_class t ON t.oid = c.conrelid '
                    . 'JOIN pg_namespace n ON n.oid = t.relnamespace '
                    . 'WHERE c.conname = ? LIMIT 1',
                    [$constraint]
                );

                if ($existing !== null) {
                    throw new RuntimeException(
                        "Contraintes #182 interrompues : collision {$constraint} "
                        . "déjà présente sur {$existing->nspname}.{$existing->relname}; "
                        . "cible attendue {$table}."
                    );
                }
            }
        }
    }

    /** @return array<string, string[]> */
    private function constraints(): array
    {
        $result = [];
        foreach ($this->constraintSql() as $table => $constraints) {
            $result[$table] = array_keys($constraints);
        }

        return $result;
    }

    /** @return array<string, array<string, string>> */
    private function constraintSql(): array
    {
        return [
            'kernel_depth_domain_totals' => [
                'kddt_182_depth_chk' => 'depth IN (' . self::DEPTHS . ')',
                'kddt_182_domain_chk' => 'domain_code IN (' . self::DOMAINS . ')',
                'kddt_182_received_nonnegative_chk' => 'kernel_received_total >= 0',
            ],
            'kernel_blueprint_runs' => [
                'kbr_182_depth_chk' => 'depth IS NULL OR depth IN (' . self::DEPTHS . ')',
                'kbr_182_domain_chk' => 'domain_code IS NULL OR domain_code IN (' . self::DOMAINS . ')',
                'kbr_182_depth_segment_chk' => 'kernel_code_dd IS NULL OR kernel_code_dd IN ('
                    . self::DEPTH_CODES . ')',
                'kbr_182_domain_segment_chk' => 'kernel_code_do IS NULL OR kernel_code_do IN ('
                    . self::DOMAINS . ')',
            ],
            'kernel_code_sequences' => [
                'kcs_182_depth_chk' => 'depth IN (' . self::DEPTHS . ')',
                'kcs_182_domain_chk' => 'domain_code IN (' . self::DOMAINS . ')',
                'kcs_182_next_nonnegative_chk' => 'next_value >= 0',
            ],
            'taxonomy_v11_occurrences' => [
                'tvo_182_depth_chk' => 'depth IN (' . self::DEPTHS . ')',
                'tvo_182_domain_chk' => 'domain_code IN (' . self::DOMAINS . ')',
            ],
            'taxonomy_v11_terminal_facts' => [
                'tvf_182_depth_chk' => 'depth IN (' . self::DEPTHS . ')',
                'tvf_182_domain_chk' => 'domain_code IN (' . self::DOMAINS . ')',
            ],
            'taxonomy_v11_blueprint_assignments' => [
                'tvb_182_depth_chk' => 'depth IN (' . self::DEPTHS . ')',
                'tvb_182_domain_chk' => 'domain_code IN (' . self::DOMAINS . ')',
            ],
        ];
    }
};