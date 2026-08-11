<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Rotation\DomainExhaustionChecker;
use App\Services\QuestionBank\Rotation\TaxonomyNavigatorInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * TaxonomyOrchestrator — seule implémentation active de TaxonomyNavigatorInterface.
 *
 * Remplace TaxonomyProgressManager (supprimé) comme propriétaire de :
 *   - la progression Taxonomy (peekNext / confirmConsumed)
 *   - la détection d'épuisement (isExhausted)
 *   - l'orchestration du pipeline Gemini → Validation → Banque
 *
 * Logique migérée de TaxonomyProgressManager :
 *   - peekNext() idempotent : retourne toujours le même triplet sans avancer
 *   - confirmConsumed() avance d'une Idée Dominante (au lieu d'un Sujet)
 *   - isExhausted() : vérification DB pure, sans appel Gemini
 *   - Transactions DB pour éviter les race conditions
 *
 * Invariants :
 *   - peekNext() ne retourne null que si le domaine est réellement épuisé
 *   - confirmConsumed() n'avance que si un triplet était disponible
 *   - Gemini est appelé par TaxonomyGeminiClient, jamais directement ici
 *   - ValidationDominantIdeas décide PASS/FAIL, jamais Gemini ni cet orchestrateur
 */
final class TaxonomyOrchestrator implements DomainExhaustionChecker, TaxonomyNavigatorInterface
{
    /** Garde anti-boucle infinie dans fillBank(). */
    private const MAX_FILL_ITERATIONS = 32;

    /**
     * Résolution domain_code (lowercase ASCII) → nom lisible pour les prompts Gemini.
     * Miroir du DOMAIN_MAP de l'ancien TaxonomyReader.
     */
    private const DOMAIN_LABELS = [
        'histoire'   => 'Histoire',
        'geographie' => 'Géographie',
        'sport'      => 'Sport',
        'art'        => 'Art',
        'cuisine'    => 'Cuisine',
        'science'    => 'Science',
        'cinema'     => 'Cinéma',
        'faune'      => 'Faune',
        'general'    => 'Général',
    ];

    public function __construct(
        private readonly TaxonomyBankRepository   $repo,
        private readonly TaxonomyGeminiClient     $gemini,
        private readonly ValidationDominantIdeas  $validator,
    ) {}

    // =========================================================================
    // TaxonomyNavigatorInterface
    // =========================================================================

    /**
     * Retourne le prochain triplet disponible (sub_domain, subject, dominant_idea_active).
     *
     * Idempotent : deux appels successifs sans confirmConsumed() retournent le même triplet.
     * Retourne null si le domaine est entièrement épuisé.
     *
     * {@inheritdoc}
     */
    public function peekNext(int $depth, string $domainCode): ?array
    {
        // Fail-closed : lève une exception si le Depth est inconnu
        $contract = DepthContractRegistry::get($depth);

        // Étape 1 : chercher un triplet déjà disponible (IDEMPOTENT)
        $existing = $this->repo->findFirstAvailableIdea($depth, $domainCode);

        if ($existing !== null) {
            return $this->buildTerritory($existing);
        }

        // Étape 2 : remplir la banque
        $filled = $this->fillBank($depth, $domainCode, $contract);

        if (! $filled) {
            return null; // domaine épuisé
        }

        // Étape 3 : retourner le triplet nouvellement généré
        $idea = $this->repo->findFirstAvailableIdea($depth, $domainCode);

        return $idea !== null ? $this->buildTerritory($idea) : null;
    }

    /**
     * Marque l'idée active courante comme CONSUMED et avance vers la suivante.
     *
     * Sémantique : l'Idée Dominante active a été entièrement consommée par le pipeline.
     * Idempotent si appelé quand aucune idée n'est disponible (no-op).
     *
     * {@inheritdoc}
     */
    public function confirmConsumed(int $depth, string $domainCode): void
    {
        DB::transaction(function () use ($depth, $domainCode) {
            $idea = $this->repo->findFirstAvailableIdea($depth, $domainCode);

            if ($idea === null) {
                return; // No-op : bassin épuisé ou non initialisé
            }

            $this->repo->markIdeaConsumed($idea->idea_id);
        });
    }

