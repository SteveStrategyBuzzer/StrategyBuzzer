<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

/**
 * ValidationDominantIdeas — validateur des Idées Dominantes candidates.
 *
 * Implémente deux groupes de règles :
 *
 * ── Règles PHP-enforced (deterministes, exécutées ici) ────────────────────
 *   R01  FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION — phrase trop longue ou indicateurs de phrase
 *   R02  GENERIC_CATEGORY                      — catégorie générique d'interrogation
 *   R03  SUBJECT_REPETITION                    — idée qui répète le Sujet
 *   R04  TOO_BROAD                             — idée couvrant plusieurs directions (conjonctions)
 *   R05  NOT_DOMINANT                          — méta-description (rôle/impact/histoire de X)
 *   R06  DUPLICATE                             — doublon exact avec idée existante
 *   R07  LEXICAL_EQUIVALENCE                   — synonyme/reformulation lexicale
 *   R08  CONCEPTUAL_COLLISION                  — même direction conceptuelle (mots-clés communs)
 *   R09  ALREADY_COVERED                       — direction déjà couverte dans l'historique
 *   R10  OUTSIDE_DEPTH                         — complexité manifestement incompatible avec le Depth
 *   R11  SET_DIVERSITY_COLLISION               — ensemble trop concentré dans un axe (validation collective)
 *
 * ── Règles Gemini-prompt-enforced (NON évaluées ici) ─────────────────────
 *   PG1  OUTSIDE_DOMAIN      — enforced par la généalogie dans le prompt Gemini
 *   PG2  OUTSIDE_SUBDOMAIN   — enforced par la généalogie dans le prompt Gemini
 *   PG3  PEDAGOGICAL_COLLISION — enforced via mémoire cumulative Gemini
 *   PG4  TOO_NARROW          — enforced par le prompt Gemini (grain de spécificité)
 *
 * NE PAS y mettre de logique d'appel Gemini.
 * NE PAS réintroduire KLD ou KEY_STRUCTURE ici.
 */
final class ValidationDominantIdeas
{
    /**
     * Catégories génériques interdites comme Idées Dominantes.
     * R02 : une Idée Dominante est une connaissance réelle, pas une catégorie d'interrogation.
     */
    private const GENERIC_CATEGORIES = [
        'date', 'dates', 'personnage', 'personnages', 'acteur', 'acteurs',
        'cause', 'causes', 'consequence', 'consequences',
        'lieu', 'lieux', 'document', 'documents', 'province', 'provinces',
        'evenement', 'evenements', 'fait', 'faits', 'donnee', 'donnees',
        'statistique', 'statistiques', 'caracteristique', 'caracteristiques',
        'propriete', 'proprietes', 'definition', 'description', 'exemple', 'exemples',
        'origine', 'histoire', 'chronologie', 'impact', 'role', 'importance',
        'signification', 'symbolique', 'symbole', 'type', 'types',
        'categorie', 'categories', 'aspect', 'aspects', 'composant', 'composants',
        'resultat', 'resultats', 'effet', 'effets', 'facteur', 'facteurs',
        'element', 'elements', 'contexte', 'information', 'theme', 'themes',
        'periode', 'ere', 'epoque', 'siecle',
    ];

    /**
     * Préfixes de méta-description interdits comme Idées Dominantes (R05 NOT_DOMINANT).
     * Ces formulations décrivent une catégorie de connaissance, pas une connaissance elle-même.
     */
    private const META_DESCRIPTION_PREFIXES = [
        'histoire de ', 'histoire du ', 'histoire des ', 'histoire d\'',
        'evolution de ', 'evolution du ', 'evolution des ',
        'role de ', 'role du ', 'role des ', 'role d\'',
        'impact de ', 'impact du ', 'impact des ',
        'importance de ', 'importance du ', 'importance des ',
        'origine de ', 'origine du ', 'origine des ',
        'definition de ', 'definition du ', 'definition des ',
        'concept de ', 'concept du ', 'concept des ',
        'notion de ', 'notion du ', 'notion des ',
        'evolution de ', 'evolution du ', 'evolution des ',
        'contexte de ', 'contexte du ', 'contexte des ',
        'caracteristique de ', 'caracteristique du ', 'caracteristiques de ',
        'principes de ', 'principes du ', 'fonctionnement de ',
    ];

    /**
     * Conjonctions indiquant une Idée trop large (R04 TOO_BROAD).
     * Une Idée Dominante ne doit couvrir qu'une seule direction.
     */
    private const BROADENING_CONJUNCTIONS = [
        ' et ', ' ou ', ' ainsi que ', ' mais aussi ', ' de meme que ',
        ' comme ', ' notamment ', ' entre autres ', ', puis ',
    ];

