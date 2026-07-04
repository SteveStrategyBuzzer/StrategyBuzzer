---
name: Pipeline officiel Kernel (2026-07-04)
description: Ordre verrouillé de la chaîne de production de noyaux — responsabilités par brique.
---

# Pipeline officiel Kernel — Verrouillé 2026-07-04

## Ordre de la chaîne

```
KernelBlueprint
      │
      ▼
KernelRotationPlanner          ← autorité : depth + domain_code + DomainCycle
      │
      ▼
TaxonomyProgressManager        ← autorité : sub_domain + subject + dominant_idea   [FERMÉ]
      │  peekNext() → paire unique (depth, domain, sub_domain, subject, dominant_idea, kf)
      ▼
KEY_LEARNING_DIRECTION         ← reçoit UNE paire, anti-doublon pédagogique          [NEXT]
      │
      ▼
KEY_STRUCTURE                  ← qualité taxonomique + pré-code yy-xx-xxx-xxx-xxx-zz
      │
      ▼
QuestionIntent                 ← encodeur passif d'identité
      │
      ▼
Phase 1 (génération AI)
      │
      ▼
Validation
      │
      ▼
Traductions (Phases 3/4)
      │
      ▼
READY_BANK
```

## Règle de transaction métier (VERROUILLÉE)

```
peekNext()
  → KLD
  → KEY_STRUCTURE
  → QuestionIntent créé avec succès
  → confirmConsumed()   ← UNIQUEMENT si tout réussit
```

Si KLD, KEY_STRUCTURE ou QuestionIntent échoue → `confirmConsumed()` n'est JAMAIS appelé.
Le prochain `peekNext()` retourne exactement la même paire (retry automatique).

## Séparation stricte des responsabilités

| Brique | Rôle exact | Autorité exclusive |
|---|---|---|
| KernelRotationPlanner | Planificateur | depth, domain_code, DomainCycle |
| TaxonomyProgressManager | **Chargeur Taxonomy / Idea Loader** (chargeur à 2 temps) | sub_domain → 50 sujets → sujet actif → 5 idées → 1 idée candidate |
| KLD | **Filtre de validation** — anti-répétition pédagogique | sub_domain/sujet/idée — APRÈS tirage d'une idée candidate |
| KEY_STRUCTURE | **Filtre de validation** — qualité taxonomique | pré-code yy-xx-xxx-xxx-xxx-zz |
| QuestionIntent | Encodeur passif | identité complète du noyau |

## Vocabulaire verrouillé (2026-07-04)

- `TaxonomyProgressManager` = chargeur Taxonomy / Idea Loader
  - Chargeur 1 : sous-domaine + sujets (jusqu'à 50)
  - Chargeur 2 : idées dominantes du sujet actif (5)
  - Tire UNE idée candidate → l'envoie à KLD + KEY_STRUCTURE
  - Avance automatiquement : 5 idées épuisées → sujet suivant ; 50 sujets épuisés → sous-domaine suivant
- `KLD` + `KEY_STRUCTURE` = filtres de validation, PAS des chargeurs
- Le **noyau final** ne transporte QUE : depth, domain, sub_domain, subject, dominant_idea, knowledge_frequency
  — jamais les 50 sujets, les 5 idées, ni la progression

## Contenu exact transporté entre TaxonomyProgressManager et KLD

```
depth               int
domain_code         string
sub_domain          string
subject             string
dominant_idea       string
knowledge_frequency int
```

**Jamais transporté** : les 50 sujets, les 5 idées, les sous-domaines disponibles, la progression.

**Why:** Retirer la responsabilité de progression de Taxonomy du moteur de génération simplifie
tous les composants suivants : KLD, KEY_STRUCTURE et QuestionIntent ne raisonnent plus jamais
sur un ensemble de 50 sujets × 5 idées — ils reçoivent une seule paire et la traitent.
