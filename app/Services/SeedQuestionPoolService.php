<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Charge des questions depuis le pool de secours embarqué
 * (resources/seed/fallback-questions-{lang}.json).
 *
 * Utilisé en TOUT DERNIER recours par MatchQuestionPlanner quand la banque
 * Postgres ne couvre pas tous les slots demandés. Aucun appel IA n'est
 * jamais fait : la règle est seed-first quand la banque est insuffisante.
 */
class SeedQuestionPoolService
{
    /** @var array<string, array> mémo par langue */
    private array $memo = [];

    /**
     * Retourne une question seed adaptée au filtre demandé (sub_domain,
     * cognitive_type) si possible, sinon n'importe quelle question encore
     * inutilisée. Retourne null si le pool est vide pour cette langue.
     *
     * @param array<int,string> $usedTextHashes
     */
    public function pickOne(string $language, array $filter = [], array $usedTextHashes = []): ?array
    {
        $pool = $this->loadPool($language);
        if (empty($pool)) {
            return null;
        }

        $usedSet = array_flip($usedTextHashes);
        $available = array_values(array_filter($pool, function ($q) use ($usedSet) {
            $hash = md5((string) ($q['question_text'] ?? ''));
            return !isset($usedSet[$hash]);
        }));

        if (empty($available)) {
            // Tout consommé → on autorise la répétition (mieux que rien).
            $available = $pool;
        }

        // Privilégier le sub_domain demandé
        if (!empty($filter['sub_domain'])) {
            $sub = strtolower($filter['sub_domain']);
            $matched = array_values(array_filter($available, function ($q) use ($sub) {
                return strtolower((string) ($q['sub_theme'] ?? '')) === $sub;
            }));
            if (!empty($matched)) {
                $available = $matched;
            }
        }

        $picked = $available[array_rand($available)];

        return $this->normalize($picked, $language);
    }

    private function loadPool(string $language): array
    {
        if (isset($this->memo[$language])) {
            return $this->memo[$language];
        }

        $candidates = [
            resource_path("seed/fallback-questions-{$language}.json"),
            resource_path('seed/fallback-questions-fr.json'),
        ];

        foreach ($candidates as $path) {
            if (file_exists($path)) {
                $raw = file_get_contents($path);
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded['questions'])) {
                    return $this->memo[$language] = $decoded['questions'];
                }
            }
        }

        Log::info('[SeedQuestionPoolService] no seed pool available', ['language' => $language]);
        return $this->memo[$language] = [];
    }

    private function normalize(array $q, string $language): array
    {
        $text = (string) ($q['question_text'] ?? '');
        $id = 'seed_' . $language . '_' . substr(md5(strtolower(trim($text))), 0, 12);

        return [
            'id'             => $id,
            'group_id'       => null,
            'type'           => $q['type'] ?? 'multiple',
            'question_text'  => $text,
            'text'           => $text,
            'answers'        => $q['answers'] ?? [],
            'correct_index'  => (int) ($q['correct_id'] ?? $q['correct_index'] ?? 0),
            'correct_id'     => (int) ($q['correct_id'] ?? $q['correct_index'] ?? 0),
            'explanation'    => $q['explanation'] ?? null,
            'theme'          => $q['theme'] ?? 'general',
            'sub_theme'      => $q['sub_theme'] ?? null,
            'language'       => $language,
            'from_seed'      => true,
        ];
    }
}
