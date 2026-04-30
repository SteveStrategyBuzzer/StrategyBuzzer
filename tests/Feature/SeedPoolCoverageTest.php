<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * #93 — make-time guard against the embedded seed pool regressing.
 *
 * For each supported language, every (domain, depth_band) cell of the
 * fallback question pool must hold at least N=10 questions. If this
 * fails, regenerate with:
 *
 *   node scripts/seed/generate_seed_pool.mjs --langs <lang> --target 10
 *
 * The same matrix is checked by `npm run seed:check`; this test simply
 * brings the guard into the PHPUnit suite so it runs in CI alongside
 * the rest of the question-bank invariants.
 */
class SeedPoolCoverageTest extends TestCase
{
    /** @var int Minimum questions per (lang, domain, band) segment. */
    private const TARGET = 10;

    /** Mirrors scripts/seed/check_seed_coverage.mjs and the generator. */
    private const LANGS = [
        'fr', 'en', 'es', 'it', 'de', 'pt', 'ru', 'zh', 'ar', 'el',
    ];

    private const DOMAINS = [
        'general', 'histoire', 'sport', 'geographie',
        'art', 'cuisine', 'science', 'cinema', 'faune',
    ];

    private const BANDS = ['3-4', '5-6', '7-8', '9-10'];

    public function test_seed_pool_covers_every_lang_domain_band_segment(): void
    {
        $gaps    = [];
        $missing = [];

        foreach (self::LANGS as $lang) {
            $path = base_path("resources/seed/fallback-questions-{$lang}.json");
            if (! is_file($path)) {
                $missing[] = $path;
                continue;
            }

            $raw = file_get_contents($path);
            $data = json_decode($raw, true);
            $this->assertIsArray($data, "Seed file {$path} is not valid JSON.");
            $this->assertIsArray($data['questions'] ?? null, "{$path} has no 'questions' array.");

            // Recount from the raw rows — we don't trust _meta, it could
            // be stale after a hand-edit. The check must reflect reality.
            $cov = [];
            foreach (self::DOMAINS as $d) {
                foreach (self::BANDS as $b) {
                    $cov[$d][$b] = 0;
                }
            }
            foreach ($data['questions'] as $q) {
                $dom  = $q['theme']      ?? $q['domain'] ?? null;
                $band = $q['depth_band'] ?? null;
                if ($dom !== null && $band !== null && isset($cov[$dom][$band])) {
                    $cov[$dom][$band]++;
                }
            }
            foreach (self::DOMAINS as $d) {
                foreach (self::BANDS as $b) {
                    if ($cov[$d][$b] < self::TARGET) {
                        $gaps[] = sprintf('%s/%s/%s → %d/%d', $lang, $d, $b, $cov[$d][$b], self::TARGET);
                    }
                }
            }
        }

        $this->assertEmpty(
            $missing,
            "Missing seed files: \n  " . implode("\n  ", $missing)
        );
        $this->assertEmpty(
            $gaps,
            sprintf(
                "Seed pool below target (%d per segment) — regenerate with\n".
                "  node scripts/seed/generate_seed_pool.mjs --langs <lang> --target %d\nGaps:\n  %s",
                self::TARGET,
                self::TARGET,
                implode("\n  ", $gaps)
            )
        );
    }
}
