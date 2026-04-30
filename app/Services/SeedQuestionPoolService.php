<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Charge des questions depuis le pool de secours embarqué
 * (resources/seed/fallback-questions-{lang}.json).
 *
 * Utilisé en TOUT DERNIER recours par MatchQuestionPlanner et QuestionService
 * quand la banque Postgres + le cache Redis ne couvrent pas un slot demandé.
 * Aucun appel IA n'est jamais fait : la règle est seed-first quand la banque
 * est insuffisante (#88).
 *
 * #93 :
 *  - le service supporte désormais les filtres `sub_domain`, `cognitive_type`
 *    et `niveau_band` pour s'aligner sur le contrat du planner.
 *  - le fallback silencieux vers la version FR est REMOVED. Si la langue
 *    demandée n'a pas de fichier seed, on log un warning et on retourne
 *    null pour que le détecteur dry de #92 voie la lacune réelle.
 */
class SeedQuestionPoolService
{
    /** @var array<string, array> mémo par langue (false = absence prouvée) */
    private array $memo = [];

    /**
     * Retourne une question seed adaptée au filtre demandé. La sélection
     * tente de matcher le sub_domain, le cognitive_type, puis le niveau_band
     * — chaque contrainte est appliquée seulement si elle réduit l'ensemble
     * des candidats sans le vider. Retourne null si le pool est vide pour
     * cette langue (jamais de fallback silencieux vers une autre langue).
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

        $available = $this->narrow($available, $filter, 'sub_domain');
        $available = $this->narrow($available, $filter, 'cognitive_type');
        $available = $this->narrow($available, $filter, 'niveau_band');

        $picked = $available[array_rand($available)];

        return $this->normalize($picked, $language);
    }

    /**
     * Returns a snapshot of the pool for a language (used by inventory tests
     * and ops health surfaces). Empty array if no file exists for that lang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function inventoryFor(string $language): array
    {
        return $this->loadPool($language);
    }

    /**
     * Filtre l'ensemble candidat par une clé donnée seulement si la clé est
     * fournie ET si la restriction laisse au moins un candidat. Sinon retombe
     * sur l'ensemble d'entrée — on dégrade graciously plutôt que de retourner
     * vide.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @param array<string, mixed>             $filter
     * @param string                           $key
     * @return array<int, array<string, mixed>>
     */
    private function narrow(array $candidates, array $filter, string $key): array
    {
        if (empty($filter[$key])) {
            return $candidates;
        }
        $needle = strtolower((string) $filter[$key]);
        $sourceKey = ($key === 'sub_domain') ? 'sub_theme' : $key;
        $matched = array_values(array_filter($candidates, function ($q) use ($needle, $sourceKey, $key) {
            $vSource = strtolower((string) ($q[$sourceKey] ?? ''));
            $vKey    = strtolower((string) ($q[$key] ?? ''));
            return $vSource === $needle || $vKey === $needle;
        }));
        return !empty($matched) ? $matched : $candidates;
    }

    private function loadPool(string $language): array
    {
        if (array_key_exists($language, $this->memo)) {
            return $this->memo[$language];
        }

        $path = resource_path("seed/fallback-questions-{$language}.json");
        if (!file_exists($path)) {
            Log::warning('[SeedQuestionPoolService] no seed pool file for language — silent fallback DISABLED', [
                'language' => $language,
                'expected_path' => $path,
            ]);
            return $this->memo[$language] = [];
        }

        $raw = file_get_contents($path);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['questions'])) {
            Log::warning('[SeedQuestionPoolService] seed pool file malformed or empty', [
                'language' => $language,
                'path' => $path,
            ]);
            return $this->memo[$language] = [];
        }

        return $this->memo[$language] = $decoded['questions'];
    }

    private function normalize(array $q, string $language): array
    {
        $text = (string) ($q['question_text'] ?? '');
        $id = 'seed_' . $language . '_' . substr(md5(strtolower(trim($text))), 0, 12);

        return [
            'id'             => $id,
            'group_id'       => null,
            'concept_id'     => $q['concept_id'] ?? null,
            'type'           => $q['type'] ?? 'multiple',
            'question_text'  => $text,
            'text'           => $text,
            'answers'        => $q['answers'] ?? [],
            'correct_index'  => (int) ($q['correct_id'] ?? $q['correct_index'] ?? 0),
            'correct_id'     => (int) ($q['correct_id'] ?? $q['correct_index'] ?? 0),
            'explanation'    => $q['explanation'] ?? null,
            'saviez_vous'    => $q['saviez_vous'] ?? null,
            'theme'          => $q['theme'] ?? 'general',
            'sub_theme'      => $q['sub_theme'] ?? $q['sub_domain'] ?? null,
            'sub_domain'     => $q['sub_domain'] ?? $q['sub_theme'] ?? null,
            'cognitive_type' => $q['cognitive_type'] ?? null,
            'niveau_band'    => $q['niveau_band'] ?? null,
            'language'       => $language,
            'from_seed'      => true,
        ];
    }
}
