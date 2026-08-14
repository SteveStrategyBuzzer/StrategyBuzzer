<?php
// @deprecated LEGACY — Architecture V1 SUPERSEDED.
// DomainExhaustionChecker (PULL) remplacé par PUSH (Taxonomy → receiveDomainExhausted).
// Conservé uniquement parce que TaxonomyOrchestrator l'implémente encore.
// Suppression physique : après refactoring TaxonomyOrchestrator (hors périmètre LOT A/B).

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

/**
 * DomainExhaustionChecker
 *
 * Contrat minimal exposé par TaxonomyProgressManager à l'usage de KernelRotationPlanner.
 *
 * Règle d'utilisation :
 *   KernelRotationPlanner interroge UN seul domaine par rotation — jamais les 8 en même temps.
 *   Il appelle isExhausted() sur le current_domain de son état persisté, puis décide
 *   d'incrémenter completed_domains et d'avancer le DomainCycle si la réponse est true.
 */
interface DomainExhaustionChecker
{
    /**
     * Retourne true si le bassin Taxonomy pour ce couple est entièrement épuisé.
     *
     * "Épuisé" signifie :
     *   - tous les sous-domaines parcourus
     *   - tous les sujets de chaque sous-domaine parcourus
     *   - toutes les idées dominantes de chaque sujet consommées
     *   - statut 'exhausted' enregistré dans taxonomy_progress
     *
     * Retourne false si le bassin n'a jamais été initialisé (pas encore de ligne).
     */
    public function isExhausted(int $depth, string $domainCode): bool;
}
