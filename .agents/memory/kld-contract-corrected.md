---
name: KLD contrat corrigé (2026-07-04)
description: Corrections obligatoires au document technique KLD avant PATCH B — moteur pur, pas de DB, pas de hash, DTO typé.
---

> ⛔ **SUPERSEDED 2026-08-11** — KLD/KEY_STRUCTURE retirés du flow canonique ; responsabilités absorbées par ValidationDominantIdeas. Ne pas rebrancher. Voir [canonical-kernel-flow.md](canonical-kernel-flow.md). Conservé pour historique.


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
Expose :
- `contains(string $directionKey): bool`
- `getIdeasForSubject(string $subjectKey): array` — retourne les idea_canonical_keys déjà validées pour ce sujet

Signature finale :
```php
public function check(
    LearningDirectionInput $input,
    LearningDirectionRegistry $registry
): LearningDirectionResult
```

## Mécanisme en 7 étapes (verrouillé 2026-07-05)

```
1. Normaliser le sujet                              → subject_key
2. Normaliser l'idée                                → idea_key
3. Résoudre idea_key via getSynonyms()              → idea_canonical_key
4. direction_key = subject_key + "::" + idea_canonical_key
5. registry.contains(direction_key) ?
   → oui → FAIL  DIRECT_PAIR_CONTEXT_DUPLICATE
6. registry.getIdeasForSubject(subject_key) → existing_ideas[]
   Pour chaque existing_idea :
     familyIndex.sameFamily(domain_code, idea_canonical_key, existing_idea) ?
   → oui → REVIEW_STRUCTURE  POSSIBLE_CONTEXTUAL_DUPLICATE
7. → PASS
```

## Frontière KLD / KEY_STRUCTURE (v6 — 2026-07-05)

KLD = **registre de dossiers pédagogiques + synonymes directs + familles d'idées**. Mécanisme déterministe en 7 étapes.
KEY_STRUCTURE = **juge structurel final**.

```
transport + auto     → FAIL             (synonyme direct de voiture)
transport + camion   → REVIEW_STRUCTURE (même famille véhicules_routiers que voiture)
transport + autobus  → REVIEW_STRUCTURE (même famille véhicules_routiers que voiture)
transport + train    → PASS             (famille différente — transport_ferroviaire)
transport + avion    → PASS             (famille différente — transport_aérien)
idée hors depth      → PASS             → KEY_STRUCTURE FAIL
```

Règle précise :
- même sujet + synonyme direct → FAIL
- même sujet + idée dans même famille → REVIEW_STRUCTURE
- même sujet + idée de famille différente/inconnue → PASS

## LearningDirectionLexicon (v6 — 2026-07-05)

**Fichier :** `app/Services/QuestionBank/Knowledge/LearningDirectionLexicon.php`

| Méthode | Rôle |
|---|---|
| `getSynonyms()` | Groupes de synonymes directs — résout idea_key → idea_canonical_key |

Responsabilité unique : résoudre `auto → voiture`, `char → voiture`, `bagnole → voiture`.

## LearningIdeaFamilyIndex (nouveau — 2026-07-05)

**Fichier :** `app/Services/QuestionBank/Knowledge/LearningIdeaFamilyIndex.php`

| Méthode | Rôle |
|---|---|
| `sameFamily(string $domainCode, string $ideaA, string $ideaB): bool` | Retourne true si ideaA et ideaB appartiennent à la même famille pour ce domaine |

Structure interne (données statiques) :
```
transport:
  véhicules_routiers: [voiture, camion, autobus, pickup]
  transport_ferroviaire: [train, métro, tramway]
  transport_aérien: [avion, hélicoptère]
  transport_maritime: [bateau, navire, ferry]
```

Responsabilité unique : déclarer les familles d'idées trop proches pour un même sujet.
KLD s'en sert uniquement pour détecter le risque (REVIEW_STRUCTURE) — jamais pour trancher.

## Responsabilités KLD

DOIT :
- Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage
- Résoudre synonymes directs via `LearningDirectionLexicon::getSynonyms()` → même direction_key → FAIL
- Détecter familles d'idées proches via `LearningIdeaFamilyIndex::sameFamily()` → REVIEW_STRUCTURE
- Retourner PASS | FAIL | REVIEW_STRUCTURE déterministe

NE DOIT JAMAIS :
- Lire/écrire DB
- Générer un hash
- Avancer TaxonomyProgressManager
- Créer QuestionIntent
- Choisir domaine/sous-domaine/sujet/idée

## Motifs par sortie (v6)

**FAIL :**
`DIRECT_PAIR_CONTEXT_DUPLICATE` — direction_key déjà dans registry (via résolution synonyme incluse)

**REVIEW_STRUCTURE :**
`POSSIBLE_CONTEXTUAL_DUPLICATE` — idée dans même famille qu'une direction existante pour ce sujet

**PASS :**
reason = null — direction inédite, famille différente ou inconnue

## Tests — 11 tests purs (aucune DB, aucun Eloquent)
1.  passes_when_subject_and_idea_are_both_new
2.  fails_when_direction_key_already_exists_exactly
3.  fails_when_idea_resolves_to_existing_canonical_via_synonym
4.  returns_review_structure_when_idea_is_in_same_family_as_existing
5.  passes_when_idea_is_in_different_family_from_existing
6.  passes_when_idea_family_is_unknown
7.  resolves_synonym_to_canonical_before_building_direction_key
8.  does_not_generate_hashes
9.  has_no_database_dependency
10. accepts_typed_learning_direction_input
11. review_structure_carries_possible_contextual_duplicate_reason

## Ordre des patches
PATCH B1 : LearningDirectionInput DTO (pas de migration)
PATCH B2 : KeyLearningDirection service pur
PATCH C  : KeyLearningDirectionTest (PHPUnit\Framework\TestCase direct — ZÉRO DB)

**Why:** KLD est un moteur de validation pédagogique pur. L'injecter Eloquent le rendrait
non-testable sans DB, non-réutilisable, et briserait la séparation des responsabilités.
L'orchestrateur est responsable de fournir les données → KLD décide → orchestrateur persiste.