    /**
     * Indicateurs de phrase interdits (R01 FORMAT_MINIMAL_IRREDUCTIBLE).
     */
    private const PHRASE_INDICATORS = [
        'le fait que', 'la façon dont', 'la maniere dont', 'comment ', 'pourquoi ',
        'la raison pour laquelle', 'ce qui explique', 'qui permet de',
    ];

    // =========================================================================
    // API publique — validation individuelle
    // =========================================================================

    /**
     * Valide une candidate Idée Dominante selon les règles PHP-enforced.
     *
     * @param string        $candidate         La valeur candidate à valider
     * @param string        $domain            Domaine actif (angle obligatoire)
     * @param string        $subDomain         Sous-domaine actif
     * @param string        $subject           Sujet actif
     * @param DepthContract $contract          Contrat qualitatif du Depth
     * @param string[]      $passIdeas         Idées déjà validées PASS pour ce sujet
     * @param string[]      $failIdeas         Idées précédemment rejetées (toutes valeurs)
     * @param string[]      $coveredDirections Directions pédagogiques déjà couvertes
     */
    public function validateOne(
        string        $candidate,
        string        $domain,
        string        $subDomain,
        string        $subject,
        DepthContract $contract,
        array         $passIdeas = [],
        array         $failIdeas = [],
        array         $coveredDirections = [],
    ): ValidationResult {
        $normalized = $this->normalize($candidate);

        // ── R01 : FORMAT_MINIMAL_IRREDUCTIBLE ────────────────────────────────
        if ($this->violatesFormatMinimalIrreductible($candidate)) {
            return ValidationResult::fail(
                FailReason::FORMAT_MINIMAL_IRREDUCTIBLE_VIOLATION,
                'Idée formulée comme une phrase — doit être une unité minimale de sens.'
            );
        }

        // ── R02 : catégorie générique ─────────────────────────────────────────
        if ($this->isGenericCategory($normalized)) {
            return ValidationResult::fail(
                FailReason::GENERIC_CATEGORY,
                "'{$candidate}' est une catégorie générique d'interrogation, pas une connaissance."
            );
        }

        // ── R05 : méta-description (NOT_DOMINANT) ────────────────────────────
        if ($this->isMetaDescription($normalized)) {
            return ValidationResult::fail(
                FailReason::NOT_DOMINANT,
                "'{$candidate}' est une méta-description, pas une connaissance dominante réelle."
            );
        }

        // ── R04 : TOO_BROAD — conjonctions entre plusieurs directions ─────────
        if ($this->isTooBroad($candidate)) {
            return ValidationResult::fail(
                FailReason::TOO_BROAD,
                "'{$candidate}' couvre plusieurs directions. FORMAT_MINIMAL_IRREDUCTIBLE : une seule direction par Idée."
            );
        }

        // ── R03 : répétition du Sujet ─────────────────────────────────────────
        $normalizedSubject = $this->normalize($subject);
        if ($normalizedSubject === $normalized) {
            return ValidationResult::fail(
                FailReason::SUBJECT_REPETITION,
                "L'Idée répète le Sujet '{$subject}'."
            );
        }
        // Inclut le sujet comme sous-chaîne (≤ 8 chars de différence)
        if (strlen($normalized) > 3 && str_contains($normalizedSubject, $normalized)
            && abs(strlen($normalized) - strlen($normalizedSubject)) <= 8) {
            return ValidationResult::fail(
                FailReason::SUBJECT_REPETITION,
                "L'Idée est trop proche du Sujet '{$subject}'."
            );
        }

        // ── R10 : OUTSIDE_DEPTH — complexité manifestement inadaptée ─────────
        $depthResult = $this->checkDepthCompatibility($candidate, $normalized, $contract);
        if ($depthResult !== null) {
            return $depthResult;
        }

        // ── R06 : doublon exact ───────────────────────────────────────────────
        foreach ($passIdeas as $existing) {
            if ($normalized === $this->normalize($existing)) {
                return ValidationResult::fail(FailReason::DUPLICATE, $existing);
            }
        }

        // ── R07 : équivalence lexicale ────────────────────────────────────────
        foreach ($passIdeas as $existing) {
            $normalizedExisting = $this->normalize($existing);
            if ($this->areLexicallyEquivalent($normalized, $normalizedExisting)) {
                return ValidationResult::fail(FailReason::LEXICAL_EQUIVALENCE, $existing);
            }
        }

        // ── R08 : collision conceptuelle (direction clé partagée) ─────────────
        foreach ($passIdeas as $existing) {
            $normalizedExisting = $this->normalize($existing);
            if ($this->haveConceptualCollision($normalized, $normalizedExisting)) {
                return ValidationResult::fail(FailReason::CONCEPTUAL_COLLISION, $existing);
            }
        }

        // ── R07 : reformulation d'une idée rejetée ────────────────────────────
        foreach ($failIdeas as $failed) {
            $normalizedFailed = $this->normalize($failed);
            if ($this->areLexicallyEquivalent($normalized, $normalizedFailed)) {
                return ValidationResult::fail(
                    FailReason::LEXICAL_EQUIVALENCE,
                    "Reformulation de l'idée rejetée '{$failed}'."
                );
            }
        }

        // ── R09 : direction déjà couverte ─────────────────────────────────────
        foreach ($coveredDirections as $direction) {
            $normalizedDir = $this->normalize($direction);
            if ($this->areLexicallyEquivalent($normalized, $normalizedDir)) {
                return ValidationResult::fail(FailReason::ALREADY_COVERED, $direction);
            }
        }

        return ValidationResult::pass();
    }

