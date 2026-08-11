<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * TaxonomyGeminiClient — seul chemin Gemini pour la couche Taxonomy.
 *
 * Génère via l'API Gemini REST :
 *   - generateSubdomains() : Sous-domaines pour un Domaine + Depth
 *   - generateSubjects()   : Sujets pour un Sous-domaine
 *   - generateIdeas()      : Idées Dominantes candidates pour un Sujet
 *
 * Chaque appel inclut la mémoire cumulative complète (appel 1+2+...+n).
 * La mémoire est transmise en argument — la persistance est gérée par TaxonomyBankRepository.
 *
 * Gemini est un EXÉCUTEUR, pas une autorité architecturale.
 * Toutes les décisions PASS/FAIL sont prises par ValidationDominantIdeas, pas ici.
 */
class TaxonomyGeminiClient
{
    private string $apiKey;

    public function __construct()
    {
        $key = env('GEMINI_API_KEY');

        if (empty($key)) {
            throw new RuntimeException(
                'TaxonomyGeminiClient: GEMINI_API_KEY absent de l\'environnement.'
            );
        }

        $this->apiKey = $key;
    }

    // =========================================================================
    // Génération de Sous-domaines
    // =========================================================================

    /**
     * Génère des candidats Sous-domaines pour un Domaine + Depth.
     *
     * @param string         $domain            Code du Domaine (ex: 'histoire')
     * @param string         $domainLabel       Nom lisible du Domaine (ex: 'Histoire')
     * @param DepthContract  $contract          Contrat qualitatif du Depth
     * @param string[]       $existingSubdomains Sous-domaines déjà créés
     * @param int            $remainingCapacity Capacité restante
     * @param array          $cumulativeMemory  Mémoire cumulée des appels précédents
     *
     * @return array{status: string, candidates: array<array{value: string}>}
     *   status = 'CANDIDATES' | 'NO_MORE_SUBDOMAINS'
     */
    public function generateSubdomains(
        string        $domain,
        string        $domainLabel,
        DepthContract $contract,
        array         $existingSubdomains,
        int           $remainingCapacity,
        array         $cumulativeMemory = [],
    ): array {
        $memorySection = $this->formatCumulativeMemory($cumulativeMemory);

        $exclusionsText = empty($existingSubdomains)
            ? 'Aucun Sous-domaine existant.'
            : "Sous-domaines déjà créés (NE PAS répéter ni reformuler) :\n"
              . implode("\n", array_map(fn($s) => "  - {$s}", $existingSubdomains));

        $prompt = <<<PROMPT
Tu es un générateur de SOUS-DOMAINES pour un pipeline Taxonomy pédagogique.

{$contract->toPromptText()}

DOMAINE : {$domainLabel}

RÈGLES OBLIGATOIRES pour chaque Sous-domaine :
- Être un grain inférieur du Domaine (catégorie sous le Domaine)
- Rester strictement dans l'angle du Domaine
- Respecter le Depth : calibré pour le profil "{$contract->subjectProfileLabel}"
- FORMAT_MINIMAL_IRREDUCTIBLE : 1 mot si 1 mot suffit, groupe irréductible seulement si nécessaire
- Suffisamment large pour produire plusieurs Sujets conformes
- Ne PAS embarquer une précision qui appartient aux Sujets
- Ne PAS être un doublon, synonyme ou reformulation d'un Sous-domaine existant

{$exclusionsText}

{$memorySection}

CAPACITÉ RESTANTE : {$remainingCapacity} Sous-domaine(s) à générer.

Si tu peux proposer de nouveaux Sous-domaines valides, retourne :
{"status": "CANDIDATES", "candidates": [{"value": "..."}]}

Si aucun nouveau Sous-domaine valide n'est disponible pour ce Domaine à ce Depth, retourne :
{"status": "NO_MORE_SUBDOMAINS", "candidates": []}

IMPORTANT : Retourne UNIQUEMENT du JSON valide, sans markdown, sans explication.
PROMPT;

        return $this->callGemini($prompt, 'generateSubdomains', [
            'domain' => $domain,
            'depth'  => $contract->depth,
        ]);
    }

    // =========================================================================
    // Génération de Sujets
    // =========================================================================

