<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Architecture;

use Tests\TestCase;

final class CreatorIdentityConstraintsMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__
        . '/../../../../database/migrations/2026_09_11_000002_add_creator_identity_constraints.php';

    public function test_migration_is_postgres_transactional_and_reversible(): void
    {
        $source = file_get_contents(self::MIGRATION);
        self::assertIsString($source);
        self::assertStringContainsString('DB::transaction', $source);
        self::assertStringContainsString('pg_constraint', $source);
        self::assertStringContainsString('DROP CONSTRAINT IF EXISTS', $source);
        self::assertStringContainsString('JSON_THROW_ON_ERROR', $source);
    }

    public function test_only_identity_bearing_tables_are_constrained(): void
    {
        $source = file_get_contents(self::MIGRATION);
        self::assertIsString($source);

        foreach ([
            'kernel_depth_domain_totals',
            'kernel_blueprint_runs',
            'kernel_code_sequences',
            'taxonomy_v11_occurrences',
            'taxonomy_v11_terminal_facts',
            'taxonomy_v11_blueprint_assignments',
        ] as $table) {
            self::assertStringContainsString("'{$table}'", $source);
        }

        self::assertStringNotContainsString('taxonomy_subdomain_bank', $source);
        self::assertStringNotContainsString('english', $source);
        self::assertStringNotContainsString('General', $source);
        self::assertStringContainsString("'GEO', 'HIS', 'FAU', 'ART', 'SPO', 'CIN', 'CUI', 'SCI'", $source);
        self::assertStringContainsString('2, 4, 6, 7, 8, 9, 10', $source);
    }

    public function test_invalid_rows_are_checked_before_ddl(): void
    {
        $source = file_get_contents(self::MIGRATION);
        self::assertIsString($source);

        $checks = strpos($source, 'assertExistingRowsAreCanonical');
        $ddl = strpos($source, 'addConstraints');

        self::assertNotFalse($checks);
        self::assertNotFalse($ddl);
        self::assertLessThan($ddl, $checks);
    }
}