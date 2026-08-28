<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TaxonomyOrchestrator — orchestration active Taxonomy v1.1.
 *
 * Invariants :
 *   - assignToBlueprint() est l'unique chemin d'attribution.
 *   - La sélection, l'écriture au Blueprint et la consommation sont atomiques.
 *   - L'épuisement est un fait terminal persistant, jamais une interrogation.
 *   - Gemini est appelé par TaxonomyGeminiClient, jamais directement ici
 *   - ValidationDominantIdeas décide PASS/FAIL, jamais Gemini ni cet orchestrateur
 */
final class TaxonomyOrchestrator
{
    /** Garde anti-boucle infinie du warm-up v1.1. */
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
    // Taxonomy v1.1 — attribution atomique au Blueprint engagé
    // =========================================================================

    /**
     * Attribue le triplet Taxonomy v1.1 au Blueprint déjà engagé.
     *
     * L'attribution est idempotente par blueprint_id et consomme exactement le
     * même IdeaSlot qui est écrit dans le Blueprint. Aucun signal de rotation
     * n'est lu ni produit ici.
     */
    public function assignToBlueprint(KernelBlueprint $blueprint): void
    {
        if (! $blueprint->isRotationFilled()) {
            throw new RuntimeException(
                'Taxonomy v1.1 requiert un Blueprint dont depth et domain sont déjà remplis.'
            );
        }

        if ($blueprint->isTaxonomyFilled()) {
            return;
        }

        $blueprintId = (string) $blueprint->blueprint_id;
        $depth = (int) $blueprint->depth;
        $domainCode = (string) $blueprint->domain;

        $assignment = $this->repo->findV11BlueprintAssignment($blueprintId);
        if ($assignment !== null) {
            $this->fillBlueprintFromV11Assignment($blueprint, $assignment);
            return;
        }

        $occurrence = $this->repo->findOrCreateV11Occurrence($depth, $domainCode);
        if ($occurrence->status === 'BLOCKED') {
            throw new TaxonomyBlockedException(
                "Taxonomy v1.1 est BLOCKED pour Depth {$depth} / Domaine {$domainCode}."
            );
        }

        try {
            $occurrence = $this->ensureV11Content(
                $occurrence,
                $depth,
                $domainCode,
                DepthContractRegistry::get($depth),
            );

            DB::transaction(function () use ($blueprint, $blueprintId, $occurrence, $depth, $domainCode) {
                $lockedOccurrence = $this->repo->lockV11Occurrence((int) $occurrence->id);
                if ($lockedOccurrence === null || $lockedOccurrence->status === 'BLOCKED') {
                    throw new TaxonomyBlockedException(
                        "Taxonomy v1.1 est BLOCKED pour Depth {$depth} / Domaine {$domainCode}."
                    );
                }

                $existing = $this->repo->findV11BlueprintAssignment($blueprintId);
                if ($existing !== null) {
                    $this->fillBlueprintFromV11Assignment($blueprint, $existing);
                    return;
                }

                $idea = $this->repo->claimV11FirstAvailableIdea((int) $lockedOccurrence->id);
                if ($idea === null) {
                    throw new TaxonomyPreparationException(
                        "Taxonomy v1.1: aucun IdeaSlot disponible pour occurrence={$occurrence->id}."
                    );
                }

                $created = $this->repo->createV11BlueprintAssignment(
                    $blueprintId,
                    (int) $occurrence->id,
                    $idea,
                    $depth,
                    $domainCode,
                );

                if (! $created) {
                    $winner = $this->repo->findV11BlueprintAssignment($blueprintId);
                    if ($winner === null) {
                        throw new RuntimeException(
                            "Taxonomy v1.1: assignation {$blueprintId} absente après conflit d'idempotence."
                        );
                    }

                    $this->fillBlueprintFromV11Assignment($blueprint, $winner);
                    return;
                }

                $this->fillBlueprintFromV11Idea($blueprint, $idea);
                $this->repo->markV11IdeaConsumed((int) $idea->idea_id);
                $this->repo->markV11SubjectUsedIfNoAvailableIdeas((int) $idea->subject_id);

                if (
                    $this->repo->remainingV11Ideas((int) $lockedOccurrence->id) === 0
                    && $this->repo->remainingV11Subjects((int) $lockedOccurrence->id) === 0
                ) {
                    $this->repo->markV11OccurrenceExhausted(
                        (int) $lockedOccurrence->id,
                        $depth,
                        $domainCode,
                    );
                }
            });

            $this->repo->resetV11TechnicalFailures((int) $occurrence->id);
        } catch (TaxonomyGeminiTechnicalException|TaxonomyPreparationException $exception) {
            $blocked = $this->repo->recordV11TechnicalFailure(
                (int) $occurrence->id,
                $exception->getMessage(),
            );

            if ($blocked) {
                throw new TaxonomyBlockedException(
                    "Taxonomy v1.1 a atteint trois opérations intellectuelles non résolues "
                    . "pour Depth {$depth} / Domaine {$domainCode}.",
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /**
     * Prépare une occurrence jusqu'à disposer d'un IdeaSlot PASS sélectionnable.
     *
     * @return object occurrence ouverte contenant au moins un IdeaSlot disponible
     */
    private function ensureV11Content(
        object $occurrence,
        int $depth,
        string $domainCode,
        DepthContract $contract,
    ): object {
        for ($pass = 0; $pass < 2; $pass++) {
            if ($this->repo->findV11FirstAvailableIdea((int) $occurrence->id) !== null) {
                return $occurrence;
            }

            $subdomain = $this->ensureV11SubdomainAndSubjects(
                $occurrence,
                $domainCode,
                $contract,
            );

            if ($this->repo->findV11FirstAvailableIdea((int) $occurrence->id) !== null) {
                return $occurrence;
            }

            $subject = $this->repo->findV11SubjectNeedingIdeas((int) $occurrence->id);
            if ($subject !== null) {
                $this->generateV11IdeasForSubject(
                    $occurrence,
                    $subdomain,
                    $subject,
                    $domainCode,
                    $contract,
                );

                if ($this->repo->findV11FirstAvailableIdea((int) $occurrence->id) !== null) {
                    return $occurrence;
                }
            }

            // Une occurrence réellement consommée est terminale. Une anomalie de
            // préparation, elle, lève une exception plus haut et ne devient jamais
            // une fausse fin de contenu.
            if ($this->repo->remainingV11Subjects((int) $occurrence->id) === 0) {
                $this->repo->markV11OccurrenceExhausted(
                    (int) $occurrence->id,
                    $depth,
                    $domainCode,
                );
                $occurrence = $this->repo->findOrCreateV11Occurrence($depth, $domainCode);

                if ($occurrence->status === 'BLOCKED') {
                    throw new TaxonomyBlockedException(
                        "Taxonomy v1.1 est BLOCKED pour Depth {$depth} / Domaine {$domainCode}."
                    );
                }

                continue;
            }

            throw new TaxonomyPreparationException(
                "Taxonomy v1.1: l'occurrence {$occurrence->id} ne produit aucun IdeaSlot exploitable."
            );
        }

        throw new TaxonomyPreparationException(
            "Taxonomy v1.1: aucune occurrence exploitable après deux tentatives de préparation."
        );
    }

    private function ensureV11SubdomainAndSubjects(
        object $occurrence,
        string $domainCode,
        DepthContract $contract,
    ): object {
        $existing = $this->repo->findV11Subdomain((int) $occurrence->id);
        if ($existing !== null) {
            return $existing;
        }

        $response = $this->gemini->generateOccurrence(
            $domainCode,
            $this->domainLabel($domainCode),
            $contract,
            $this->repo->v11Lookback((int) $contract->depth, $domainCode),
        );

        if (
            $response['status'] !== 'CANDIDATES'
            || $response['subdomain'] === null
            || $response['subjects'] === []
        ) {
            throw new TaxonomyPreparationException(
                "Taxonomy v1.1: Gemini n'a pas produit de Sous-domaine + Subjects exploitables "
                . "pour Depth {$contract->depth} / Domaine {$domainCode}."
            );
        }

        // DEC-100 : capacité technique = MAX_SUBJECTS_PER_GEMINI_CALL Subjects par appel.
        // Le premier lot persisté avec le Sous-domaine ne dépasse jamais cette capacité ;
        // le reste (si le Sous-domaine peut en porter davantage, jusqu'à 50) est complété
        // ci-dessous par des appels supplémentaires en lots équilibrés.
        $firstBatch = array_slice($response['subjects'], 0, TaxonomyConfig::MAX_SUBJECTS_PER_GEMINI_CALL);

        $subdomain = DB::transaction(function () use ($occurrence, $response, $firstBatch) {
            $locked = $this->repo->lockV11Occurrence((int) $occurrence->id);
            if ($locked === null) {
                throw new RuntimeException("Occurrence Taxonomy {$occurrence->id} introuvable.");
            }

            $subdomain = $this->repo->findV11Subdomain((int) $occurrence->id);
            if ($subdomain !== null) {
                return $subdomain;
            }

            $subdomain = $this->repo->createV11Subdomain(
                (int) $occurrence->id,
                $response['subdomain'],
            );
            $this->repo->createV11Subjects((int) $subdomain->id, $firstBatch);
            $this->repo->markV11OccurrenceOpen((int) $occurrence->id);

            return $subdomain;
        });

        $this->completeV11SubjectsInBalancedBatches($occurrence, $subdomain, $domainCode, $contract);

        return $subdomain;
    }

    /**
     * DEC-100 : complète le SubjectBank d'un Sous-domaine V11 en lots équilibrés
     * d'au plus MAX_SUBJECTS_PER_GEMINI_CALL Subjects par appel Gemini, jusqu'à
     * MAX_SUBJECTS_PER_SUBDOMAIN ou jusqu'à ce que Gemini déclare NO_MORE_SUBJECTS.
     *
     * DEC-098 : ne force jamais un quota — un lot vide ou NO_MORE_SUBJECTS arrête
     * la complétion sans erreur ; un Sous-domaine avec moins de 50 Subjects
     * n'est jamais une anomalie.
     */
    private function completeV11SubjectsInBalancedBatches(
        object $occurrence,
        object $subdomain,
        string $domainCode,
        DepthContract $contract,
    ): void {
        $contextType = 'SUBJECTS';
        $contextKey  = 'subdomain:' . $subdomain->id;

        while (true) {
            $existingSubjects = array_map(
                fn($s) => $s->subject_name,
                $this->repo->getV11SubjectsForSubdomain((int) $subdomain->id)
            );

            $remaining = TaxonomyConfig::MAX_SUBJECTS_PER_SUBDOMAIN - count($existingSubjects);
            if ($remaining <= 0) {
                return;
            }

            // DEC-100 : "préparation équilibrée des lots avec minimum d'appels" —
            // pas seulement un plafond technique par appel. callsRemaining est le
            // nombre MINIMAL d'appels nécessaires pour couvrir $remaining (inchangé) ;
            // batchSize répartit $remaining aussi également que possible sur ces
            // appels (écart de taille ≤ 1 entre lots), recalculé à chaque itération
            // sur le $remaining réellement observé — jamais 10+10+3, mais 8+8+7.
            $callsRemaining = (int) ceil($remaining / TaxonomyConfig::MAX_SUBJECTS_PER_GEMINI_CALL);
            $batchSize      = (int) ceil($remaining / $callsRemaining);
            $memory    = $this->repo->getV11Memory((int) $occurrence->id, $contextType, $contextKey);
            $attempt   = $this->repo->nextV11AttemptNumber((int) $occurrence->id, $contextType, $contextKey);

            $response = $this->gemini->generateSubjects(
                domain: $domainCode,
                domainLabel: $this->domainLabel($domainCode),
                subDomain: (string) $subdomain->subdomain_name,
                contract: $contract,
                existingSubjects: $existingSubjects,
                consumedSubjects: [],
                remainingCapacity: $batchSize,
                cumulativeMemory: $memory,
            );

            $newNames = [];
            foreach ($response['candidates'] as $candidate) {
                $value = trim($candidate['value'] ?? '');
                if ($value === '' || in_array($value, $existingSubjects, true) || in_array($value, $newNames, true)) {
                    continue;
                }
                $newNames[] = $value;
            }

            if ($newNames !== []) {
                $this->repo->createV11Subjects((int) $subdomain->id, $newNames);
            }

            $this->repo->persistV11Memory(
                (int) $occurrence->id,
                $contextType,
                $contextKey,
                $attempt,
                array_column($response['candidates'], 'value'),
                $newNames,
                [],
                $newNames,
                $response['status'] === 'NO_MORE_SUBJECTS',
            );

            if ($response['status'] === 'NO_MORE_SUBJECTS' || $newNames === []) {
                return;
            }
        }
    }

    private function generateV11IdeasForSubject(
        object $occurrence,
        object $subdomain,
        object $subject,
        string $domainCode,
        DepthContract $contract,
    ): void {
        $contextType = 'IDEAS';
        $contextKey = 'subject:' . $subject->id;
        $memory = $this->repo->getV11Memory((int) $occurrence->id, $contextType, $contextKey);
        $attempt = $this->repo->nextV11AttemptNumber((int) $occurrence->id, $contextType, $contextKey);
        $passIdeas = $this->repo->getV11PassIdeaValues((int) $subject->id);
        $failDetails = $this->repo->getV11FailIdeaDetails((int) $subject->id);
        $failIdeas = array_values(array_filter(array_column($failDetails, 'value')));
        $coveredDirections = [];

        foreach ($memory as $entry) {
            $coveredDirections = array_merge($coveredDirections, $entry['covered_directions']);
        }

        // DEC-102 : plafond 1..5 Idées PASS par Sujet. Un Sujet ayant déjà atteint
        // le plafond n'appelle plus Gemini — il est simplement marqué épuisé sans
        // que cela soit traité comme une anomalie (des idées PASS existent déjà).
        $remainingSlots = TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT - count($passIdeas);
        if ($remainingSlots <= 0) {
            $this->repo->markV11SubjectIdeaGenerationExhausted((int) $subject->id);
            return;
        }

        $response = $this->gemini->generateIdeas(
            domain: $domainCode,
            domainLabel: $this->domainLabel($domainCode),
            subDomain: (string) $subdomain->subdomain_name,
            subject: (string) $subject->subject_name,
            contract: $contract,
            passIdeas: $passIdeas,
            failIdeas: $failIdeas,
            failDetails: $failDetails,
            remainingSlots: $remainingSlots,
            cumulativeMemory: $memory,
        );

        $newPassIdeas = [];
        $newFailDetails = [];
        foreach ($response['candidates'] as $candidate) {
            // Les candidats Gemini sont normalisés en {value: string} par
            // TaxonomyGeminiClient::parseGeminiResponse() — extraire la valeur
            // avant toute validation ou persistance (validateOne() et
            // persistV11*Idea() attendent une string, pas un tableau).
            $value = trim($candidate['value'] ?? '');
            if ($value === '') {
                continue;
            }

            // DEC-102 : ne jamais dépasser le plafond de 5 Idées PASS par Sujet,
            // même si Gemini a proposé plus de candidats que de slots restants.
            if (count($passIdeas) + count($newPassIdeas) >= TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT) {
                break;
            }

            $result = $this->validator->validateOne(
                $value,
                $this->domainLabel($domainCode),
                (string) $subdomain->subdomain_name,
                (string) $subject->subject_name,
                $contract,
                array_merge($passIdeas, $newPassIdeas),
                array_merge($failIdeas, array_column($newFailDetails, 'value')),
                $coveredDirections,
            );

            if ($result->isPass()) {
                $newPassIdeas[] = $value;
                $coveredDirections[] = $value;
                continue;
            }

            $newFailDetails[] = [
                'value'         => $value,
                'reason'        => $result->reason ?? 'UNKNOWN',
                'conflict_with' => $result->conflictWith,
            ];
        }

        $roundState = DB::transaction(function () use (
            $occurrence,
            $subject,
            $contextType,
            $contextKey,
            $attempt,
            $response,
            $newPassIdeas,
            $newFailDetails,
            $coveredDirections,
        ) {
            foreach ($newPassIdeas as $idea) {
                $this->repo->persistV11PassIdea((int) $subject->id, $idea);
            }

            foreach ($newFailDetails as $failure) {
                $this->repo->persistV11FailIdea(
                    (int) $subject->id,
                    $failure['value'],
                    $failure['reason'],
                    $failure['conflict_with'],
                );
            }

            // DEC-102 : la décision porte sur le total réellement persisté,
            // jamais uniquement sur les nouvelles PASS de ce round.
            $totalPass = count($this->repo->getV11PassIdeaValues((int) $subject->id));
            $generationExhausted = (
                $totalPass >= TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT
                || (
                    $totalPass > 0
                    && (
                        $response['status'] === 'NO_MORE_IDEAS'
                        || $attempt >= TaxonomyConfig::MAX_DOMINANT_IDEA_GENERATION_ATTEMPTS
                    )
                )
            );

            $this->repo->incrementV11SubjectIdeaAttempts((int) $subject->id);
            $this->repo->persistV11Memory(
                (int) $occurrence->id,
                $contextType,
                $contextKey,
                $attempt,
                array_column($response['candidates'], 'value'),
                $newPassIdeas,
                $newFailDetails,
                $coveredDirections,
                $generationExhausted,
            );

            if ($generationExhausted) {
                $this->repo->markV11SubjectIdeaGenerationExhausted((int) $subject->id);
            }

            return [
                'total_pass'          => $totalPass,
                'generation_exhausted' => $generationExhausted,
            ];
        });

        if ($roundState['total_pass'] === 0) {
            throw new TaxonomyPreparationException(
                "Taxonomy v1.1: zéro Idea PASS total pour Subject {$subject->id}; "
                . "ce n'est pas un épuisement."
            );
        }

        // Avec 1..4 PASS, une reprise possible sans nouveau PASS reste une
        // anomalie. NO_MORE_IDEAS et la limite de tentatives sont, eux, normaux.
        if (! $roundState['generation_exhausted'] && $newPassIdeas === []) {
            throw new TaxonomyPreparationException(
                "Taxonomy v1.1: aucune nouvelle Idea PASS exploitable pour Subject {$subject->id}; "
                . "une reprise reste nécessaire."
            );
        }
    }

    private function fillBlueprintFromV11Idea(KernelBlueprint $blueprint, object $idea): void
    {
        $blueprint->fillTaxonomy(
            (string) $idea->subdomain_name,
            (string) $idea->subject_name,
            (string) $idea->idea_value,
        );
    }

    private function fillBlueprintFromV11Assignment(KernelBlueprint $blueprint, object $assignment): void
    {
        $blueprint->fillTaxonomy(
            (string) $assignment->subdomain_active,
            (string) $assignment->subject_active,
            (string) $assignment->dominant_idea_active,
        );
    }

    /**
     * Warm-up opérateur v1.1: prépare une occurrence et au moins le nombre
     * demandé de Subjects avec une Idea PASS disponible, sans sélectionner ni
     * consommer d'IdeaSlot et sans écrire de Blueprint.
     */
    public function warmUpV11Cell(int $depth, string $domainCode, int $targetSubjectsWithIdeas = 1): int
    {
        $contract = DepthContractRegistry::get($depth);
        $target = max(1, $targetSubjectsWithIdeas);
        $iterations = 0;

        while (
            $this->repo->countV11SubjectsWithAvailableIdeas($depth, $domainCode) < $target
            && $iterations++ < self::MAX_FILL_ITERATIONS
        ) {
            $occurrence = $this->repo->findOrCreateV11Occurrence($depth, $domainCode);
            if ($occurrence->status === 'BLOCKED') {
                throw new TaxonomyBlockedException(
                    "Taxonomy v1.1 est BLOCKED pour Depth {$depth} / Domaine {$domainCode}."
                );
            }

            try {
                $subdomain = $this->ensureV11SubdomainAndSubjects($occurrence, $domainCode, $contract);
                $subject = $this->repo->findV11SubjectNeedingIdeas((int) $occurrence->id);

                if ($subject === null) {
                    break;
                }

                $this->generateV11IdeasForSubject(
                    $occurrence,
                    $subdomain,
                    $subject,
                    $domainCode,
                    $contract,
                );
                $this->repo->resetV11TechnicalFailures((int) $occurrence->id);
            } catch (TaxonomyGeminiTechnicalException|TaxonomyPreparationException $exception) {
                $blocked = $this->repo->recordV11TechnicalFailure(
                    (int) $occurrence->id,
                    $exception->getMessage(),
                );

                if ($blocked) {
                    throw new TaxonomyBlockedException(
                        "Taxonomy v1.1 a atteint trois opérations intellectuelles non résolues "
                        . "pour Depth {$depth} / Domaine {$domainCode}.",
                        0,
                        $exception,
                    );
                }

                throw $exception;
            }
        }

        return $this->repo->countV11SubjectsWithAvailableIdeas($depth, $domainCode);
    }

    /**
     * TOMBSTONE v1.0 — ne pas utiliser, ne pas restaurer.
     * Chemin officiel : assignToBlueprint().
     */
    public function peekNext(int $depth, string $domainCode): ?array
    {
        throw new RuntimeException(
            'Taxonomy v1.0 (peekNext) est un chemin interdit. '
            . 'assignToBlueprint() est le seul chemin Taxonomy v1.1 autorisé.'
        );
    }

    /**
     * TOMBSTONE v1.0 — ne pas utiliser, ne pas restaurer.
     * Chemin officiel : assignToBlueprint().
     */
    public function confirmConsumed(int $depth, string $domainCode): void
    {
        throw new RuntimeException(
            'Taxonomy v1.0 (confirmConsumed) est un chemin interdit. '
            . 'assignToBlueprint() est le seul chemin Taxonomy v1.1 autorisé.'
        );
    }

    /**
     * TOMBSTONE v1.0 — ne pas utiliser, ne pas restaurer.
     * Chemin officiel : assignToBlueprint().
     */
    public function isExhausted(int $depth, string $domainCode): bool
    {
        throw new RuntimeException(
            'Taxonomy v1.0 (isExhausted) est un chemin interdit. '
            . 'assignToBlueprint() est le seul chemin Taxonomy v1.1 autorisé.'
        );
    }

    private function domainLabel(string $domainCode): string
    {
        return self::DOMAIN_LABELS[$domainCode] ?? ucfirst($domainCode);
    }
}
