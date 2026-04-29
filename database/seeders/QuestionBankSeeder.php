<?php

namespace Database\Seeders;

use App\Services\QuestionBank\QuestionBankRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Imports the legacy resources/seed/fallback-questions-{lang}.json files into
 * the persistent bank as validated question_groups with FR translations.
 *
 * This bootstraps the bank with curated content so the picker can serve
 * questions immediately (the worker #82 will then continuously expand it).
 */
class QuestionBankSeeder extends Seeder
{
    /**
     * Map free-form `sub_theme` strings used in the seed JSON to the
     * 8 canonical sub-domains. Anything not mapped here is skipped.
     */
    private const SUB_DOMAIN_MAP = [
        'géographie' => 'Géographie',
        'histoire'   => 'Histoire',
        'art'        => 'Art',
        'sciences'   => 'Science',
        'science'    => 'Science',
        'sport'      => 'Sport',
        'musique'    => 'Art',
        'cinéma'     => 'Cinéma',
        'cinema'     => 'Cinéma',
        'cuisine'    => 'Cuisine',
        'faune'      => 'Faune',
        'culture'    => 'Histoire',
    ];

    /**
     * Spread the 30 seed questions across the Solo student bands so each
     * band has some content to serve from day one.
     */
    private const SEED_LEVEL_ROTATION = [5, 15, 25, 35, 45, 55, 65, 75, 85, 95];

    public function run(): void
    {
        $repo = new QuestionBankRepository();

        foreach (['fr'] as $language) {
            $path = resource_path("seed/fallback-questions-{$language}.json");
            if (!file_exists($path)) {
                continue;
            }

            $raw = json_decode(file_get_contents($path), true);
            $questions = $raw['questions'] ?? [];
            $imported = 0;
            $skipped = 0;

            foreach ($questions as $i => $q) {
                $subThemeRaw = strtolower(trim((string) ($q['sub_theme'] ?? '')));
                $subDomain = self::SUB_DOMAIN_MAP[$subThemeRaw] ?? null;
                if ($subDomain === null) {
                    $skipped++;
                    continue;
                }

                $level = self::SEED_LEVEL_ROTATION[$i % count(self::SEED_LEVEL_ROTATION)];
                $depth = $this->depthForLevel($level);

                $answers = $q['answers'] ?? [];
                $correctId = (int) ($q['correct_id'] ?? $q['correct_index'] ?? 0);
                $keyMap = ['A', 'B', 'C', 'D'];
                $correctKey = $keyMap[$correctId] ?? 'A';

                $conceptId = 'seed_' . substr(md5(strtolower((string) ($q['question_text'] ?? ''))), 0, 16);

                $payload = [
                    'difficulty_level' => $level,
                    'boss_level' => null,
                    'difficulty_depth' => $depth,
                    'domain' => $subDomain,
                    'sub_domain' => $subDomain,
                    'question_type' => ($q['type'] ?? 'multiple') === 'true_false' ? 'true_false' : 'qcm',
                    'cognitive_type' => 'recognition',
                    'concept_id' => $conceptId,
                    'concept_family' => $subDomain,
                    'source' => 'seed',
                    'validated' => true,
                    'translations' => [
                        $language => [
                            'question_text' => $q['question_text'] ?? '',
                            'answer_a' => $answers[0] ?? '',
                            'answer_b' => $answers[1] ?? '',
                            'answer_c' => $answers[2] ?? null,
                            'answer_d' => $answers[3] ?? null,
                            'correct_answer_key' => $correctKey,
                            'explanation' => $q['explanation'] ?? null,
                            'saviez_vous' => $q['explanation'] ?? null,
                        ],
                    ],
                ];

                $group = $repo->addToBank($payload, updateExisting: true);
                if ($group) {
                    $imported++;
                }
            }

            Log::info('[QuestionBankSeeder] imported seed pool', [
                'language' => $language,
                'imported' => $imported,
                'skipped' => $skipped,
            ]);

            $this->command?->info("[QuestionBankSeeder] {$language}: imported={$imported} skipped={$skipped}");
        }
    }

    private function depthForLevel(int $level): int
    {
        if ($level <= 9)  return 4;
        if ($level <= 19) return 5;
        if ($level <= 39) return 6;
        if ($level === 40) return 7;
        if ($level <= 69) return 8;
        if ($level === 70) return 9;
        return 10;
    }
}