    /**
     * Génère des candidats Sujets pour un Sous-domaine.
     *
     * @param string        $domain           Code du Domaine
     * @param string        $domainLabel      Nom lisible du Domaine
     * @param string        $subDomain        Nom du Sous-domaine actif
     * @param DepthContract $contract         Contrat qualitatif du Depth
     * @param string[]      $existingSubjects Sujets déjà créés
     * @param string[]      $consumedSubjects Sujets déjà consommés
     * @param int           $remainingCapacity Capacité restante (jusqu'à 50)
     * @param array         $cumulativeMemory Mémoire cumulée des appels précédents
     *
     * @return array{status: string, candidates: array<array{value: string}>}
     *   status = 'CANDIDATES' | 'NO_MORE_SUBJECTS'
     */
    public function generateSubjects(
        string        $domain,
        string        $domainLabel,
        string        $subDomain,
        DepthContract $contract,
        array         $existingSubjects = [],
        array         $consumedSubjects = [],
        int           $remainingCapacity = 50,
        array         $cumulativeMemory = [],
    ): array {
        $memorySection = $this->formatCumulativeMemory($cumulativeMemory);

        $allKnownSubjects = array_unique(array_merge($existingSubjects, $consumedSubjects));
        $exclusionsText   = empty($allKnownSubjects)
            ? 'Aucun Sujet existant.'
            : "Sujets déjà présents (NE PAS répéter, reformuler ou absorber) :\n"
              . implode("\n", array_map(fn($s) => "  - {$s}", $allKnownSubjects));

        $prompt = <<<PROMPT
Tu es un générateur de SUJETS pour un pipeline Taxonomy pédagogique.

{$contract->toPromptText()}

GÉNÉALOGIE OBLIGATOIRE :
  DOMAINE    : {$domainLabel}
  SOUS-DOMAINE : {$subDomain}

Chaque Sujet candidat doit respecter :
1. Être une subdivision directe du Sous-domaine
2. Rester STRICTEMENT dans l'angle du Domaine — le Domaine est l'angle intellectuel, pas un simple contexte
3. Respecter le profil Sujet : "{$contract->subjectProfileLabel}" — {$contract->subjectProfileDescription}
4. FORMAT_MINIMAL_IRREDUCTIBLE : 1 mot si 1 mot suffit
5. Pas de doublon exact, synonyme, reformulation ou équivalence conceptuelle
6. Ne pas absorber plusieurs Sujets voisins
7. Pas être absorbé par un Sujet existant
8. Capacité à produire plusieurs connaissances dominantes distinctes

EXEMPLE INTERDIT (Domaine Histoire, Sous-domaine Canada) :
  Géographie, Climat, Faune canadienne, Cuisine canadienne
  → Ces sujets ne sont PAS dans le Domaine Histoire.

EXEMPLE CORRECT (Domaine Histoire, Sous-domaine Canada) :
  Confédération canadienne, Nouvelle-France, Guerre de 1812

{$exclusionsText}

{$memorySection}

CAPACITÉ RESTANTE : {$remainingCapacity} Sujet(s) à générer (maximum 50 total).

Si tu peux proposer de nouveaux Sujets valides, retourne :
{"status": "CANDIDATES", "candidates": [{"value": "..."}]}

Si aucun nouveau Sujet valide n'est disponible pour ce Sous-domaine, retourne :
{"status": "NO_MORE_SUBJECTS", "candidates": []}

IMPORTANT : Retourne UNIQUEMENT du JSON valide, sans markdown, sans explication.
PROMPT;

        return $this->callGemini($prompt, 'generateSubjects', [
            'domain'     => $domain,
            'sub_domain' => $subDomain,
            'depth'      => $contract->depth,
        ]);
    }

    // =========================================================================
    // Génération d'Idées Dominantes
    // =========================================================================

