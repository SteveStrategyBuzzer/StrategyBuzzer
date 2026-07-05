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

## LearningDirectionResult (DTO retour typé)

```php
final class LearningDirectionResult
{
    public readonly string  $status;               // 'pass' | 'fail'
    public readonly ?string $reason;               // null si pass
    public readonly string  $normalizedSubject;
    public readonly string  $normalizedDominantIdea;
    public readonly ?string $businessEquivalence;  // null si pas d'équivalence détectée
    public readonly bool    $contextValidated;
}
```

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

## Frontière KLD / KEY_STRUCTURE (verrouillée 2026-07-04)

KLD = détecteur de **synonymes directs** (doublons cachés).
KEY_STRUCTURE = **moteur de contexte complet** (cohérence, depth, décorticage, collision structurelle).

```
transport + voiture → transport + auto     → KLD FAIL  (synonyme direct)
transport + voiture → transport + camion   → KLD PASS  → KEY_STRUCTURE analyse
idea hors depth                            → KLD PASS  → KEY_STRUCTURE FAIL
```

KLD ne juge jamais si une idée est pédagogiquement bonne.
Il juge uniquement : même dossier pédagogique sous un nom différent = doublon caché.

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
- Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage sous formulation différente
- Appliquer lexique de synonymes directs via LearningDirectionLexicon::getSynonyms()
- Appliquer context_map via LearningDirectionLexicon::getContextRules()
- Retourner PASS/FAIL déterministe
- Laisser passer les concepts voisins non synonymes → KEY_STRUCTURE les analyse

NE DOIT JAMAIS :
- Lire/écrire DB
- Générer un hash
- Avancer TaxonomyProgressManager
- Créer QuestionIntent
- Choisir domaine/sous-domaine/sujet/idée

## 6 motifs de rejet
INVALID_MINIMAL_PAIR, DIRECT_PAIR_CONTEXT_DUPLICATE, REVERSED_PAIR_CONTEXT_DUPLICATE,
CONCEPTUAL_COLLISION, CONTEXT_NOT_DISTINCT, PAIR_TOO_CLOSE_TO_EXISTING

## Tests — 12 tests purs (aucune DB, aucun Eloquent)
1. rejects_when_subject_equals_dominant_idea
2. rejects_direct_duplicate_from_existing_directions
3. rejects_reversed_duplicate_from_existing_directions
4. rejects_business_equivalence_collision
5. rejects_when_context_map_is_silent
6. passes_when_context_map_declares_distinct_contexts
7. rejects_when_pair_is_too_close_to_existing_direction
8. passes_when_no_rule_triggers
9. returns_canonical_direction_on_pass
10. does_not_generate_hashes
11. has_no_database_dependency
12. accepts_typed_learning_direction_input

## Ordre des patches
PATCH B1 : LearningDirectionInput DTO (pas de migration)
PATCH B2 : KeyLearningDirection service pur
PATCH C  : KeyLearningDirectionTest (PHPUnit\Framework\TestCase direct — ZÉRO DB)

**Why:** KLD est un moteur de validation pédagogique pur. L'injecter Eloquent le rendrait
non-testable sans DB, non-réutilisable, et briserait la séparation des responsabilités.
L'orchestrateur est responsable de fournir les données → KLD décide → orchestrateur persiste.
