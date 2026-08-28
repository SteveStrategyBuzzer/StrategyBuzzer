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
    private const TECHNICAL_ATTEMPTS = 4;

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
     * Crée le seul Sous-domaine d'une occurrence avec ses Subjects PASS initiaux.
     *
     * Un Sous-domaine sans Subject exploitable n'est jamais persisté par
     * TaxonomyOrchestrator. Les Subjects rejetés restent hors de la persistance.
     *
     * @param array<int, array{subdomain: string, subjects: array<int, array{subject: string, pass_ideas: string[], fail_ideas: array}>>>
     *     $lookback
     * @return array{status: string, subdomain: string|null, subjects: string[]}
     */
    public function generateOccurrence(
        string $domain,
        string $domainLabel,
        DepthContract $contract,
        array $lookback = [],
    ): array {
        $history = $this->formatOccurrenceLookback($lookback);
        $maxFirstBatch = TaxonomyConfig::MAX_SUBJECTS_PER_GEMINI_CALL;

        $prompt = <<<PROMPT
Tu prépares une OCCURRENCE Taxonomy complète pour un pipeline pédagogique.

{$contract->toPromptText()}

DOMAINE : {$domainLabel}

Retourne, dans le même travail intellectuel :
- exactement 1 Sous-domaine officiel et viable;
- entre 1 et {$maxFirstBatch} Subjects PASS qui appartiennent strictement à ce Sous-domaine
  (ce premier lot pourra être complété par des appels supplémentaires si le
  Sous-domaine peut porter davantage de Subjects conformes);
- aucun Subject FAIL ou douteux.

Contraintes :
- Sous-domaine et Subjects doivent respecter le Depth et le Domaine.
- Le Sous-domaine ne peut pas être un Sujet déguisé.
- Chaque Subject doit pouvoir porter au moins une connaissance dominante.
- Ne répète ni ne reformule les occurrences historiques.
- N'invente aucun état de rotation, aucun prochain Domaine ou Depth.

{$history}

Si une occurrence exploitable est possible, retourne exactement :
{"status":"CANDIDATES","subdomain":"...","subjects":[{"value":"..."}, {"value":"..."}]}

Si aucune occurrence viable n'est possible, retourne :
{"status":"NO_MORE_OCCURRENCES","subdomain":null,"subjects":[]}

IMPORTANT : Retourne UNIQUEMENT du JSON valide, sans markdown ni explication.
PROMPT;

        $response = $this->callGemini($prompt, 'generateOccurrence', [
            'domain' => $domain,
            'depth' => $contract->depth,
        ]);

        $subdomain = $response['subdomain'] ?? null;
        $subjects = $response['subjects'] ?? [];

        return [
            'status' => (string) ($response['status'] ?? 'NO_MORE_OCCURRENCES'),
            'subdomain' => is_string($subdomain) && trim($subdomain) !== '' ? trim($subdomain) : null,
            'subjects' => array_values(array_filter(
                array_map(
                    fn($subject) => is_string($subject) ? trim($subject) : '',
                    is_array($subjects) ? $subjects : []
                ),
                fn(string $subject) => $subject !== ''
            )),
        ];
    }

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
- OUTSIDE_SUBDOMAIN : l'Idée doit appartenir réellement au Sous-domaine '{$subDomain}' et
  au Sujet '{$subject}' transmis ; refuse-la si elle appartient plutôt à un autre
  Sous-domaine, ou si son lien avec le Sous-domaine actif est seulement indirect
- TOO_NARROW : l'Idée ne doit pas être un détail trop étroit, anecdotique ou impropre à
  devenir une direction pédagogique dominante pour le Sujet ; une formulation courte ne
  doit jamais être confondue avec une portée intellectuelle trop étroite
- DISCRIMINATION SOUS-DOMAINE + SUJET : juge chaque Idée dans le couple EXACT
  Sous-domaine '{$subDomain}' + Sujet '{$subject}' transmis ; une Idée générique au
  Domaine, ou applicable indistinctement à plusieurs Sujets, doit être refusée

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

        $lastError = null;

        for ($attempt = 1; $attempt <= self::TECHNICAL_ATTEMPTS; $attempt++) {
            try {
                $response = Http::timeout($timeout)->post($url, $body);

                if (! $response->successful()) {
                    throw new \RuntimeException(
                        "HTTP {$response->status()} pendant {$operation}."
                    );
                }

                $json = $response->json();
                $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

                if (! is_string($text) || trim($text) === '') {
                    throw new \RuntimeException("Réponse vide pendant {$operation}.");
                }

                return $this->parseGeminiResponse($text, $operation);
            } catch (\Throwable $e) {
                $lastError = $e;

                Log::warning('[TaxonomyGeminiClient] Échec technique retryable', [
                    'operation' => $operation,
                    'attempt'   => $attempt,
                    'max_attempts' => self::TECHNICAL_ATTEMPTS,
                    'error'     => $e->getMessage(),
                    'context'   => $context,
                ]);
            }
        }

        throw new TaxonomyGeminiTechnicalException(
            "Gemini indisponible après 1 tentative initiale et 3 retries pour {$operation}: "
            . ($lastError?->getMessage() ?? 'erreur technique inconnue'),
            $operation,
            $context,
        );
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
            throw new \RuntimeException("JSON Gemini invalide pendant {$operation}.");
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

        $result = [
            'status'     => $status,
            'candidates' => $normalized,
        ];

        if (isset($decoded['subdomain'])) {
            $subdomain = $decoded['subdomain'];
            $result['subdomain'] = is_array($subdomain)
                ? ($subdomain['value'] ?? null)
                : $subdomain;
        }

        if (isset($decoded['subjects']) && is_array($decoded['subjects'])) {
            $result['subjects'] = array_values(array_filter(array_map(
                fn($subject) => is_string($subject)
                    ? $subject
                    : (is_array($subject) && is_string($subject['value'] ?? null)
                        ? $subject['value']
                        : null),
                $decoded['subjects']
            )));
        }

        return $result;
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

    /**
     * @param array<int, array{subdomain: string, subjects: string[], ideas: string[]}> $lookback
     */
    private function formatOccurrenceLookback(array $lookback): string
    {
        if ($lookback === []) {
            return 'Aucune occurrence historique exploitable.';
        }

        $lines = ['LOOKBACK-2 DES OCCURRENCES ANTÉRIEURES (ne pas répéter ni reformuler) :'];
        foreach ($lookback as $index => $entry) {
            $lines[] = '  Occurrence -' . ($index + 1) . ':';
            $lines[] = '    Sous-domaine : ' . ($entry['subdomain'] ?: '—');
            foreach (($entry['subjects'] ?? []) as $subject) {
                $lines[] = '    Sujet : ' . ($subject['subject'] ?? '—');
                $passIdeas = $subject['pass_ideas'] ?? [];
                if ($passIdeas !== []) {
                    $lines[] = '      PASS : ' . implode(', ', $passIdeas);
                }
                foreach (($subject['fail_ideas'] ?? []) as $failIdea) {
                    $value = $failIdea['value'] ?? '—';
                    $reason = $failIdea['reason'] ?? 'UNKNOWN';
                    $lines[] = "      FAIL : {$value} ({$reason})";
                }
            }
        }

        return implode("\n", $lines);
    }
}