    // =========================================================================
    // API publique — validation collective (diversité) — R11
    // =========================================================================

    /**
     * Valide la diversité collective d'un ensemble d'Idées Dominantes PASS prospectif.
     *
     * Appelée AVANT persistance pour décider si une candidate doit être rejetée
     * pour SET_DIVERSITY_COLLISION plutôt que persisted comme PASS.
     *
     * Retourne null si la diversité est acceptable.
     * Retourne un ValidationResult::fail(SET_DIVERSITY_COLLISION) sinon.
     *
     * @param string[] $prospectiveSet Idées PASS déjà validées + nouvelle candidate
     */
    public function validateDiversity(array $prospectiveSet): ?ValidationResult
    {
        if (count($prospectiveSet) < 3) {
            return null; // Pas assez d'idées pour détecter une concentration
        }

        // Axe 1 : concentration excessive sur des noms de personnages
        $personCount = 0;
        foreach ($prospectiveSet as $idea) {
            if ($this->looksLikePersonName($idea)) {
                $personCount++;
            }
        }

        $total = count($prospectiveSet);

        if ($personCount === $total && $total >= 4) {
            return ValidationResult::fail(
                FailReason::SET_DIVERSITY_COLLISION,
                "L'ensemble est concentré exclusivement sur des noms de personnages "
                . "({$personCount}/{$total}). La diversité des directions pédagogiques est insuffisante."
            );
        }

        if ($personCount > 0 && $total >= 4 && ($personCount / $total) >= 0.75) {
            return ValidationResult::fail(
                FailReason::SET_DIVERSITY_COLLISION,
                "L'ensemble est sur-concentré dans l'axe 'personnages' "
                . "({$personCount}/{$total} idées). "
                . "Diversifier les directions pédagogiques."
            );
        }

        // Axe 2 : paires lexicalement redondantes dans l'ensemble
        $redundantPairs = 0;
        for ($i = 0; $i < $total; $i++) {
            for ($j = $i + 1; $j < $total; $j++) {
                if ($this->areLexicallyEquivalent(
                    $this->normalize($prospectiveSet[$i]),
                    $this->normalize($prospectiveSet[$j])
                )) {
                    $redundantPairs++;
                }
            }
        }

        if ($redundantPairs >= 2) {
            return ValidationResult::fail(
                FailReason::SET_DIVERSITY_COLLISION,
                "L'ensemble contient {$redundantPairs} paires d'idées trop proches sémantiquement."
            );
        }

        return null;
    }

    // =========================================================================
    // Méthodes privées — implémentation des règles
    // =========================================================================

    /**
     * R01 : Vérifie si une Idée viole FORMAT_MINIMAL_IRREDUCTIBLE.
     */
    private function violatesFormatMinimalIrreductible(string $candidate): bool
    {
        $lower = mb_strtolower(trim($candidate), 'UTF-8');

        foreach (self::PHRASE_INDICATORS as $indicator) {
            if (str_contains($lower, $indicator)) {
                return true;
            }
        }

        // Trop long (> 6 mots → probablement une phrase)
        $words = preg_split('/\s+/', trim($candidate)) ?: [];

        return count($words) > 6;
    }

    /**
     * R02 : Vérifie si une valeur est une catégorie générique.
     * Normalise les items de la liste (sans accents) pour comparaison robuste.
     */
    private function isGenericCategory(string $normalized): bool
    {
        // GENERIC_CATEGORIES est déjà sans accents (pré-normalisé dans la constante)
        return in_array($normalized, self::GENERIC_CATEGORIES, true);
    }

