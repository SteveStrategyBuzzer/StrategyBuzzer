<?php

namespace App\Services\QuestionBank\Worker;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Bank-side LLM caller. INDEPENDENT from the legacy live-match path
 * (AIQuestionGeneratorService → Node API) — the worker speaks to
 * Gemini directly so we can ship a rich, segment-aware prompt
 * (depth rubric, cognitive_type explanation, multilingual JSON schema)
 * without bloating the live-match endpoint.
 *
 * #82 ships single-provider Gemini. #83 swaps this generator for a
 * multi-provider router with key rotation; the worker doesn't change.
 */
class BankAIGenerator
{
    private const GEMINI_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';
    private const GEMINI_MODEL = 'gemini-2.0-flash';

    /**
     * @param  array  $segment  one row from BankNeedsCalculator output
     * @return array{ok:bool, payload?:array, error?:string, http_status?:int}
     */
    public function generateForSegment(array $segment): array
    {
        $apiKey = env('GEMINI_API_KEY');
        if (!$apiKey) {
            return ['ok' => false, 'error' => 'GEMINI_API_KEY not set'];
        }

        $prompt = $this->buildPrompt($segment);

        try {
            $response = Http::timeout(45)->post(
                sprintf(self::GEMINI_URL, self::GEMINI_MODEL, $apiKey),
                [
                    'contents' => [[
                        'parts' => [['text' => $prompt]],
                    ]],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'temperature' => 0.85,
                        'topP' => 0.95,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'transport: '.$e->getMessage()];
        }

        if (!$response->successful()) {
            return [
                'ok' => false,
                'error' => 'gemini http error',
                'http_status' => $response->status(),
            ];
        }

        $body = $response->json();
        $text = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
        if (!$text) {
            return ['ok' => false, 'error' => 'no candidate text'];
        }

        // Gemini sometimes wraps JSON in ```json fences. Strip them.
        $text = trim($text);
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $text) ?? $text;
        }

        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            return ['ok' => false, 'error' => 'invalid JSON: '.json_last_error_msg()];
        }

        $payload = $this->shapeIntoPayload($decoded, $segment);
        if ($payload === null) {
            return ['ok' => false, 'error' => 'shape mismatch'];
        }

        return ['ok' => true, 'payload' => $payload];
    }

    private function buildPrompt(array $segment): string
    {
        $config = config('question_bank_profiles');
        $depth = (int) $segment['depth_range'][1];
        $rubric = $this->rubricFor($depth, $config);
        $cogExplain = $this->cognitiveExplain($segment['cognitive_type']);
        $langs = $config['worker']['preferred_languages'];
        $langList = implode(',', $langs);

        $schema = "{\n";
        $schema .= '  "concept_id": "<short stable kebab-case id, unique to this fact>",'."\n";
        $schema .= '  "concept_family": "<broader topic family, lowercase kebab>",'."\n";
        $schema .= '  "translations": {'."\n";
        foreach ($langs as $lang) {
            $schema .= '    "'.$lang.'": { "question_text": "...", "answer_a": "...", "answer_b": "...", "answer_c": "...", "answer_d": "...", "correct_answer_key": "A|B|C|D", "explanation": "...", "saviez_vous": "..." },'."\n";
        }
        $schema = rtrim($schema, ",\n")."\n  }\n}";

        return <<<PROMPT
Tu es un générateur de questions de quiz pour StrategyBuzzer. Génère UNE question dans le format JSON exact ci-dessous.

CONTRAINTES STRICTES:
- Domaine: {$segment['domain']}
- Sous-domaine: {$segment['sub_domain']}
- Type cognitif requis: {$segment['cognitive_type']} — {$cogExplain}
- Niveau de difficulté (depth {$depth}): {$rubric}
- Format: 4 réponses (A, B, C, D), une seule correcte
- Le `correct_answer_key` doit être la même lettre dans TOUTES les langues (les positions A/B/C/D sont logiquement alignées)
- Le champ `saviez_vous` est OBLIGATOIRE et doit contenir une anecdote concrète d'au moins 30 caractères, jamais générique
- Les langues à fournir: {$langList}

LANGUES: produis chaque traduction NATURELLE dans la langue cible (pas de traduction littérale mot à mot). La logique de la question doit être identique partout.

CONCEPT_ID: utilise un identifiant stable (kebab-case) qui décrit le fait précis testé. Exemple: "tour-eiffel-construction-1889" plutôt que "monument-paris-1".

Réponds UNIQUEMENT avec le JSON suivant (pas de markdown, pas de prose):
{$schema}
PROMPT;
    }

    /**
     * Reshape the LLM response into the canonical addToBank() payload,
     * carrying every fixed field from the segment so the writer doesn't
     * need to trust the LLM on classification.
     */
    private function shapeIntoPayload(array $decoded, array $segment): ?array
    {
        $translations = $decoded['translations'] ?? null;
        if (!is_array($translations) || empty($translations)) {
            return null;
        }

        // Sanitise translations.
        $cleanTranslations = [];
        foreach ($translations as $lang => $tr) {
            if (!is_array($tr)) {
                continue;
            }
            $cleanTranslations[$lang] = [
                'question_text' => (string) ($tr['question_text'] ?? ''),
                'answer_a' => (string) ($tr['answer_a'] ?? ''),
                'answer_b' => (string) ($tr['answer_b'] ?? ''),
                'answer_c' => (string) ($tr['answer_c'] ?? ''),
                'answer_d' => (string) ($tr['answer_d'] ?? ''),
                'correct_answer_key' => strtoupper((string) ($tr['correct_answer_key'] ?? 'A')),
                'explanation' => (string) ($tr['explanation'] ?? ''),
                'saviez_vous' => (string) ($tr['saviez_vous'] ?? ''),
            ];
        }
        if (empty($cleanTranslations)) {
            return null;
        }

        $payload = [
            'difficulty_depth' => (int) $segment['depth_range'][1],
            'domain' => (string) $segment['domain'],
            'sub_domain' => (string) $segment['sub_domain'],
            'question_type' => (string) ($segment['question_type'] ?? 'qcm'),
            'cognitive_type' => (string) $segment['cognitive_type'],
            'concept_id' => (string) ($decoded['concept_id'] ?? Str::random(20)),
            'concept_family' => (string) ($decoded['concept_family'] ?? $segment['sub_domain']),
            'source' => 'gemini',
            'validated' => $this->hasAllPreferred($cleanTranslations),
            'translations' => $cleanTranslations,
        ];

        // Set the level/boss exclusively (XOR enforced by DB CHECK).
        $target = $segment['mode_target'];
        if ($target['type'] === 'boss') {
            $payload['boss_level'] = (int) $target['level'];
        } else {
            // For solo_range we pin to the LOW end of the band so the row is
            // re-usable across the entire band; the planner only filters by
            // depth_range and band membership.
            $payload['difficulty_level'] = (int) $target['levels'][0];
        }

        return $payload;
    }

    private function hasAllPreferred(array $translations): bool
    {
        $preferred = config('question_bank_profiles.worker.preferred_languages', []);
        foreach ($preferred as $lang) {
            if (empty($translations[$lang]['question_text'])) {
                return false;
            }
        }
        return true;
    }

    private function rubricFor(int $depth, array $config): string
    {
        if ($depth >= 9) return $config['depth_rubric']['9-10'];
        if ($depth >= 7) return $config['depth_rubric']['7-8'];
        if ($depth >= 5) return $config['depth_rubric']['5-6'];
        return $config['depth_rubric']['3-4'];
    }

    private function cognitiveExplain(string $type): string
    {
        return match ($type) {
            'recognition' => 'fait direct, mémorisation pure ; pas de raisonnement multi-étapes',
            'reasoning' => 'requiert une déduction, comparaison ou calcul léger ; pas un simple rappel',
            'deceptive_trap' => 'distracteurs très plausibles ; confusion classique ; bonne réponse contre-intuitive',
            default => 'générique',
        };
    }
}
