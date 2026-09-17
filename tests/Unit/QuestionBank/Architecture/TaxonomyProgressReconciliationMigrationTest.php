<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Architecture;

use Tests\TestCase;

final class TaxonomyProgressReconciliationMigrationTest extends TestCase
{
    private const MIGRATION = __DIR__
        . '/../../../../database/migrations/2026_09_11_000003_reconcile_legacy_taxonomy_progress.php';

    public function test_reconciliation_is_guarded_and_reversible(): void
    {
        $source = file_get_contents(self::MIGRATION);
        self::assertIsString($source);

        self::assertStringContainsString('LOCK TABLE taxonomy_progress IN ACCESS EXCLUSIVE MODE', $source);
        self::assertStringContainsString("count() !== 0", $source);
        self::assertStringContainsString("Schema::getColumnListing('taxonomy_progress')", $source);
        self::assertStringContainsString("Schema::drop('taxonomy_progress')", $source);
        self::assertStringContainsString("Schema::create('taxonomy_progress'", $source);
    }

    public function test_reconciliation_does_not_seed_or_transform_legacy_rows(): void
    {
        $source = file_get_contents(self::MIGRATION);
        self::assertIsString($source);

        self::assertStringNotContainsString('insert(', $source);
        self::assertStringNotContainsString('update(', $source);
        self::assertStringNotContainsString('CreatorDomainRegistry', $source);
    }
}