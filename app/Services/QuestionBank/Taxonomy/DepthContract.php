<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

/**
 * DepthContract — contrat qualitatif d'un Depth.
 *
 * Contient les trois familles métier :
 *   - subject_profile    : niveau de spécialisation du Sujet
 *   - knowledge_frequency: notoriété attendue de l'Idée Dominante (échelle 1–8)
 *   - forbidden_rules    : ce qui est interdit à ce niveau
 *
 * Immuable après construction.
 * Source de vérité : DepthContractRegistry.
 */
final class DepthContract
{
    /**
     * @param int    $depth
     * @param string $subjectProfileLabel       Ex: "Grand public"
     * @param string $subjectProfileDescription Description du niveau de spécialisation
     * @param int    $knowledgeFrequencyMin      Valeur min sur l'échelle 1–8
     * @param int    $knowledgeFrequencyMax      Valeur max sur l'échelle 1–8
     * @param string $knowledgeFrequencyLabel    Ex: "Très commune"
     * @param string $knowledgeFrequencyDesc     Description de la notoriété attendue
     * @param string[] $forbiddenRules           Règles interdites à ce Depth
     * @param string $referenceProfile           Profil de référence du public cible
     */
    public function __construct(
        public readonly int    $depth,
        public readonly string $subjectProfileLabel,
        public readonly string $subjectProfileDescription,
        public readonly int    $knowledgeFrequencyMin,
        public readonly int    $knowledgeFrequencyMax,
        public readonly string $knowledgeFrequencyLabel,
        public readonly string $knowledgeFrequencyDesc,
        public readonly array  $forbiddenRules,
        public readonly string $referenceProfile,
    ) {}

    /**
     * Retourne une description textuelle compacte pour les prompts Gemini.
     */
    public function toPromptText(): string
    {
        $kf    = $this->knowledgeFrequencyMin === $this->knowledgeFrequencyMax
                    ? (string) $this->knowledgeFrequencyMin
                    : "{$this->knowledgeFrequencyMin}–{$this->knowledgeFrequencyMax}";

        $rules = implode('; ', $this->forbiddenRules);

        return <<<TEXT
DEPTH {$this->depth} CONTRACT:
  subject_profile:
    label: {$this->subjectProfileLabel}
    description: {$this->subjectProfileDescription}
    reference: {$this->referenceProfile}
  knowledge_frequency:
    valeur: {$kf}
    label: {$this->knowledgeFrequencyLabel}
    description: {$this->knowledgeFrequencyDesc}
  forbidden_rules: {$rules}
TEXT;
    }
}
