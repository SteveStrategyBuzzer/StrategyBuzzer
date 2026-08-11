---
name: NOYAU MÈRE — structure officielle (KERNEL BLUEPRINT)
description: Définition verrouillée du conteneur NOYAU MÈRE produit par KERNEL BLUEPRINT — contexte, 7 cognitifs, 4 slots/cognitif, rules/mechanisms (6 catégories), statuts, 6 traces par slot. Vérificateurs externes + phases + quarantaine.
---

# NOYAU MÈRE — structure officielle (VERROUILLÉE)

Le NOYAU MÈRE est un **conteneur intelligent préstructuré** produit par KERNEL BLUEPRINT.
Il porte son identité, son contexte intellectuel, ses cognitifs, ses slots, les règles/mécanismes
associés, les statuts et les traces propres à chaque slot. Les vérificateurs externes (KLD,
KEY_STRUCTURE, QUESTIONINTENT) **travaillent sur lui** mais n'en font **pas partie**.

## NOYAU MÈRE = UNE ENTITÉ COMPLÈTE (VERROUILLÉ 2026-06-16 — corrige l'ancienne notion de « puce »)
- **1 NOYAU MÈRE = 1 SOUS-DOMAINE = UNE entité complète indivisible.** Cohérent avec « 1 passe KEY_STRUCTURE = 1 sous-domaine » (entrée de KEY_STRUCTURE = le NOYAU MÈRE complet).
- **Il n'existe PAS** plusieurs noyaux dérivés. **PAS** de puces indépendantes. **PAS** de découpage sujet/idée.
- `subjects[1..50]` × `dominant_ideas[1..5]` + les 7 cognitifs × 4 slots sont la **STRUCTURE INTERNE** d'un SEUL noyau, jamais des objets séparés.
- QUESTIONINTENT encode ce noyau mère comme **UN seul objet** (une seule identité). L'ancien modèle « fan-out / 5 puces / 1 idée = 1 noyau » est ABANDONNÉ.
- Conséquences à réconcilier (granularité d'identité gameplay + unité de comptage Ready_Bank + clé d'unicité) : voir questionintent-contract.md.

## 1. Contexte intellectuel
- `depth`, `domain`, `sub_domain` (UN sous-domaine ACTIF, pas tout le domaine)
- `subjects[1..50]` → `{ subject, dominant_ideas[1..5] }`

### Granularité ACTIVE + génération LAZY (VERROUILLÉ 2026-06-19)
- Le noyau = 1 domaine + **1 sous-domaine actif** + jusqu'à **50 sujets** (coquilles) + **5 idées dominantes uniquement pour le SUJET ACTIF**.
- Les autres sujets EXISTENT dans le noyau mais leurs `dominant_ideas[1..5]` restent **EMPTY** jusqu'à activation.
- Quand le sujet actif est terminé → Rotation/KLD active le sujet suivant → Taxonomy génère SES 5 idées. Tous sujets épuisés → KLD demande changement de sous-domaine.
- ⚠️ ÉCART CODE : `resources/rotation/taxonomy.json` est aujourd'hui EAGER et sous-dimensionné (8 domaines × 4 sous-domaines × 4 sujets × 5 idées, toutes pré-remplies). La cible est LAZY (idées générées à l'activation) et ≤50 sujets par sous-domaine.

## 2. Cognitifs préinscrits (7) — instanciés par (subject × dominant_idea)
`qcm_recognition`, `qcm_reasoning`, `qcm_deceptive_trap`,
`tf_recognition_true`, `tf_recognition_false`, `tf_reasoning_true`, `tf_reasoning_false`

## 3. Slots de chaque cognitif (4)
`Questions`, `Réponses`, `Saviez-vous`, `Traductions`

## 4. Règles, Mécanismes & Contraintes PAR SLOT
Chaque slot porte `rules`, `mechanisms` et `constraints`. Les contraintes couvrent explicitement (6 catégories) :
- mécanisme cognitif attendu
- contraintes cognitives
- contraintes gameplay
- contraintes de Depth
- contraintes de validation
- contraintes de traduction

## 5. Statuts de slot
`EMPTY` → travail → `VALIDATED_OK` **ou** `WARNING`.
Définitions identiques au contrat QUESTIONINTENT :
- EMPTY = non rempli (partiel autorisé, D5)
- VALIDATED_OK = conforme → gameplay + traduction possibles
- WARNING = non conforme → ni gameplay ni traduction → correction via Quarantaine

## 6. Traces PAR SLOT (6) — NOUVEAU vs anciens contrats (avant : « traçabilité » globale)
Chaque slot possède SES propres traces :
- `trace_creation`
- `trace_validation`
- `trace_translation`
- `trace_translation_validation`
- `trace_correction`
- `trace_replacement`

## Vérificateurs externes (travaillent SUR le noyau, n'en font pas partie)
- **KEY_LEARNING_DIRECTION** — entrée : sujet actif + idée dominante active → vérifie la paire (anti-répétition de direction).
- **KEY_STRUCTURE** — entrée : NOYAU MÈRE → vérifie la structure complète : Depth, cohérence structurelle, égrainage naturel, domaine, sous-domaine, sujets, idées dominantes.
- **QUESTIONINTENT** — encode le NOYAU MÈRE COMPLET comme UN seul objet → produit son identité exploitable (pas de puces, pas de fan-out). Il **ne valide pas, ne corrige pas, ne choisit pas, ne remplit pas** : il encode.

## Phases — qui écrit quelle trace
- **PHASE 1** (travail) : remplit Questions / Réponses / Saviez-vous → écrit `trace_creation`.
- **PHASE 2** (vérif P1) : `VALIDATED_OK` ou `WARNING` → écrit `trace_validation`.
- **PHASE 3** (travail) : remplit Traductions, **uniquement** pour les slots création `VALIDATED_OK` → écrit `trace_translation`.
- **PHASE 4** (vérif P3) : `VALIDATED_OK` ou `WARNING` → écrit `trace_translation_validation`.
- **QUARANTAINE** (environnement transversal) : reçoit un PRINT / une COPIE DE TRAVAIL du noyau (⚠️ MAJ 2026-08-11 — jamais le noyau canonique lui-même, qui reste dans le flow principal), identifie les slots WARNING, corrige dans le print, garde les traces, réintègre les slots corrigés dans le noyau canonique → écrit `trace_correction` + `trace_replacement`.

## Définition officielle verrouillée (texte de référence)
« Le NOYAU MÈRE est un conteneur intelligent préstructuré qui porte son identité, son contexte
intellectuel, ses cognitifs, ses slots, les règles et mécanismes associés, les statuts et les traces
propres à chaque slot. KEY_LEARNING_DIRECTION et KEY_STRUCTURE travaillent sur lui. QUESTIONINTENT
l'encode. PHASES 1 et 3 produisent le travail, PHASES 2 et 4 vérifient ce travail, et QUARANTAINE
corrige les anomalies sans briser le pipeline. »

**Why:** Verrouillé avec l'utilisateur le 2026-06-15, corrigé le 2026-06-16. Fixe le NOYAU MÈRE
comme conteneur de sous-domaine (≤50 sujets) avec règles/mécanismes/contraintes ET traces AU NIVEAU
SLOT (pas au niveau noyau), ce qui rend chaque slot auditable de bout en bout (création → validation
→ traduction → correction). CORRECTION 2026-06-16 : le NOYAU MÈRE est UNE entité complète indivisible
— pas de puce, pas de fan-out, pas de découpage sujet/idée. Les subjects/idées/cognitifs sont la
structure INTERNE d'un seul noyau, jamais des objets séparés.
