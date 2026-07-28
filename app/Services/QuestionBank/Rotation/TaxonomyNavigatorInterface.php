<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

/**
 * TaxonomyNavigatorInterface — contrat de navigation taxonomique.
 *
 * Expose les deux opérations utilisées par KernelPipelineOrchestrator :
 *   - peekNext()       : observe le prochain sujet sans avancer le curseur.
 *   - confirmConsumed(): avance le curseur d'un sujet.
 *
 * TaxonomyProgressManager (final) l'implémente en production.
 * Des stubs peuvent l'implémenter librement en test.
 */
interface TaxonomyNavigatorInterface
{
    /**
     * Retourne le prochain territoire disponible pour ce depth × domain.
     * Retourne null si le bassin est EXHAUSTED (signal EMPTY pour l'orchestrateur).
     *
     * Idempotent : deux appels successifs sans confirmConsumed() retournent le même sujet.
     *
     * @return array{
     *     sub_domain:           string,
     *     subject:              string,
     *     dominant_idea?:       string,
     *     dominant_idea_active?: string,
     * }|null
     */
    public function peekNext(int $depth, string $domainCode): ?array;

    /**
     * Avance le curseur d'UN sujet.
     * Appelé uniquement après engagement réussi du Blueprint.
     */
    public function confirmConsumed(int $depth, string $domainCode): void;
}
