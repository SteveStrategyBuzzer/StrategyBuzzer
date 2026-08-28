<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class TaxonomyV11ArchitectureTest extends TestCase
{
    private const LEGACY_TABLES = [
        'taxonomy_subdomain_bank',
        'taxonomy_subject_bank',
        'taxonomy_dominant_idea_bank',
        'taxonomy_generation_memory',
    ];

    private const LEGACY_HELPERS = [
        'warmUpCell',
        'fillBank',
        'generateNewSubdomain',
        'generateNewSubject',
        'generateIdeasForSubject',
        'buildTerritory',
        'findExhaustedWithOnlyFails',
    ];

    public function test_active_php_surfaces_do_not_reference_v10_tables_or_helpers(): void
    {
        $root = dirname(__DIR__, 4);
        $sources = $this->phpSources([$root . '/app', $root . '/tests']);

        foreach (self::LEGACY_TABLES as $table) {
            $this->assertStringNotContainsString($table, $sources, "Table legacy active: {$table}");
        }

        $appSources = $this->phpSources([$root . '/app/Services/QuestionBank/Taxonomy']);
        foreach (self::LEGACY_HELPERS as $helper) {
            $this->assertDoesNotMatchRegularExpression(
                '/function\s+' . preg_quote($helper, '/') . '\s*\(/',
                $appSources,
                "Helper legacy actif: {$helper}",
            );
        }
    }

    public function test_tests_use_legacy_navigation_only_for_three_tombstone_refusals(): void
    {
        $tests = $this->phpSources([dirname(__DIR__, 4) . '/tests']);

        $this->assertStringNotContainsString('TaxonomyNavigatorInterface', $tests);
        $this->assertSame(1, preg_match_all('/->peekNext\s*\(/', $tests));
        $this->assertSame(1, preg_match_all('/->confirmConsumed\s*\(/', $tests));
        $this->assertSame(1, preg_match_all('/->isExhausted\s*\(/', $tests));
    }

    /**
     * @param array<int, string> $roots
     */
    private function phpSources(array $roots): string
    {
        $sources = '';
        foreach ($roots as $root) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($iterator as $file) {
                if (
                    $file->isFile()
                    && $file->getExtension() === 'php'
                    && $file->getPathname() !== __FILE__
                ) {
                    $sources .= "\n" . file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }
}