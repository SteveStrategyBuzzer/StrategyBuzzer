---
name: Pipeline officiel Kernel Blueprint (2026-07-04)
description: Référence architecturale du moteur de création — Blueprint vivant, responsabilités par brique, vocabulaire officiel.
---

# Pipeline officiel Kernel Blueprint — Référence architecturale

## Pipeline officiel (verrouillé)

```
KernelBlueprint (frame vide)
        │
        ▼
KernelRotationPlanner
        │ remplit : depth · domain_code · rotation_identifier
        ▼
Bassin Taxonomy
        │ produit : cartouche candidate
        │ remplit : sub_domain · subject · dominant_idea · knowledge_frequency
        ▼
KEY_LEARNING_DIRECTION                                              [NEXT]
        │ remplit : learning_direction_status · learning_direction_reason
        ▼
KEY_STRUCTURE
        │ remplit : structure_status · structure_reason
        ▼
QuestionIntent
        │ remplit : kernel_code · ks_hash · kld_hash · semantic_key · intent_key
        ▼
KernelBlueprint complètement rempli
        │
        ▼
PHASE 1 (génération AI)
        │
        ▼
Validation
        │
        ▼
Translations (Phases 3/4)
        │
        ▼
READY_BANK
```

---

## Le KernelBlueprint est un support vivant

Le KernelBlueprint n'est pas uniquement créé au début et transmis inchangé.

Il est le **support commun** qui circule dans tout le pipeline. Chaque brique écrit
**uniquement les slots dont elle est propriétaire**. Aucune brique ne modifie les slots
d'une autre brique.

Le Blueprint est considéré **terminé uniquement après QuestionIntent**.

---

## Responsabilité exacte de chaque brique (slots propriétaires)

### KernelBlueprint
- Frame vide contenant tous les slots nécessaires aux étapes suivantes
- Aucune logique métier
- Support unique sur lequel chaque brique écrit sa partie

### KernelRotationPlanner
Remplit uniquement :
- `depth`
- `domain_code`
- `rotation_identifier`

Ne remplit rien d'autre.

### Bassin Taxonomy
Le Bassin est le **moteur Taxonomy** — pas un conteneur passif.

Pour un depth donné, il contient **8 chargeurs autonomes** (un par domaine Gameplay),
coordonnés exclusivement par KernelRotationPlanner.

Chaque domaine possède deux chargeurs nommés :

```
                    DEPTH N
          ┌─────────────────────────────┐
          │        BASSIN TAXONOMY      │
          └─────────────────────────────┘

  histoire
     ├── Chargeur de sujets   : sous-domaine actif → jusqu'à 50 sujets
     └── Chargeur d'idées     : sujet actif → 5 idées dominantes

  geographie / sport / art / cuisine / science / cinema / faune
     ├── Chargeur de sujets   (idem)
     └── Chargeur d'idées     (idem)
```

Le Bassin **ne crée jamais un noyau**. Il produit uniquement une **cartouche candidate**.

Remplit uniquement :
- `sub_domain`
- `subject`
- `dominant_idea`
- `knowledge_frequency`

### KEY_LEARNING_DIRECTION
- Consomme la cartouche candidate
- Valide la cohérence pédagogique (anti-répétition direction sujet+idée)
- Aucun accès DB · Aucun hash · N'avance pas la Taxonomy

Remplit uniquement :
- `learning_direction_status`
- `learning_direction_reason`

### KEY_STRUCTURE
- Valide la structure du noyau (qualité taxonomique + pré-code)
- Ne modifie jamais : depth · domain · subject · dominant_idea

Remplit uniquement :
- `structure_status`
- `structure_reason`

### QuestionIntent
- Dernière étape avant Phase 1
- Responsable uniquement de l'identité du noyau
- Ne valide rien · Ne choisit rien · Ne modifie jamais la Taxonomy

Remplit uniquement :
- `kernel_code`
- `ks_hash`
- `kld_hash`
- `semantic_key`
- `intent_key`

---

## Règle de transaction métier (VERROUILLÉE)

```
peekNext()  [Bassin Taxonomy → cartouche candidate]
  → KLD     [valide direction]
  → KS      [valide structure]
  → QuestionIntent créé avec succès
  → confirmConsumed()   ← UNIQUEMENT si tout réussit
```

Si KLD, KEY_STRUCTURE ou QuestionIntent échoue → `confirmConsumed()` n'est JAMAIS appelé.
Le prochain `peekNext()` retourne exactement la même cartouche (retry automatique).

---

## Structure du Bassin Taxonomy

**8 chargeurs autonomes coordonnés par KernelRotationPlanner.**

Chaque chargeur avance à son propre rythme.

**Progression naturelle (garantie par le chargeur, sans règle spéciale) :**

| Condition | Action |
|---|---|
| Chargeur d'idées épuisé (5 idées) | → sujet suivant dans le Chargeur de sujets |
| Chargeur de sujets épuisé (50 sujets) | → sous-domaine suivant |
| Sous-domaine suivant indisponible | → domaine épuisé pour ce depth |
| 8 domaines épuisés | → depth complet |

**Maximisation automatique :** un sous-domaine ne change jamais avant que ses 50 sujets × 5 idées
soient entièrement épuisés. Aucune règle spéciale nécessaire — le chargeur la garantit naturellement.

---

## Contenu de la cartouche candidate

Ce que le Bassin Taxonomy produit à chaque tirage :

```
depth               int
domain_code         string
sub_domain          string
subject             string
dominant_idea       string
knowledge_frequency int
```

**Jamais transporté** : les 8 domaines, les 50 sujets, les 5 idées, l'état des chargeurs,
la progression du bassin.

---

## Vocabulaire officiel (verrouillé)

| ✔ Utiliser | ✘ Ne plus utiliser |
|---|---|
| Bassin Taxonomy | — |
| Chargeur de sujets | Chargeur 1 |
| Chargeur d'idées | Chargeur 2 |
| Cartouche candidate | "noyau" (avant QuestionIntent) |
| Blueprint vivant | Blueprint passif / Blueprint transmis |
| 8 chargeurs autonomes coordonnés par KernelRotationPlanner | 8 machines indépendantes |

---

## État des briques

| Brique | État |
|---|---|
| KernelBlueprint | Support vivant — circule dans tout le pipeline |
| KernelRotationPlanner | FERMÉ ✔ |
| TaxonomyProgressManager (= Bassin Taxonomy) | FERMÉ ✔ |
| KEY_LEARNING_DIRECTION | **NEXT** |
| KEY_STRUCTURE | À venir |
| QuestionIntent | À venir |

**Why:** Le Blueprint vivant garantit qu'aucune brique ne peut lire ou modifier les slots
d'une autre. La cartouche candidate évite de confondre les données brutes du Bassin avec
un noyau complet. Ce vocabulaire est la condition pour que KLD, KEY_STRUCTURE et QuestionIntent
restent des filtres purs sans couplage entre eux.
