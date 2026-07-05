<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Knowledge\LearningDirectionLexicon;
use App\Services\QuestionBank\Knowledge\LearningIdeaFamilyIndex;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionInput;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult;

/**
 * KEY_LEARNING_DIRECTION
 *
 * Garde d'entrée du chargeur d'idées dans le pipeline Taxonomy.
 *
 * Mission : empêcher qu'un même sujet enseigne deux fois la même direction
 * d'apprentissage sous une formulation différente.
 *
 * Mécanisme en 7 étapes (déterministe, sans DB, sans IA) :
 *   1. Normaliser le sujet                           → subject_key
 *   2. Normaliser l'idée                             → idea_key
 *   3. Résoudre via lexique de synonymes             → idea_canonical_key
 *   4. Construire direction_key = subject::canonical
 *   5. direction_key déjà dans registry ?            → FAIL
 *   6. Idée dans même famille qu'une direction existante pour ce sujet ? → REVIEW_STRUCTURE
 *   7. Sinon                                         → PASS
 *
 * 3 sorties :
 *   PASS             — direction inédite, KEY_STRUCTURE vérifie la structure
 *   FAIL             — doublon certain (même dossier pédagogique)
 *   REVIEW_STRUCTURE — risque contextuel (même famille), KEY_STRUCTURE tranche
 *
 * Ce service ne lit jamais la DB.
 * Ce service ne génère jamais de hash.
 * Ce service ne juge jamais si une idée est pédagogiquement bonne.
 */
final class KeyLearningDirection
{
    public function __construct(
        private readonly LearningDirectionLexicon $lexicon,
        private readonly LearningIdeaFamilyIndex  $familyIndex,
    ) {}

    public function check(
        LearningDirectionInput    $input,
        LearningDirectionRegistry $registry,
    ): LearningDirectionResult {
        // Étape 1 — normaliser le sujet
        $subjectKey = $this->lexicon->normalize($input->subject);

        // Étape 2 — normaliser l'idée
        $ideaKey = $this->lexicon->normalize($input->dominantIdea);

        // Étape 3 — résoudre via synonymes directs → forme canonique
        $ideaCanonical = $this->lexicon->resolve($input->dominantIdea);

        // Étape 4 — construire la clé du dossier pédagogique
        $directionKey = $subjectKey . '::' . $ideaCanonical;

        // Étape 5 — dossier exact déjà enregistré ?
        if ($registry->contains($directionKey)) {
            $synonymDetected = ($ideaCanonical !== $ideaKey) ? $ideaCanonical : null;

            return LearningDirectionResult::fail(
                reason:          LearningDirectionResult::REASON_DIRECT_PAIR_DUPLICATE,
                normalizedSubject: $subjectKey,
                normalizedIdea:  $ideaCanonical,
                synonymDetected: $synonymDetected,
            );
        }

        // Étape 6 — même famille qu'une direction existante pour ce sujet ?
        $existingIdeas = $registry->getIdeasForSubject($subjectKey);

        foreach ($existingIdeas as $existingIdea) {
            if ($this->familyIndex->sameFamily($input->domainCode, $ideaCanonical, $existingIdea)) {
                return LearningDirectionResult::reviewStructure($subjectKey, $ideaCanonical);
            }
        }

        // Étape 7 — direction inédite
        return LearningDirectionResult::pass($subjectKey, $ideaCanonical);
    }
}
