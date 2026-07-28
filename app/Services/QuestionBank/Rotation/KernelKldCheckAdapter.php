<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\Contracts\KernelKldCheckInterface;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionInput;
use App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult;

/**
 * Adaptateur entre KernelPipelineOrchestrator et KeyLearningDirection.
 *
 * Responsabilités :
 *   - Construire LearningDirectionInput à partir du territoire Taxonomy
 *   - Déléguer à KeyLearningDirection::check()
 *   - Ne modifier jamais la logique métier de KLD
 *
 * Prérequis de l'appel :
 *   - territory['dominant_idea'] ou territory['dominant_idea_active'] doit être présent
 *   - Si absent, l'orchestrateur a déjà déclaré PIPELINE_BLOCKED_AWAITING_IDEA_SLOT_LOADER
 *     et n'atteint pas cet adaptateur
 */
final class KernelKldCheckAdapter implements KernelKldCheckInterface
{
    public function __construct(
        private readonly KeyLearningDirection $kld,
    ) {}

    public function check(
        array                     $territory,
        string                    $domainCode,
        int                       $depth,
        LearningDirectionRegistry $registry,
    ): LearningDirectionResult {
        $input = new LearningDirectionInput(
            depth:              $depth,
            domainCode:         $domainCode,
            subDomain:          $territory['sub_domain']   ?? '',
            subject:            $territory['subject']      ?? '',
            dominantIdea:       $territory['dominant_idea'] ?? $territory['dominant_idea_active'] ?? '',
            knowledgeFrequency: (int) ($territory['knowledge_frequency'] ?? 0),
        );

        return $this->kld->check($input, $registry);
    }
}
