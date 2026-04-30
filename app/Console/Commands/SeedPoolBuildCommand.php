<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Generates resources/seed/fallback-questions-{lang}.json for every language
 * declared in resources/seed/source/general-fallback-source.php.
 *
 * The source PHP file is the single source of truth (#93). Run this whenever
 * concepts or translations change. NEVER reachable from gameplay.
 */
class SeedPoolBuildCommand extends Command
{
    protected $signature = 'seed-pool:build {--check : Verify per-language JSONs match the source without writing}';

    protected $description = 'Build per-language fallback seed pool JSON files from the canonical PHP source.';

    public function handle(): int
    {
        $sourcePath = resource_path('seed/source/general-fallback-source.php');
        if (!file_exists($sourcePath)) {
            $this->error("Source file missing: {$sourcePath}");
            return self::FAILURE;
        }

        $source = require $sourcePath;
        if (!is_array($source) || empty($source['concepts']) || empty($source['meta']['languages'])) {
            $this->error('Source file is malformed (expected meta.languages and concepts).');
            return self::FAILURE;
        }

        $languages = $source['meta']['languages'];
        $concepts  = $source['concepts'];
        $check     = (bool) $this->option('check');

        $this->info(sprintf(
            'Building seed pool: %d concepts × %d languages',
            count($concepts),
            count($languages)
        ));

        $errors = [];
        foreach ($languages as $lang) {
            $payload = $this->buildPayloadForLanguage($lang, $concepts, $source['meta']);
            $targetPath = resource_path("seed/fallback-questions-{$lang}.json");
            $jsonOut = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
            ) . "\n";

            if ($check) {
                $existing = file_exists($targetPath) ? file_get_contents($targetPath) : '';
                if (trim($existing) !== trim($jsonOut)) {
                    $errors[] = "OUT OF SYNC: {$targetPath}";
                }
                continue;
            }

            file_put_contents($targetPath, $jsonOut);
            $this->line(sprintf('  ✓ %s (%d questions)', basename($targetPath), count($payload['questions'])));
        }

        if ($check) {
            if (!empty($errors)) {
                $this->error('Seed pool JSON files are out of sync with source:');
                foreach ($errors as $e) {
                    $this->error("  - {$e}");
                }
                return self::FAILURE;
            }
            $this->info('All seed pool JSON files are in sync with the source.');
            return self::SUCCESS;
        }

        $this->info('Seed pool build complete.');
        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $concepts
     * @param array<string, mixed>             $meta
     * @return array<string, mixed>
     */
    private function buildPayloadForLanguage(string $lang, array $concepts, array $meta): array
    {
        $questions = [];
        foreach ($concepts as $concept) {
            $tr = $concept['translations'][$lang] ?? null;
            if ($tr === null) {
                throw new \RuntimeException(sprintf(
                    'Concept %s missing translation for language %s',
                    $concept['concept_id'] ?? '(unknown)',
                    $lang
                ));
            }

            $questions[] = [
                'type'           => 'multiple',
                'concept_id'     => $concept['concept_id'],
                'sub_domain'     => $concept['sub_domain'],
                'sub_theme'      => $concept['sub_domain'],
                'cognitive_type' => $concept['cognitive_type'],
                'niveau_band'    => $concept['niveau_band'],
                'theme'          => $meta['theme'] ?? 'general',
                'language'       => $lang,
                'question_text'  => $tr['question_text'],
                'answers'        => $tr['answers'],
                'correct_id'     => (int) $tr['correct_id'],
                'explanation'    => $tr['explanation'],
                'saviez_vous'    => $tr['saviez_vous'],
            ];
        }

        return [
            '_meta' => [
                'purpose' => 'Embedded seed pool (#88/#93). Consumed when bank+cache are dry. NEVER calls AI.',
                'language' => $lang,
                'theme' => $meta['theme'] ?? 'general',
                'subdomains' => $meta['subdomains'] ?? [],
                'count' => count($questions),
                'source_version' => $meta['version'] ?? 1,
                'generated_by' => 'seed-pool:build',
            ],
            'questions' => $questions,
        ];
    }
}
