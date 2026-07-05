<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Knowledge;

/**
 * Lexique de synonymes directs au service de KEY_LEARNING_DIRECTION.
 *
 * Responsabilité unique : résoudre une idée vers sa forme canonique.
 *   auto → voiture, char → voiture, bagnole → voiture
 *
 * Ce n'est pas une base de connaissances générale.
 * Ce n'est pas un moteur de proximité sémantique.
 * Ce n'est pas un juge de qualité pédagogique.
 */
final class LearningDirectionLexicon
{
    /**
     * Retourne la table plate : alias → canonique.
     * La clé canonique pointe vers elle-même.
     *
     * @return array<string, string>
     */
    public function getSynonyms(): array
    {
        return [
            // ── Transport ──────────────────────────────────────────
            'auto'      => 'voiture',
            'char'      => 'voiture',
            'bagnole'   => 'voiture',
            'automobile' => 'voiture',
            'voiture'   => 'voiture',

            'moto'       => 'motocyclette',
            'motocyclette' => 'motocyclette',
            'deux-roues' => 'motocyclette',

            'bus'        => 'autobus',
            'autobus'    => 'autobus',
            'car'        => 'autobus',

            'avion'      => 'avion',
            'aéronef'    => 'avion',

            'train'      => 'train',
            'locomotive' => 'train',

            'bateau'     => 'bateau',
            'navire'     => 'bateau',
            'vaisseau'   => 'bateau',

            // ── Géographie ─────────────────────────────────────────
            'capitale'          => 'capitale',
            'chef-lieu'         => 'capitale',
            'ville principale'  => 'capitale',

            // ── Sciences ───────────────────────────────────────────
            'eau'           => 'eau',
            'h2o'           => 'eau',
            'liquide aqueux' => 'eau',

            // ── Histoire ───────────────────────────────────────────
            'roi'     => 'monarque',
            'monarque' => 'monarque',
            'souverain' => 'monarque',
        ];
    }

    /**
     * Résout une idée vers sa forme canonique.
     * Si aucun synonyme n'est défini, retourne l'idée normalisée telle quelle.
     */
    public function resolve(string $idea): string
    {
        $normalized = $this->normalize($idea);
        $synonyms   = $this->getSynonyms();

        return $synonyms[$normalized] ?? $normalized;
    }

    public function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }
}