    /**
     * Génère des candidats Idées Dominantes pour un Sujet.
     *
     * @param string        $domain            Code du Domaine
     * @param string        $domainLabel       Nom lisible du Domaine
     * @param string        $subDomain         Nom du Sous-domaine actif
     * @param string        $subject           Nom du Sujet actif
     * @param DepthContract $contract          Contrat qualitatif du Depth
     * @param string[]      $passIdeas         Idées déjà validées PASS
     * @param string[]      $failIdeas         Idées rejetées (valeurs)
     * @param array         $failDetails       Détails des FAIL [{value, reason, conflict_with}]
     * @param int           $remainingSlots    Slots restants (jusqu'à 5)
     * @param array         $cumulativeMemory  Mémoire cumulée des appels précédents
     *
     * @return array{status: string, candidates: array<array{value: string}>}
     *   status = 'CANDIDATES' | 'NO_MORE_IDEAS'
     */
    public function generateIdeas(
        string        $domain,
        string        $domainLabel,
        string        $subDomain,
        string        $subject,
        DepthContract $contract,
        array         $passIdeas = [],
        array         $failIdeas = [],
        array         $failDetails = [],
        int           $remainingSlots = 5,
        array         $cumulativeMemory = [],
    ): array {
        $memorySection = $this->formatCumulativeMemory($cumulativeMemory);

        $passText = empty($passIdeas)
            ? 'Aucune Idée validée PASS pour l\'instant.'
            : "Idées déjà PASS (NE PAS répéter ni reformuler) :\n"
              . implode("\n", array_map(fn($i) => "  ✓ {$i}", $passIdeas));

        $failText = empty($failDetails)
            ? 'Aucun rejet précédent.'
            : "Idées rejetées FAIL (NE PAS proposer de reformulation de ces directions) :\n"
              . implode("\n", array_map(
                  fn($f) => "  ✗ {$f['value']} → {$f['reason']}"
                            . ($f['conflict_with'] ? " (conflit: {$f['conflict_with']})" : ''),
                  $failDetails
              ));

        $prompt = <<<PROMPT
Tu es un générateur d'IDÉES DOMINANTES pour un pipeline Taxonomy pédagogique.

{$contract->toPromptText()}

GÉNÉALOGIE COMPLÈTE OBLIGATOIRE :
  DOMAINE      : {$domainLabel}
  SOUS-DOMAINE : {$subDomain}
  SUJET        : {$subject}

Question conceptuelle à satisfaire pour chaque candidat :
"Cette Idée est-elle une CONNAISSANCE DOMINANTE RÉELLE du Sujet '{$subject}',
dans le Sous-domaine '{$subDomain}', vue selon le Domaine '{$domainLabel}',
au niveau de précision du Depth {$contract->depth} ({$contract->subjectProfileLabel}) ?"

RÈGLES STRICTES :
- PAS de catégorie générique (Date, Personnages, Causes, Conséquences, Lieux, Documents…)
- PAS de répétition du Sujet
- PAS de doublon, synonyme, reformulation ou équivalence conceptuelle avec les PASS existants
- FORMAT_MINIMAL_IRREDUCTIBLE : pas de phrase, unité de sens minimale
- Chaque Idée doit pointer une direction de connaissance DISTINCTE et DOMINANTE
- Cherche de NOUVELLES DIRECTIONS, pas de nouvelles façons d'écrire une direction déjà rejetée

EXEMPLE INTERDIT (direction déjà rejetée) :
  Après "Acte de l'Amérique du Nord britannique" → NE PAS proposer "AANB de 1867"
  → Ce sont la même connaissance dominante.

{$passText}

{$failText}

{$memorySection}

SLOTS RESTANTS : {$remainingSlots} Idée(s) à générer (maximum 5 total par Sujet).

Si tu peux proposer de nouvelles Idées valides, retourne :
{"status": "CANDIDATES", "candidates": [{"value": "..."}]}

Si aucune nouvelle direction de connaissance valide n'est disponible, retourne :
{"status": "NO_MORE_IDEAS", "candidates": []}

IMPORTANT : Retourne UNIQUEMENT du JSON valide, sans markdown, sans explication.
PROMPT;

        return $this->callGemini($prompt, 'generateIdeas', [
            'domain'     => $domain,
            'sub_domain' => $subDomain,
            'subject'    => $subject,
            'depth'      => $contract->depth,
        ]);
    }

    // =========================================================================
    // Transport HTTP commun
    // =========================================================================

