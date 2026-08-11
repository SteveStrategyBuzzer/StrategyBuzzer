<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use InvalidArgumentException;

/**
 * DepthContractRegistry — source unique de vérité des DepthContracts.
 *
 * Couvre les 7 Depths officiels : 2, 4, 6, 7, 8, 9, 10.
 * Fail-closed : lève InvalidArgumentException si le Depth est inconnu.
 *
 * NE PAS disperser les valeurs de contrat dans des prompts ou des classes métier.
 * Toute logique qui a besoin du contrat doit appeler DepthContractRegistry::get($depth).
 */
final class DepthContractRegistry
{
    /** @var array<int, DepthContract> */
    private static array $cache = [];

    /**
     * Retourne le DepthContract pour le Depth donné.
     *
     * @throws InvalidArgumentException si le Depth est inconnu (fail-closed)
     */
    public static function get(int $depth): DepthContract
    {
        if (isset(self::$cache[$depth])) {
            return self::$cache[$depth];
        }

        $contract = match ($depth) {
            2  => self::depth2(),
            4  => self::depth4(),
            6  => self::depth6(),
            7  => self::depth7(),
            8  => self::depth8(),
            9  => self::depth9(),
            10 => self::depth10(),
            default => throw new InvalidArgumentException(
                "DepthContractRegistry: Depth inconnu [{$depth}]. "
                . 'Depths supportés : 2, 4, 6, 7, 8, 9, 10.'
            ),
        };

        self::$cache[$depth] = $contract;

        return $contract;
    }

    /**
     * Retourne la liste de tous les Depths officiels supportés.
     *
     * @return int[]
     */
    public static function officialDepths(): array
    {
        return [2, 4, 6, 7, 8, 9, 10];
    }

    /**
     * Retourne true si le Depth est dans la liste officielle.
     */
    public static function isKnown(int $depth): bool
    {
        return in_array($depth, self::officialDepths(), true);
    }

    // =========================================================================
    // Définitions des contrats par Depth
    // =========================================================================

    private static function depth2(): DepthContract
    {
        return new DepthContract(
            depth: 2,
            subjectProfileLabel: 'Grand public',
            subjectProfileDescription: 'Territoire accessible sans connaissance préalable. '
                . 'Sous-domaines généraux, sujets enseignés ou connus largement, '
                . 'idées évidentes après identification du Sujet.',
            knowledgeFrequencyMin: 1,
            knowledgeFrequencyMax: 2,
            knowledgeFrequencyLabel: 'Très commune',
            knowledgeFrequencyDesc: 'Territoire immédiatement reconnaissable. '
                . 'Sous-domaines larges et universels, sujets très connus, '
                . 'idées dominantes immédiatement accessibles.',
            forbiddenRules: [
                'sujet rare ou peu connu',
                'formulation abstraite ou technique',
                'piège complexe ou trompeur',
                'connaissance nécessitant une base dans le domaine',
                'faits triviaux sans intérêt pédagogique',
            ],
            referenceProfile: 'Adolescent moyen ayant reçu une éducation générale',
        );
    }

    private static function depth4(): DepthContract
    {
        return new DepthContract(
            depth: 4,
            subjectProfileLabel: 'Culture générale',
            subjectProfileDescription: 'Territoire accessible à une personne curieuse et cultivée. '
                . 'Sous-domaines toujours larges mais plus ciblés, '
                . 'idées moins immédiates que Depth 2.',
            knowledgeFrequencyMin: 2,
            knowledgeFrequencyMax: 3,
            knowledgeFrequencyLabel: 'Commune',
            knowledgeFrequencyDesc: 'Territoire de culture générale. '
                . 'Sujets connus d\'une personne cultivée, '
                . 'idées moins immédiates que Depth 2.',
            forbiddenRules: [
                'notoriété universelle excessive (trop simple)',
                'connaissance nécessitant une spécialisation',
                'sujet pointu ou de niche',
                'raisonnement multi-étapes',
            ],
            referenceProfile: 'Fin d\'adolescence, lecteur de vulgarisation ou documentaire',
        );
    }