    // =========================================================================
    // DomainExhaustionChecker
    // =========================================================================

    /**
     * Retourne true si le bassin Taxonomy pour ce couple est entièrement épuisé.
     *
     * Vérification DB pure — sans appel Gemini.
     * Retourne false si le bassin n'a jamais été initialisé.
     *
     * {@inheritdoc}
     */
    public function isExhausted(int $depth, string $domainCode): bool
    {
        // Si aucun sous-domaine n'existe → jamais initialisé → pas épuisé
        $subdomains = $this->repo->getSubdomains($depth, $domainCode);
        if (empty($subdomains)) {
            return false;
        }

        // S'il reste une idée PASS disponible → pas épuisé
        $hasAvailableIdea = $this->repo->findFirstAvailableIdea($depth, $domainCode);
        if ($hasAvailableIdea !== null) {
            return false;
        }

        // S'il reste un sous-domaine pouvant générer des sujets → pas épuisé
        foreach ($subdomains as $sd) {
            if (! $sd->generation_exhausted) {
                return false;
            }

            // S'il reste un sujet pouvant encore générer des idées
            $subjects = $this->repo->getSubjectsForSubdomain($sd->id);
            foreach ($subjects as $s) {
                if (! $s->idea_generation_exhausted) {
                    return false;
                }
            }
        }

        return true;
    }

    // =========================================================================
    // Pipeline interne — remplissage de la banque
    // =========================================================================

    /**
     * Tente de remplir la banque jusqu'à ce qu'une idée PASS soit disponible.
     *
     * Parcourt les sous-domaines existants puis en génère de nouveaux si nécessaire.
     * Retourne true si au moins une idée PASS a été générée.
     */
    private function fillBank(int $depth, string $domainCode, DepthContract $contract): bool
    {
        $domainLabel = self::DOMAIN_LABELS[$domainCode] ?? ucfirst($domainCode);
        $iterations  = 0;

        while ($iterations++ < self::MAX_FILL_ITERATIONS) {
            // 1. Chercher un sous-domaine actif (non exhausted ou avec sujets restants)
            $subdomain = $this->repo->findActiveSubdomain($depth, $domainCode);

            if ($subdomain === null) {
                // Tenter de générer un nouveau sous-domaine
                $subdomain = $this->generateNewSubdomain($depth, $domainCode, $domainLabel, $contract);

                if ($subdomain === null) {
                    return false; // Impossible de générer plus de sous-domaines
                }
            }

            // 2. Chercher un sujet nécessitant des idées
            $subject = $this->repo->findSubjectNeedingIdeas($subdomain->id);

            if ($subject === null) {
                // Tenter de générer de nouveaux sujets pour ce sous-domaine
                $newSubject = $this->generateNewSubject($subdomain, $depth, $domainCode, $domainLabel, $contract);

                if ($newSubject !== null) {
                    $subject = $newSubject;
                } else {
                    // Ce sous-domaine ne peut plus produire de sujets
                    $this->repo->markSubdomainGenerationExhausted($subdomain->id);
                    continue; // Essayer le prochain sous-domaine
                }
            }

            // 3. Générer des idées pour ce sujet
            $ideaGenerated = $this->generateIdeasForSubject(
                $subject, $subdomain, $depth, $domainCode, $domainLabel, $contract
            );

            if ($ideaGenerated) {
                return true; // Au moins une idée PASS générée
            }

            // Sujet épuisé → continuer avec le suivant
            // (markSubjectIdeaGenerationExhausted déjà appelé dans generateIdeasForSubject)
        }

        return false; // Garde anti-boucle infinie
    }

