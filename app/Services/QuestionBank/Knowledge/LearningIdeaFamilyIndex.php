<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Knowledge;

/**
 * Index des familles d'idées proches par domaine.
 *
 * Responsabilité unique : déclarer quelles idées sont pédagogiquement
 * trop proches pour un même sujet, sans être des synonymes directs.
 *
 * Exemples :
 *   transport → véhicules_routiers : [voiture, camion, autobus, pickup]
 *   transport → transport_aérien   : [avion, hélicoptère]
 *
 * KLD utilise cet index pour détecter un risque (REVIEW_STRUCTURE).
 * KEY_STRUCTURE décide si la collision est réelle.
 *
 * Ce fichier ne juge jamais la qualité pédagogique d'une idée.
 */
final class LearningIdeaFamilyIndex
{
    /**
     * @return array<string, array<string, list<string>>>
     *         domain_code → family_name → [canonical_ideas...]
     */
    public function getFamilies(): array
    {
        return [
            'transport' => [
                'vehicules_routiers'    => ['voiture', 'camion', 'autobus', 'pickup', 'motocyclette', 'utilitaire'],
                'transport_ferroviaire' => ['train', 'metro', 'tramway', 'rer'],
                'transport_aerien'      => ['avion', 'helicoptere', 'drone', 'planeur'],
                'transport_maritime'    => ['bateau', 'ferry', 'paquebot', 'sous-marin'],
                'transport_doux'        => ['velo', 'trottinette', 'marche'],
            ],
            'geographie' => [
                'divisions_administratives' => ['capitale', 'region', 'departement', 'province', 'commune'],
                'reliefs'                   => ['montagne', 'colline', 'plaine', 'plateau', 'vallee'],
                'etendues_eau'              => ['fleuve', 'riviere', 'lac', 'mer', 'ocean'],
            ],
            'sciences' => [
                'etats_matiere'   => ['solide', 'liquide', 'gaz', 'plasma'],
                'corps_simples'   => ['eau', 'oxygene', 'azote', 'carbone', 'hydrogene'],
                'forces'          => ['gravite', 'electromagnetisme', 'friction', 'tension'],
            ],
            'histoire' => [
                'figures_pouvoir' => ['monarque', 'president', 'premier_ministre', 'dictateur', 'empereur'],
                'conflits'        => ['guerre', 'bataille', 'siege', 'revolution', 'coup_etat'],
            ],
            'mathematiques' => [
                'operations'   => ['addition', 'soustraction', 'multiplication', 'division'],
                'formes_2d'    => ['carre', 'rectangle', 'triangle', 'cercle', 'hexagone'],
                'formes_3d'    => ['cube', 'sphere', 'pyramide', 'cylindre', 'cone'],
            ],
        ];
    }

    /**
     * Retourne true si ideaA et ideaB appartiennent à la même famille
     * pour ce domaine (après normalisation).
     */
    public function sameFamily(string $domainCode, string $ideaA, string $ideaB): bool
    {
        $domainCode = mb_strtolower(trim($domainCode));
        $ideaA      = mb_strtolower(trim($ideaA));
        $ideaB      = mb_strtolower(trim($ideaB));

        if ($ideaA === $ideaB) {
            return true;
        }

        $families = $this->getFamilies();

        if (!isset($families[$domainCode])) {
            return false;
        }

        foreach ($families[$domainCode] as $members) {
            if (in_array($ideaA, $members, true) && in_array($ideaB, $members, true)) {
                return true;
            }
        }

        return false;
    }
}
