<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Architecture;

use App\Services\QuestionBank\KernelCodeEngine;
use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
use App\Services\QuestionBank\Rotation\KernelTerminalFactRepository;
use App\Services\QuestionBank\Taxonomy\TaxonomyBankRepository;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

final class OwnerWriteBoundaryTest extends TestCase
{
    public function test_production_writers_are_confined_to_declared_owner_apis(): void
    {
        $root = dirname(__DIR__, 4) . '/app';
        $violations = [];

        $allowedRunOwners = [
            KernelBlueprintRunRepository::class,
            KernelCodeEngine::class,
        ];
        $allowedSequenceOwners = [
            KernelCodeEngine::class,
        ];
        $allowedTaxonomyOwners = [
            TaxonomyBankRepository::class,
        ];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();
            $source = file_get_contents($path);
            if ($source === false) {
                self::fail("Lecture impossible du fichier de production {$path}.");
            }

            $source = self::withoutComments($source);
            $class = self::declaredClass($source);
            $constants = self::stringConstants($source);
            $writes = preg_match_all(
                '/(?:DB::table|->table)\s*\(\s*([\'"][^\'"]+[\'"]|self::[A-Z_]+)\s*\)'
                . '[\s\S]{0,280}?->\s*(insertOrIgnore|insert|update|upsert|increment|delete)\s*\(/',
                $source,
                $matches,
                PREG_SET_ORDER
            );

            if ($writes === false) {
                self::fail("Analyse statique impossible pour {$path}.");
            }

            foreach ($matches as $match) {
                $table = self::resolveTable($match[1], $constants);
                if ($table === null) {
                    continue;
                }
                $ownerSet = str_starts_with($table, 'taxonomy_v11_')
                    ? $allowedTaxonomyOwners
                    : match ($table) {
                        'kernel_blueprint_runs' => $allowedRunOwners,
                        'kernel_code_sequences' => $allowedSequenceOwners,
                        default => [],
                    };

                if ($ownerSet !== [] && ! in_array($class, $ownerSet, true)) {
                    $violations[] = "{$path}: {$table}->{$match[2]}";
                }
            }
        }

        $this->assertSame([], $violations, implode("\n", $violations));
    }

    public function test_owner_apis_remain_explicitly_declared(): void
    {
        $this->assertTrue(
            method_exists(KernelBlueprintRunRepository::class, 'markEngaged')
            && method_exists(KernelBlueprintRunRepository::class, 'writeTaxonomyProjection')
        );
        $this->assertTrue(method_exists(KernelCodeEngine::class, 'assignKernelCode'));
        $this->assertTrue(method_exists(TaxonomyBankRepository::class, 'findOrCreateV11Occurrence'));
    }

    private static function withoutComments(string $source): string
    {
        $tokens = token_get_all($source);
        $result = '';
        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }
            $result .= is_array($token) ? $token[1] : $token;
        }

        return $result;
    }

    private static function declaredClass(string $source): ?string
    {
        if (! preg_match('/namespace\s+([^;]+);/', $source, $namespace)
            || ! preg_match('/(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class)) {
            return null;
        }

        return trim($namespace[1]) . '\\' . $class[1];
    }

    /** @return array<string, string> */
    private static function stringConstants(string $source): array
    {
        preg_match_all(
            '/(?:private|protected|public)?\s*const\s+([A-Z_]+)\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        $constants = [];
        foreach ($matches as $match) {
            $constants[$match[1]] = $match[2];
        }

        return $constants;
    }

    /** @param array<string, string> $constants */
    private static function resolveTable(string $expression, array $constants): ?string
    {
        if ($expression[0] === "'" || $expression[0] === '"') {
            return substr($expression, 1, -1);
        }

        $name = substr($expression, strlen('self::'));

        return $constants[$name] ?? null;
    }
}