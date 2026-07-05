<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

/**
 * Registre des dossiers pédagogiques validés pour une session de remplissage.
 *
 * Maintient deux index en mémoire :
 *   1. direction_key (subject_key::idea_canonical_key) → présence booléenne
 *   2. subject_key → liste des idea_canonical_keys déjà validées
 *
 * Pas de DB. Pas de hash. Réinitialisé à chaque nouvelle session Taxonomy.
 */
final class LearningDirectionRegistry
{
    /** @var array<string, true> */
    private array $directions = [];

    /** @var array<string, list<string>> */
    private array $subjectIdeas = [];

    /**
     * Enregistre une direction validée dans le registre.
     */
    public function add(string $directionKey, string $subjectKey, string $ideaCanonicalKey): void
    {
        $this->directions[$directionKey]         = true;
        $this->subjectIdeas[$subjectKey][]       = $ideaCanonicalKey;
    }

    /**
     * Vérifie si une direction_key exacte existe déjà.
     */
    public function contains(string $directionKey): bool
    {
        return isset($this->directions[$directionKey]);
    }

    /**
     * Retourne les idea_canonical_keys déjà validées pour un sujet donné.
     *
     * @return list<string>
     */
    public function getIdeasForSubject(string $subjectKey): array
    {
        return $this->subjectIdeas[$subjectKey] ?? [];
    }

    /**
     * Nombre total de directions enregistrées.
     */
    public function count(): int
    {
        return count($this->directions);
    }
}
