<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

/**
 * FailReason — raisons structurées de rejet d'une Idée Dominante.
 *
 * Chaque constante est un code machine utilisé par ValidationResult et
 * persisté dans taxonomy_v11_ideas.fail_reason.
 *
 * Ces codes sont transmis dans la mémoire cumulative Gemini pour guider
 * les appels suivants.
 *
 * ── CODES PHP-ENFORCED ─────────────────────────────────────────────────────
 * Ces codes sont produits par ValidationDominantIdeas::validateOne() ou
 * validateDiversity(). Ils ont une implémentation déterministe en PHP.
 *
 * ── CODES GEMINI-PROMPT-ENFORCED ──────────────────────────────────────────
 * Ces codes existent dans le système de mémoire cumulative pour permettre
 * à Gemini de comprendre pourquoi des idées ont été rejetées, et guider
 * la prochaine génération. Ils NE SONT PAS évalués par du code PHP — ils
 * sont enforced par la généalogie dans le prompt et la mémoire cumulative.
 * Ils peuvent apparaître dans la banque si une version future du pipeline
 * les implémente en PHP, ou s'ils sont injectés manuellement.
 */
final class FailReason
{
    // =========================================================================
    // PHP-ENFORCED — validés déterministement dans ValidationDominantIdeas
    // =========================================================================

    /**
     * [PHP] Idée identique à une idée déjà présente (comparaison textuelle exacte).
     */
    public const DUPLICATE = 'DUPLICATE';

    /**
     * [PHP] Idée synonyme ou reformulation lexicale d'une idée existante.
     */
    public const LEXICAL_EQUIVALENCE = 'LEXICAL_EQUIVALENCE';

    /**
     * [PHP] Idée partageant la même direction conceptuelle clé qu'une idée existante.
     * Détecté via les mots-clés dominants (≥5 chars) communs ou leurs racines.
     */
    public const CONCEPTUAL_COLLISION = 'CONCEPTUAL_COLLISION';

    /**
     * [PHP] Idée qui est une catégorie générique d'interrogation, pas une connaissance réelle.
     * Ex: "Date", "Personnages", "Causes", "Conséquences".
     */
    public const GENERIC_CATEGORY = 'GENERIC_CATEGORY';

    /**
     * [PHP] Idée qui est une méta-description, pas une connaissance dominante réelle.
     * Ex: "Histoire de X", "Rôle de Y", "Impact de Z".
     */
    public const NOT_DOMINANT = 'NOT_DOMINANT';

    /**
     * [PHP] Idée couvrant plusieurs directions (conjonction entre deux concepts distincts).
     * Ex: "Économie et gouvernement", "Causes et conséquences".
     */
    public const TOO_BROAD = 'TOO_BROAD';

    /**
     * [PHP] Idée dont la complexité est manifestement incompatible avec le Depth.
     * Depth bas : vocabulaire ultra-spécialisé. Depth haut : concept trop trivial.
     */
    public const OUTSIDE_DEPTH = 'OUTSIDE_DEPTH';

    /**
     * [PHP] Idée qui répète le Sujet (même territoire).
     */
    public const SUBJECT_REPETITION = 'SUBJECT_REPETITION';

    /**
     * [PHP] Ensemble d'idées trop concentré dans un seul axe (diversité insuffisante).
     * Validé AVANT persistance via validateDiversity() pour ne jamais persister une PASS invalide.
     */
    public const SET_DIVERSITY_COLLISION = 'SET_DIVERSITY_COLLISION';

    /**
     * [PHP] Direction de connaissance déjà couverte dans l'historique.
     */
    public const ALREADY_COVERED = 'ALREADY_COVERED';

    /**
     * [PHP] Idée non minimale/irréductible — formulée comme une phrase.
     */
    public const FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION = 'FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION';

    // =========================================================================
    // GEMINI-PROMPT-ENFORCED — enforced via généalogie + mémoire cumulative
    // Ces codes sont RÉSERVÉS pour la mémoire Gemini et la classification manuelle.
    // Ils ne sont PAS produits par du code PHP actuellement.
    // =========================================================================

    /**
     * [GEMINI] Idée hors de l'angle du Domaine.
     * Enforced par la généalogie explicite dans le prompt (DOMAINE → SOUS-DOMAINE → SUJET).
     */
    public const OUTSIDE_DOMAIN = 'OUTSIDE_DOMAIN';

    /**
     * [GEMINI] Idée incohérente avec le Sous-domaine actif.
     * Enforced par la généalogie explicite dans le prompt.
     */
    public const OUTSIDE_SUBDOMAIN = 'OUTSIDE_SUBDOMAIN';

    /**
     * [GEMINI] Idée en collision pédagogique avec une direction déjà couverte.
     * Enforced via mémoire cumulative des directions couvertes dans le prompt.
     */
    public const PEDAGOGICAL_COLLISION = 'PEDAGOGICAL_COLLISION';

    /**
     * [GEMINI] Idée trop étroite — trop spécifique pour être dominante.
     * Enforced par le prompt (grain de spécificité = FORMAT_MINIMAL_IRREDUCTIBLE avancé).
     */
    public const TOO_NARROW = 'TOO_NARROW';

    // =========================================================================
    // Utilitaires de classification
    // =========================================================================

    /**
     * Retourne les codes évalués déterministement en PHP par ValidationDominantIdeas.
     *
     * @return string[]
     */
    public static function phpEnforced(): array
    {
        return [
            self::DUPLICATE,
            self::LEXICAL_EQUIVALENCE,
            self::CONCEPTUAL_COLLISION,
            self::GENERIC_CATEGORY,
            self::NOT_DOMINANT,
            self::TOO_BROAD,
            self::OUTSIDE_DEPTH,
            self::SUBJECT_REPETITION,
            self::SET_DIVERSITY_COLLISION,
            self::ALREADY_COVERED,
            self::FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION,
        ];
    }

    /**
     * Retourne les codes enforced exclusivement via le prompt Gemini.
     *
     * @return string[]
     */
    public static function geminiEnforced(): array
    {
        return [
            self::OUTSIDE_DOMAIN,
            self::OUTSIDE_SUBDOMAIN,
            self::PEDAGOGICAL_COLLISION,
            self::TOO_NARROW,
        ];
    }

    /**
     * Retourne tous les codes connus (PHP + Gemini).
     *
     * @return string[]
     */
    public static function all(): array
    {
        return array_merge(self::phpEnforced(), self::geminiEnforced());
    }
}