    private static function depth6(): DepthContract
    {
        return new DepthContract(
            depth: 6,
            subjectProfileLabel: 'Initié',
            subjectProfileDescription: 'Territoire connu d\'une personne ayant déjà exploré le domaine. '
                . 'Sous-domaines plus spécialisés, sujets pas forcément connus du grand public, '
                . 'idées nécessitant déjà une compréhension du territoire.',
            knowledgeFrequencyMin: 4,
            knowledgeFrequencyMax: 4,
            knowledgeFrequencyLabel: 'Modérément connue',
            knowledgeFrequencyDesc: 'Territoire nécessitant déjà un intérêt pour le domaine.',
            forbiddenRules: [
                'sujet trop spécialisé hors du niveau initié',
                'piège gratuit non reconstructible',
            ],
            referenceProfile: 'Amateur régulier du domaine',
        );
    }

    private static function depth7(): DepthContract
    {
        return new DepthContract(
            depth: 7,
            subjectProfileLabel: 'Initié avancé',
            subjectProfileDescription: 'Territoire connu après exploration active du domaine. '
                . 'Sujets connus principalement des initiés, '
                . 'idées plus fines et moins visibles.',
            knowledgeFrequencyMin: 5,
            knowledgeFrequencyMax: 5,
            knowledgeFrequencyLabel: 'Peu commune',
            knowledgeFrequencyDesc: 'Territoire ciblé. Sous-domaines spécialisés. '
                . 'Sujets connus principalement des initiés.',
            forbiddenRules: [
                'réponse devinable sans lecture complète de la question',
            ],
            referenceProfile: 'Personne investissant du temps dans le Domaine',
        );
    }

    private static function depth8(): DepthContract
    {
        return new DepthContract(
            depth: 8,
            subjectProfileLabel: 'Spécialisé',
            subjectProfileDescription: 'Territoire normalement reconnu par quelqu\'un possédant '
                . 'une expertise réelle dans le domaine. '
                . 'Sujets peu connus hors du domaine, idées nécessitant une compréhension solide.',
            knowledgeFrequencyMin: 6,
            knowledgeFrequencyMax: 6,
            knowledgeFrequencyLabel: 'Rare',
            knowledgeFrequencyDesc: 'Territoire spécialisé. Sous-domaines fortement spécialisés.',
            forbiddenRules: [
                'pure mémorisation obscure sans enjeu pédagogique',
            ],
            referenceProfile: 'Praticien confirmé du Domaine',
        );
    }

    private static function depth9(): DepthContract
    {
        return new DepthContract(
            depth: 9,
            subjectProfileLabel: 'Expert',
            subjectProfileDescription: 'Territoire connu principalement des experts du domaine. '
                . 'Sujets peu rencontrés hors des cercles spécialisés, '
                . 'idées très ciblées mais toujours vérifiables.',
            knowledgeFrequencyMin: 7,
            knowledgeFrequencyMax: 7,
            knowledgeFrequencyLabel: 'Très rare',
            knowledgeFrequencyDesc: 'Territoire avancé. Sous-domaines de niche.',
            forbiddenRules: [
                'ambiguïté non résoluble par un expert',
            ],
            referenceProfile: 'Expert reconnu du domaine',
        );
    }

    private static function depth10(): DepthContract
    {
        return new DepthContract(
            depth: 10,
            subjectProfileLabel: 'Expert avancé',
            subjectProfileDescription: 'Territoire réservé aux connaissances les plus avancées du domaine. '
                . 'Sujets exceptionnellement ciblés, idées toujours vérifiables, exploitables et documentées.',
            knowledgeFrequencyMin: 8,
            knowledgeFrequencyMax: 8,
            knowledgeFrequencyLabel: 'Extrêmement rare',
            knowledgeFrequencyDesc: 'Territoire à la frontière supérieure du domaine. '
                . 'Sous-domaines ultra spécialisés.',
            forbiddenRules: [
                'aucune anecdote obscure non exploitable',
                'aucune curiosité non documentée',
                'question impossible ou obscure sans indice interne',
            ],
            referenceProfile: 'Référence académique ou professionnelle du domaine',
        );
    }
}