    /**
     * Appel Gemini REST API avec parsing JSON structuré.
     *
     * @return array{status: string, candidates: array}
     */
    private function callGemini(string $prompt, string $operation, array $context = []): array
    {
        $model   = TaxonomyConfig::GEMINI_MODEL;
        $url     = TaxonomyConfig::GEMINI_BASE_URL . "/{$model}:generateContent?key={$this->apiKey}";
        $timeout = TaxonomyConfig::GEMINI_TIMEOUT_SECONDS;

        $body = [
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $prompt]],
                ],
            ],
            'generationConfig' => [
                'temperature'     => 0.7,
                'maxOutputTokens' => 2048,
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = Http::timeout($timeout)->post($url, $body);

            if (! $response->successful()) {
                Log::error('[TaxonomyGeminiClient] HTTP erreur', [
                    'operation' => $operation,
                    'status'    => $response->status(),
                    'context'   => $context,
                ]);

                return $this->fallbackResponse($operation);
            }

            $json = $response->json();
            $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (! is_string($text) || empty($text)) {
                Log::warning('[TaxonomyGeminiClient] Réponse vide', [
                    'operation' => $operation,
                    'context'   => $context,
                ]);

                return $this->fallbackResponse($operation);
            }

            return $this->parseGeminiResponse($text, $operation);
        } catch (\Throwable $e) {
            Log::error('[TaxonomyGeminiClient] Exception', [
                'operation' => $operation,
                'error'     => $e->getMessage(),
                'context'   => $context,
            ]);

            return $this->fallbackResponse($operation);
        }
    }

    /**
     * Parse la réponse JSON de Gemini.
     *
     * @return array{status: string, candidates: array}
     */
    private function parseGeminiResponse(string $text, string $operation): array
    {
        // Nettoyer le JSON (Gemini peut parfois ajouter des backticks)
        $cleaned = preg_replace('/^```json\s*|\s*```$/', '', trim($text)) ?? trim($text);

        $decoded = json_decode($cleaned, true);

        if (! is_array($decoded)) {
            Log::warning('[TaxonomyGeminiClient] JSON invalide', [
                'operation' => $operation,
                'raw'       => substr($text, 0, 500),
            ]);

            return $this->fallbackResponse($operation);
        }

        $status     = $decoded['status'] ?? 'NO_MORE';
        $candidates = $decoded['candidates'] ?? [];

        // Normaliser les candidats : accepter string ou {value: string}
        $normalized = [];
        foreach ($candidates as $c) {
            if (is_string($c)) {
                $normalized[] = ['value' => $c];
            } elseif (is_array($c) && isset($c['value']) && is_string($c['value'])) {
                $normalized[] = ['value' => trim($c['value'])];
            }
        }

        // Filtrer les candidats vides
        $normalized = array_values(array_filter(
            $normalized,
            fn($c) => ! empty($c['value'])
        ));

        return [
            'status'     => $status,
            'candidates' => $normalized,
        ];
    }

    /**
     * Réponse de repli en cas d'erreur — signal "épuisé" pour ne pas bloquer le pipeline.
     *
     * @return array{status: string, candidates: array}
     */
    private function fallbackResponse(string $operation): array
    {
        $statusMap = [
            'generateSubdomains' => 'NO_MORE_SUBDOMAINS',
            'generateSubjects'   => 'NO_MORE_SUBJECTS',
            'generateIdeas'      => 'NO_MORE_IDEAS',
        ];

        return [
            'status'     => $statusMap[$operation] ?? 'NO_MORE',
            'candidates' => [],
        ];
    }

    /**
     * Formate la mémoire cumulative pour injection dans le prompt.
     *
     * @param array $memory Tableau d'entrées [{attempt, candidates, pass, fail, fail_details}]
     */
    private function formatCumulativeMemory(array $memory): string
    {
        if (empty($memory)) {
            return '';
        }

        $lines = ['MÉMOIRE CUMULATIVE DES APPELS PRÉCÉDENTS :'];

        foreach ($memory as $entry) {
            $attempt  = $entry['attempt'] ?? '?';
            $pass     = $entry['pass'] ?? [];
            $fail     = $entry['fail_details'] ?? [];
            $covered  = $entry['covered_directions'] ?? [];

            $lines[] = "\n  [Appel {$attempt}]";

            if (! empty($pass)) {
                $lines[] = '  PASS : ' . implode(', ', $pass);
            }

            if (! empty($fail)) {
                foreach ($fail as $f) {
                    $lines[] = "  FAIL : {$f['value']} → {$f['reason']}"
                               . ($f['conflict_with'] ? " (conflit: {$f['conflict_with']})" : '');
                }
            }

            if (! empty($covered)) {
                $lines[] = '  Directions couvertes : ' . implode(', ', $covered);
            }
        }

        return implode("\n", $lines);
    }
}
