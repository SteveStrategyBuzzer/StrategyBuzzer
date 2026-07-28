<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\Contracts;

use App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult;
use App\Services\QuestionBank\Rotation\LearningDirectionRegistry;

/**
 * Contrat d'intégration de la vérification KLD dans le pipeline Kernel.
 *
 * Doit être appelé depuis KernelPipelineOrchestrator après que Taxonomy a fourni
 * un territoire complet (sub_domain + subject + dominant_idea).
 *
 * RÈGLE OFFICIELLE (DEC-KLD-01) :
 *   - PASS  → direction pédagogique inédite → KEY_STRUCTURE vérifie ensuite
 *   - FAIL  → doublon pédagogique certain → pipeline s'arrête, PAS de confirmConsumed()
 *   - REVIEW_STRUCTURE → KEY_STRUCTURE tranche (traité comme FAIL par l'orchestrateur
 *     jusqu'à implantation complète de KEY_STRUCTURE)
 *
 * @see KeyStructurePipelineGateInterface — étape suivante après PASS KLD
 */
interface KernelKldCheckInterface
{
    /**
     * Vérifie si la direction pédagogique du territoire est acceptable.
     *
     * @param array{
     *     sub_domain: string,
     *     subject: string,
     *     dominant_idea?: string,
     *     dominant_idea_active?: string,
     *     knowledge_frequency?: int
     * } $territory
     *
     * @return LearningDirectionResult  status = 'pass' | 'fail' | 'review_structure'
     */
    public function check(
        array                     $territory,
        string                    $domainCode,
        int                       $depth,
        LearningDirectionRegistry $registry,
    ): LearningDirectionResult;
}
