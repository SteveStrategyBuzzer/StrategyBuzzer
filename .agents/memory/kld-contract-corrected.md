---
name: KLD contrat corrigé (2026-07-04)
description: Corrections obligatoires au document technique KLD avant PATCH B — moteur pur, pas de DB, pas de hash, DTO typé.
---

# KEY_LEARNING_DIRECTION — Contrat corrigé (2026-07-04)

## Trois corrections par rapport à l'audit initial

### Correction 1 — KLD ne touche pas la DB
KLD ne connaît pas QuestionIntent, Eloquent, DB::table() ou question_intents.
Les directions existantes lui sont FOURNIES par l'orchestrateur via paramètre `$existingDirections`.
Supprimé : hashExists(), toute requête SQL, toute dépendance Eloquent.

### Correction 2 — KLD ne génère pas les hashes
KLD ne fabrique pas sha256(), kld_hash, kernel_code, semantic_key ni intent_key.
Il retourne uniquement status / reason / canonical_direction (direction normalisée validée).
La génération des hashes appartient au futur KernelIdentifierManager.

### Correction 3 — API publique typée (DTO, pas array)
Pas d'`array $pair` anonyme. DTO obligatoire : `LearningDirectionInput`.

## Fichiers exacts

```
app/Services/QuestionBank/Rotation/DTO/LearningDirectionInput.php
app/Services/QuestionBank/Rotation/KeyLearningDirection.php
tests/Unit/QuestionBank/Rotation/KeyLearningDirectionTest.php
```

## LearningDirectionInput — champs exacts

```php
public function __construct(
    public readonly int     $depth,
    public readonly string  $domainCode,
    public readonly string  $subDomain,
    public readonly string  $subject,
    public readonly string  $dominantIdea,
    public readonly int     $knowledgeFrequency,
    public readonly ?string $rotationIdentifier = null,
)
```

## API publique KeyLearningDirection (v2 — 2026-07-04)

```php
final class KeyLearningDirection
{
    public function check(
        LearningDirectionInput $input,
        array $validatedDirections = []   // LearningDirectionResult[] déjà validés
    ): LearningDirectionResult
}
```

## LearningDirectionResult (DTO retour typé — v5, 2026-07-05)

```php
final class LearningDirectionResult
{
    public readonly string  $status;               // 'pass' | 'fail' | 'review_structure'
    public readonly ?string $reason;               // null si pass ; POSSIBLE_CONTEXTUAL_DUPLICATE si review_structure
    public readonly string  $normalizedSubject;
    public readonly string  $normalizedDominantIdea;
    public readonly ?string $synonymDetected;      // null si pas de synonyme détecté (renommé depuis businessEquivalence)
    public readonly bool    $contextValidated;
}
```

**3 statuts officiels :**
- `'pass'` — direction inédite, KEY_STRUCTURE vérifie la structure sans alerte
- `'fail'` — doublon certain, KEY_STRUCTURE n'est pas appelé
- `'review_structure'` — risque contextuel, KEY_STRUCTURE tranche avec l'alerte KLD

## Autorité des connaissances métier (v4 — 2026-07-04)

```
app/Services/QuestionBank/Knowledge/LearningKnowledgeBase.php
```

Source unique officielle des connaissances pédagogiques de KLD.
Expose : getEquivalences(), getContextRules(), getSimilarityRules().
KLD la reçoit par injection. Il ne la définit pas.

## Registre des directions validées (v4 — 2026-07-04)

```
app/Services/QuestionBank/Rotation/LearningDirectionRegistry.php
```

KLD interroge un contrat métier, pas un tableau.
Expose : contains(), findEquivalent(), findReverse(), findContext().

Signature finale :
```php
public function check(
    LearningDirectionInput $input,
    LearningDirectionRegistry $registry
): LearningDirectionResult
```

## Frontière KLD / KEY_STRUCTURE (v5 — 2026-07-05)

KLD = **filtre rapide + émetteur d'alerte de risque** (3 sorties).
KEY_STRUCTURE = **juge structurel final**.

```
transport + voiture → transport + auto     → KLD FAIL             (synonyme direct — doublon certain)
transport + voiture → transport + camion   → KLD REVIEW_STRUCTURE (voisin — KEY_STRUCTURE tranche)
transport + voiture → transport + avion    → KLD PASS             (direction distincte)
idea hors depth                            → KLD PASS             → KEY_STRUCTURE FAIL
```

