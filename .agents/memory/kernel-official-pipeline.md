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

- `TaxonomyProgressManager` = gestionnaire des chargeurs Taxonomy par **depth + domain_code**
  - Autorité de progression INTERNE — gère le bassin complet d'un depth
  - Chargeur 1 : sous-domaine actif + jusqu'à 50 sujets
  - Chargeur 2 : idées dominantes du sujet actif (5)
  - Tire UNE idée candidate → l'envoie à KLD + KEY_STRUCTURE
  - Avance automatiquement : 5 idées épuisées → sujet suivant ; 50 sujets épuisés → sous-domaine suivant
- `KLD` + `KEY_STRUCTURE` = filtres de validation, PAS des chargeurs
- Le **noyau final** ne transporte QUE : depth, domain, sub_domain, subject, dominant_idea, knowledge_frequency
  — jamais les 8 domaines, les 50 sujets, les 5 idées, ni l'état des chargeurs

## Structure du bassin Taxonomy (verrouillée 2026-07-04, vocabulaire corrigé)

Le bassin Taxonomy est le **moteur Taxonomy** — pas un conteneur passif.
Pour un depth donné, il contient 8 machines indépendantes (une par domaine Gameplay),
chacune composée de 2 chargeurs nommés explicitement :

```
                    DEPTH N
          ┌─────────────────────────────┐
          │        BASSIN TAXONOMY      │   (moteur, pas conteneur)
          └─────────────────────────────┘

  histoire
     ├── Chargeur de sujets   : sous-domaine actif → jusqu'à 50 sujets
     └── Chargeur d'idées     : sujet actif → 5 idées dominantes

  geographie
     ├── Chargeur de sujets
     └── Chargeur d'idées

  sport / art / cuisine / science / cinema / faune
     ├── Chargeur de sujets   (idem)
     └── Chargeur d'idées     (idem)
```

**8 machines indépendantes** — chacune avance à son propre rythme.
KernelRotationPlanner synchronise quelle machine est active à chaque tirage.

**Progression naturelle (garantie par le chargeur, sans règle spéciale) :**
- Chargeur d'idées épuisé (5 idées) → sujet suivant dans le Chargeur de sujets
- Chargeur de sujets épuisé (50 sujets) → sous-domaine suivant
- Maximisation automatique : un sous-domaine ne change jamais avant que ses 50 sujets × 5 idées soient épuisés

**Règles du bassin :**
- KernelRotationPlanner demande un depth → le moteur Taxonomy active les 8 chargeurs de ce depth
- Le moteur tire UNE idée candidate à la fois, selon la rotation des domaines
- Un depth n'est complet que lorsque les 8 domaines de ce depth sont entièrement exploités

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