    /**
     * R05 : Vérifie si une idée est une méta-description (NOT_DOMINANT).
     * Ex: "Histoire de la Confédération", "Rôle de Macdonald".
     */
    private function isMetaDescription(string $normalized): bool
    {
        foreach (self::META_DESCRIPTION_PREFIXES as $prefix) {
            if (str_starts_with($normalized, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * R04 : Vérifie si une Idée couvre plusieurs directions (TOO_BROAD).
     * Détecte les conjonctions entre deux concepts distincts.
     */
    private function isTooBroad(string $candidate): bool
    {
        $lower = mb_strtolower(trim($candidate), 'UTF-8');

        foreach (self::BROADENING_CONJUNCTIONS as $conj) {
            if (! str_contains($lower, $conj)) {
                continue;
            }

            // Vérifier que les deux parties de la conjonction sont des concepts significatifs
            // (pas juste un article ou préposition)
            $parts = explode($conj, $lower, 2);
            $partA = trim($parts[0]);
            $partB = trim($parts[1] ?? '');

            if (
                strlen($partA) > 3
                && strlen($partB) > 3
                && count($this->significantWords($partA)) >= 1
                && count($this->significantWords($partB)) >= 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * R10 : Vérifie si la complexité de l'Idée est manifestement incompatible avec le Depth.
     *
     * Pour les Depths bas (2-4, public large) : rejeter les idées avec vocabulaire ultra-spécialisé.
     * Pour les Depths hauts (9-10, expert) : rejeter les idées trop triviales (1 mot très courant).
     */
    private function checkDepthCompatibility(
        string        $candidate,
        string        $normalized,
        DepthContract $contract,
    ): ?ValidationResult {
        // Depth bas (grand public) : vocabulaire technique manifeste → OUTSIDE_DEPTH
        if ($contract->depth <= 4) {
            // Détection de suffixes ultra-spécialisés en français
            $technicalSuffixes = [
                'ologie', 'ographie', 'ometrie', 'oscopie', 'iatrie',
                'pharmacologie', 'cytologie', 'histologie', 'thermodynamique',
            ];

            $lowerCandidate = mb_strtolower($candidate, 'UTF-8');
            $technicalCount = 0;

            foreach ($technicalSuffixes as $suffix) {
                if (str_contains($lowerCandidate, $suffix)) {
                    $technicalCount++;
                }
            }

            // ≥ 2 suffixes techniques dans une même idée → trop complexe pour ce Depth
            if ($technicalCount >= 2) {
                return ValidationResult::fail(
                    FailReason::OUTSIDE_DEPTH,
                    "L'idée contient {$technicalCount} termes ultra-spécialisés incompatibles avec "
                    . "le Depth {$contract->depth} ({$contract->subjectProfileLabel})."
                );
            }
        }

        // Depth très haut (expert avancé, Depth 9-10) : idée trop triviale → OUTSIDE_DEPTH
        if ($contract->depth >= 9) {
            $sigWords = $this->significantWords($normalized);

            // Cas 1 : un seul mot-clé court (4 chars max) → trop vague pour un expert
            // Cas 2 : aucun mot significatif (seuil > 3 chars) ET idée très courte (≤ 5 chars)
            //         Ex: "Loi" (3 chars) → pas de mot significatif, idée triviale
            $isTrivial =
                (count($sigWords) === 1 && mb_strlen($sigWords[0], 'UTF-8') <= 4)
                || (empty($sigWords) && mb_strlen($normalized, 'UTF-8') <= 5);

            if ($isTrivial) {
                return ValidationResult::fail(
                    FailReason::OUTSIDE_DEPTH,
                    "L'idée '{$candidate}' est trop triviale pour le Depth {$contract->depth} "
                    . "({$contract->subjectProfileLabel})."
                );
            }
        }

        return null;
    }

    /**
     * R08 : Vérifie si deux idées ont une collision conceptuelle.
     *
     * CONCEPTUAL_COLLISION est plus strict que LEXICAL_EQUIVALENCE :
     * deux idées partagent la même direction conceptuelle lorsque leur(s) mot(s)-clé(s)
     * dominant(s) (le nom principal, le concept central) sont identiques, même si
     * les formulations sont différentes.
     *
     * Exemples :
     *   "Constitution de 1867" ≈ "Acte constitutionnel de 1867" (mot-clé commun : "constitutionnel")
     *   "Système parlementaire" ≈ "Parlement de Westminster" (mot-clé commun : "parlement")
     */
    private function haveConceptualCollision(string $normalizedA, string $normalizedB): bool
    {
        if ($normalizedA === $normalizedB) {
            return false; // Géré par DUPLICATE
        }

        if ($this->areLexicallyEquivalent($normalizedA, $normalizedB)) {
            return false; // Géré par LEXICAL_EQUIVALENCE
        }

        $wordsA = $this->significantWords($normalizedA);
        $wordsB = $this->significantWords($normalizedB);

        if (empty($wordsA) || empty($wordsB)) {
            return false;
        }

        // Mots-clés longue (≥ 5 chars) — les concepts centraux, pas les mots courants
        $keywordsA = array_filter($wordsA, fn($w) => mb_strlen($w, 'UTF-8') >= 5);
        $keywordsB = array_filter($wordsB, fn($w) => mb_strlen($w, 'UTF-8') >= 5);

        if (empty($keywordsA) || empty($keywordsB)) {
            return false;
        }

        // Partage d'un mot-clé exact (root-sharing)
        $sharedKeywords = array_intersect(
            array_values($keywordsA),
            array_values($keywordsB)
        );

        if (! empty($sharedKeywords)) {
            return true;
        }

        // Partage par racine (premiers 5 chars du mot-clé)
        $rootsA = array_map(fn($w) => mb_substr($w, 0, 5, 'UTF-8'), $keywordsA);
        $rootsB = array_map(fn($w) => mb_substr($w, 0, 5, 'UTF-8'), $keywordsB);
        $sharedRoots = array_intersect(array_values($rootsA), array_values($rootsB));

        // Si plusieurs racines partagées → collision conceptuelle certaine
        if (count($sharedRoots) >= 2) {
            return true;
        }

        // Une racine partagée + les deux ensembles ont peu de mots → même direction
        if (count($sharedRoots) >= 1 && count($keywordsA) <= 2 && count($keywordsB) <= 2) {
            return true;
        }

        return false;
    }

    /**
     * R07 : Vérifie si deux valeurs normalisées sont lexicalement équivalentes.
     */
    private function areLexicallyEquivalent(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        $wordsA = $this->significantWords($a);
        $wordsB = $this->significantWords($b);

        if (empty($wordsA) || empty($wordsB)) {
            return false;
        }

        $commonWords = array_intersect($wordsA, $wordsB);
        $overlapA    = count($wordsA) > 0 ? count($commonWords) / count($wordsA) : 0;
        $overlapB    = count($wordsB) > 0 ? count($commonWords) / count($wordsB) : 0;

        if ($overlapA >= 0.8 || $overlapB >= 0.8) {
            return true;
        }

        // Inclusion directe de chaîne (l'un contient l'autre)
        if (strlen($a) > 3 && strlen($b) > 3) {
            if (str_contains($a, $b) || str_contains($b, $a)) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Normalise une chaîne : minuscules, trim, suppression accents, suppression articles.
     */
    private function normalize(string $value): string
    {
        $v = mb_strtolower(trim($value), 'UTF-8');

        // Supprimer les accents
        $v = strtr($v, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        // Supprimer les articles et prépositions de début
        $v = preg_replace('/^(le |la |les |l\'|un |une |des |de |du |d\'|the |a |an )/', '', $v);

        // Normaliser les espaces multiples
        $v = preg_replace('/\s+/', ' ', $v);

        return trim($v ?? '');
    }

    /**
     * Extrait les mots significatifs (longueur > 3, sans mots vides).
     *
     * @return string[]
     */
    private function significantWords(string $normalized): array
    {
        $stopWords = [
            'de', 'du', 'des', 'le', 'la', 'les', 'un', 'une',
            'et', 'ou', 'en', 'au', 'aux', 'par', 'sur', 'sous',
            'dans', 'avec', 'pour', 'que', 'qui',
        ];

        $words = preg_split('/[\s\-_]+/', $normalized) ?: [];

        return array_values(array_filter(
            $words,
            fn(string $w) => strlen($w) > 3 && ! in_array($w, $stopWords, true)
        ));
    }

    /**
     * Vérifie si une valeur ressemble à un nom de personne.
     */
    private function looksLikePersonName(string $value): bool
    {
        if (! preg_match('/^[A-ZÀÂÄÉÈÊËÎÏÔÙÛÜÇ]/u', $value)) {
            return false;
        }

        if (preg_match('/\d/', $value)) {
            return false;
        }

        $words         = preg_split('/[\s\-]+/', $value) ?: [];
        $capitalWords  = array_filter($words, fn($w) => preg_match('/^[A-ZÀÂÄÉÈÊËÎÏÔÙÛÜÇ]/u', $w));

        return count($capitalWords) >= 2;
    }
}