KLD ne juge jamais si une idée est pédagogiquement bonne.
Il juge : même dossier = FAIL ; risque de collision = REVIEW_STRUCTURE ; direction inédite = PASS.

## LearningDirectionLexicon (renommé, 2026-07-04)

**Fichier :** `app/Services/QuestionBank/Knowledge/LearningDirectionLexicon.php`

Ancienne classe `LearningKnowledgeBase` — **renommée** car sa responsabilité est maintenant un lexique métier spécialisé au service de KLD, pas une base de connaissances générale.

| Méthode | Contenu |
|---|---|
| `getSynonyms()` | Lexique de synonymes directs par domaine (voiture≈auto≈char≈bagnole) — détecte la même direction d'apprentissage sous nom différent |
| `getContextRules()` | Couples (subDomainA, subDomainB) déclarés pédagogiquement distincts pour une idée dominante (KLD-5) |
| ~~`getSimilarityRules()`~~ | **SUPPRIMÉ** — seuil 0.85 abandonné |

Responsabilité unique : "voiture = auto = char = bagnole". Ce n'est pas une base de connaissances, c'est un **lexique métier spécialisé**.

## Responsabilités KLD

DOIT :
- Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage
- Appliquer lexique de synonymes directs via `LearningDirectionLexicon::getSynonyms()` → FAIL si synonyme
- Appliquer détection de voisins via `LearningDirectionLexicon::getNeighbors()` → REVIEW_STRUCTURE si voisin
- Appliquer context_map via `LearningDirectionLexicon::getContextRules()` → arbitrage KLD-5
- Retourner PASS | FAIL | REVIEW_STRUCTURE déterministe

NE DOIT JAMAIS :
- Lire/écrire DB
- Générer un hash
- Avancer TaxonomyProgressManager
- Créer QuestionIntent
- Choisir domaine/sous-domaine/sujet/idée

## Motifs par sortie

**FAIL :**
`INVALID_MINIMAL_PAIR`, `DIRECT_PAIR_CONTEXT_DUPLICATE`, `REVERSED_PAIR_CONTEXT_DUPLICATE`,
`CONCEPTUAL_COLLISION`, `CONTEXT_NOT_DISTINCT`

**REVIEW_STRUCTURE :**
`POSSIBLE_CONTEXTUAL_DUPLICATE`

**PASS :**
reason = null

## LearningDirectionLexicon — méthodes (v5)

| Méthode | Rôle | Sortie KLD |
|---|---|---|
| `getSynonyms()` | voiture≈auto≈char≈bagnole | → FAIL |
| `getNeighbors()` | voiture→{camion, moto, bus} (voisins non synonymes) | → REVIEW_STRUCTURE |
| `getContextRules()` | couples (subDomainA, subDomainB) distincts (KLD-5) | arbitrage FAIL/PASS |

## Tests — 13 tests purs (aucune DB, aucun Eloquent)
1. rejects_when_subject_equals_dominant_idea
2. rejects_direct_duplicate_from_existing_directions
3. rejects_reversed_duplicate_from_existing_directions
4. rejects_direct_synonym_via_lexicon
5. rejects_when_context_map_is_silent
6. passes_when_context_map_declares_distinct_contexts
7. returns_review_structure_for_neighboring_idea              ← nouveau
8. passes_when_no_rule_triggers
9. returns_canonical_direction_on_pass
10. does_not_generate_hashes
11. has_no_database_dependency
12. accepts_typed_learning_direction_input
13. review_structure_carries_possible_contextual_duplicate_reason  ← nouveau

## Ordre des patches
PATCH B1 : LearningDirectionInput DTO (pas de migration)
PATCH B2 : KeyLearningDirection service pur
PATCH C  : KeyLearningDirectionTest (PHPUnit\Framework\TestCase direct — ZÉRO DB)

**Why:** KLD est un moteur de validation pédagogique pur. L'injecter Eloquent le rendrait
non-testable sans DB, non-réutilisable, et briserait la séparation des responsabilités.
L'orchestrateur est responsable de fournir les données → KLD décide → orchestrateur persiste.