    /**
     * Génère un nouveau sous-domaine via Gemini (avec mémoire cumulative).
     * Retourne null si impossible (tentatives épuisées ou Gemini ne peut plus).
     */
    private function generateNewSubdomain(
        int           $depth,
        string        $domainCode,
        string        $domainLabel,
        DepthContract $contract,
    ): ?object {
        $contextKey = TaxonomyBankRepository::subdomainContextKey($depth, $domainCode);
        $attemptNum = $this->repo->getNextAttemptNumber('SUBDOMAIN', $contextKey);

        if ($attemptNum > TaxonomyConfig::MAX_SUBDOMAIN_GENERATION_ATTEMPTS) {
            return null; // Tentatives épuisées
        }

        // Récupérer sous-domaines existants + mémoire cumulative
        $existingSubdomains = array_map(
            fn($sd) => $sd->subdomain_name,
            $this->repo->getSubdomains($depth, $domainCode)
        );

        $remaining        = TaxonomyConfig::MAX_SUBDOMAINS_PER_DOMAIN - count($existingSubdomains);
        $cumulativeMemory = $this->repo->getCumulativeMemory('SUBDOMAIN', $contextKey);

        if ($remaining <= 0) {
            return null; // Capacité maximale atteinte
        }

        $response = $this->gemini->generateSubdomains(
            domain: $domainCode,
            domainLabel: $domainLabel,
            contract: $contract,
            existingSubdomains: $existingSubdomains,
            remainingCapacity: $remaining,
            cumulativeMemory: $cumulativeMemory,
        );

        $candidates   = $response['candidates'] ?? [];
        $passSubdomains = [];

        foreach ($candidates as $c) {
            $value = trim($c['value'] ?? '');
            if (empty($value)) {
                continue;
            }

            if (! in_array($value, $existingSubdomains, true)) {
                $passSubdomains[] = $value;
            }
        }

        // Persister la mémoire cumulative
        $this->repo->persistMemoryEntry(
            contextType: 'SUBDOMAIN',
            contextKey: $contextKey,
            attemptNumber: $attemptNum,
            candidates: array_column($candidates, 'value'),
            passItems: $passSubdomains,
            failDetails: [],
            coveredDirections: $passSubdomains,
            generationExhausted: $response['status'] === 'NO_MORE_SUBDOMAINS',
        );

        // Créer les sous-domaines en DB
        foreach ($passSubdomains as $sdName) {
            $this->repo->findOrCreateSubdomain($depth, $domainCode, $sdName);
        }

        if (empty($passSubdomains)) {
            return null; // Gemini n'a pas pu en générer
        }

        // Retourner le premier nouveau sous-domaine
        return $this->repo->findActiveSubdomain($depth, $domainCode);
    }

    /**
     * Génère de nouveaux sujets pour un sous-domaine via Gemini (avec mémoire cumulative).
     * Retourne le premier nouveau sujet créé, ou null si impossible.
     */
    private function generateNewSubject(
        object        $subdomain,
        int           $depth,
        string        $domainCode,
        string        $domainLabel,
        DepthContract $contract,
    ): ?object {
        $contextKey = TaxonomyBankRepository::subjectContextKey($depth, $domainCode, $subdomain->subdomain_name);
        $attemptNum = $this->repo->getNextAttemptNumber('SUBJECT', $contextKey);

        if ($attemptNum > TaxonomyConfig::MAX_SUBJECT_GENERATION_ATTEMPTS) {
            return null; // Tentatives épuisées
        }

        $existingSubjects = array_map(
            fn($s) => $s->subject_name,
            $this->repo->getSubjectsForSubdomain($subdomain->id)
        );

        $remaining        = TaxonomyConfig::MAX_SUBJECTS_PER_SUBDOMAIN - count($existingSubjects);
        $cumulativeMemory = $this->repo->getCumulativeMemory('SUBJECT', $contextKey);

        if ($remaining <= 0) {
            return null;
        }

        // Incrémenter le compteur de tentatives du sous-domaine
        $this->repo->incrementSubdomainSubjectAttemptCount($subdomain->id);

        $response = $this->gemini->generateSubjects(
            domain: $domainCode,
            domainLabel: $domainLabel,
            subDomain: $subdomain->subdomain_name,
            contract: $contract,
            existingSubjects: $existingSubjects,
            consumedSubjects: [],
            remainingCapacity: $remaining,
            cumulativeMemory: $cumulativeMemory,
        );

        $candidates    = $response['candidates'] ?? [];
        $newSubjectNames = [];

        foreach ($candidates as $c) {
            $value = trim($c['value'] ?? '');
            if (empty($value) || in_array($value, $existingSubjects, true)) {
                continue;
            }
            $newSubjectNames[] = $value;
        }

        // Persister la mémoire cumulative
        $this->repo->persistMemoryEntry(
            contextType: 'SUBJECT',
            contextKey: $contextKey,
            attemptNumber: $attemptNum,
            candidates: array_column($candidates, 'value'),
            passItems: $newSubjectNames,
            failDetails: [],
            coveredDirections: $newSubjectNames,
            generationExhausted: $response['status'] === 'NO_MORE_SUBJECTS',
        );

        // Créer les sujets en DB
        $firstNew = null;
        foreach ($newSubjectNames as $sName) {
            $s = $this->repo->findOrCreateSubject($subdomain->id, $sName);
            $firstNew ??= $s;
        }

        return $firstNew;
    }

    /**
     * Génère des Idées Dominantes pour un sujet via Gemini + ValidationDominantIdeas.
     *
     * Retourne true si au moins une idée PASS a été générée.
     * Marque le sujet comme idea_generation_exhausted si les tentatives sont épuisées.
     */
    private function generateIdeasForSubject(
        object        $subject,
        object        $subdomain,
        int           $depth,
        string        $domainCode,
        string        $domainLabel,
        DepthContract $contract,
    ): bool {
        $contextKey = TaxonomyBankRepository::ideaContextKey(
            $depth, $domainCode, $subdomain->subdomain_name, $subject->subject_name
        );

        $attemptNum = $this->repo->getNextAttemptNumber('IDEA', $contextKey);

        if ($attemptNum > TaxonomyConfig::MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS) {
            // All attempts already consumed in previous calls — subject is done.
            // Check for zero-PASS so we can emit the observability warning before returning.
            $this->repo->markSubjectIdeaGenerationExhausted($subject->id);
            $passCount = count($this->repo->getPassIdeaValues($subject->id));
            $failCount = count($this->repo->getFailIdeaDetails($subject->id));
            $this->warnIfZeroPass($subject, $subdomain, $depth, $domainCode, $passCount, $failCount, $attemptNum, 'MAX_ATTEMPTS_ALREADY_REACHED');
            return false;
        }

        // Incrémenter le compteur de tentatives du sujet
        $this->repo->incrementSubjectIdeaAttemptCount($subject->id);

        // Récupérer l'état courant
        $passIdeas       = $this->repo->getPassIdeaValues($subject->id);
        $failDetails     = $this->repo->getFailIdeaDetails($subject->id);
        $failIdeas       = array_column($failDetails, 'value');
        $cumulativeMemory = $this->repo->getCumulativeMemory('IDEA', $contextKey);

        $remainingSlots = TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT - count($passIdeas);

        if ($remainingSlots <= 0) {
            $this->repo->markSubjectIdeaGenerationExhausted($subject->id);
            return ! empty($passIdeas);
        }

        $response = $this->gemini->generateIdeas(
            domain: $domainCode,
            domainLabel: $domainLabel,
            subDomain: $subdomain->subdomain_name,
            subject: $subject->subject_name,
            contract: $contract,
            passIdeas: $passIdeas,
            failIdeas: $failIdeas,
            failDetails: $failDetails,
            remainingSlots: $remainingSlots,
            cumulativeMemory: $cumulativeMemory,
        );

        $candidates     = $response['candidates'] ?? [];
        $newPassIdeas   = [];
        $newFailDetails = [];

        foreach ($candidates as $c) {
            $value = trim($c['value'] ?? '');
            if (empty($value)) {
                continue;
            }

            // Stopper si on a déjà atteint la capacité maximale
            if (count($passIdeas) + count($newPassIdeas) >= TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT) {
                break;
            }

            // ── Étape 1 : validation individuelle (PHP-enforced rules) ────────
            $result = $this->validator->validateOne(
                candidate: $value,
                domain: $domainLabel,
                subDomain: $subdomain->subdomain_name,
                subject: $subject->subject_name,
                contract: $contract,
                passIdeas: array_merge($passIdeas, $newPassIdeas),
                failIdeas: $failIdeas,
                coveredDirections: [],
            );

            if ($result->isFail()) {
                $newFailDetails[] = [
                    'value'        => $value,
                    'reason'       => $result->reason,
                    'conflict_with'=> $result->conflictWith,
                ];
                $this->repo->persistFailIdea($subject->id, $value, $result->reason ?? '', $result->conflictWith);
                continue;
            }

            // ── Étape 2 : validation collective de diversité AVANT persistance ─
            // INVARIANT : une idée n'est jamais persistée comme PASS si elle
            // cause une collision de diversité. La validation collective intervient
            // avant l'écriture en DB pour éviter tout état incohérent.
            $prospectiveSet  = array_merge($passIdeas, $newPassIdeas, [$value]);
            $diversityResult = $this->validator->validateDiversity($prospectiveSet);

            if ($diversityResult !== null && $diversityResult->isFail()) {
                // Diversité FAIL → persister comme FAIL, jamais comme PASS
                $newFailDetails[] = [
                    'value'        => $value,
                    'reason'       => FailReason::SET_DIVERSITY_COLLISION,
                    'conflict_with'=> $diversityResult->conflictWith,
                ];
                $this->repo->persistFailIdea(
                    $subject->id,
                    $value,
                    FailReason::SET_DIVERSITY_COLLISION,
                    $diversityResult->conflictWith,
                );
                continue;
            }

            // ── Étape 3 : validation individuelle + diversité = PASS ──────────
            $newPassIdeas[] = $value;
            $this->repo->persistPassIdea($subject->id, $value);
        }

        // Persister la mémoire cumulative
        $this->repo->persistMemoryEntry(
            contextType: 'IDEA',
            contextKey: $contextKey,
            attemptNumber: $attemptNum,
            candidates: array_column($candidates, 'value'),
            passItems: $newPassIdeas,
            failDetails: $newFailDetails,
            coveredDirections: array_merge($passIdeas, $newPassIdeas),
            generationExhausted: $response['status'] === 'NO_MORE_IDEAS',
        );

        // Marquer épuisé si NO_MORE_IDEAS ou si MAX_ATTEMPTS atteint
        if (
            $response['status'] === 'NO_MORE_IDEAS'
            || $attemptNum >= TaxonomyConfig::MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS
        ) {
            $this->repo->markSubjectIdeaGenerationExhausted($subject->id);

            // ── Alerte : sujet épuisé sans aucune idée PASS ───────────────────
            $totalPass = count(array_merge($passIdeas, $newPassIdeas));
            $totalFail = count($failDetails) + count($newFailDetails);
            $this->warnIfZeroPass($subject, $subdomain, $depth, $domainCode, $totalPass, $totalFail, $attemptNum, $response['status']);
        }

        return ! empty($newPassIdeas);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Émet un Log::warning si un sujet est épuisé sans aucune idée PASS.
     *
     * Gemini a systématiquement renvoyé des idées invalides — le sujet est
     * silencieusement abandonné. Ce warning est l'unique signal ops disponible
     * avant qu'un opérateur lance `php artisan questions:taxonomy:exhausted-subjects`.
     */
    private function warnIfZeroPass(
        object $subject,
        object $subdomain,
        int    $depth,
        string $domainCode,
        int    $passCount,
        int    $failCount,
        int    $attemptNumber,
        string $status,
    ): void {
        if ($passCount === 0 && $failCount > 0) {
            Log::warning('taxonomy.subject_exhausted_with_zero_pass', [
                'depth'          => $depth,
                'domain_code'    => $domainCode,
                'subdomain_name' => $subdomain->subdomain_name,
                'subject_id'     => $subject->id,
                'subject_name'   => $subject->subject_name,
                'fail_count'     => $failCount,
                'attempt_number' => $attemptNumber,
                'status'         => $status,
                'message'        => "Subject exhausted with {$failCount} FAIL idea(s) and 0 PASS — "
                                   . "Gemini returned unusable ideas for all {$attemptNumber} attempt(s). "
                                   . "Run `php artisan questions:taxonomy:exhausted-subjects` for a full report.",
            ]);
        }
    }

    /**
     * Construit le tableau de territoire à retourner depuis une ligne d'idée.
     *
     * @return array{sub_domain: string, subject: string, dominant_idea_active: string}
     */
    private function buildTerritory(object $idea): array
    {
        return [
            'sub_domain'           => $idea->subdomain_name,
            'subject'              => $idea->subject_name,
            'dominant_idea_active' => $idea->idea_value,
        ];
    }
}
