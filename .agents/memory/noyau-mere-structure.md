---
name: NOYAU MÈRE — structure officielle (KERNEL BLUEPRINT)
description: Définition verrouillée du conteneur NOYAU MÈRE produit par KERNEL BLUEPRINT — contexte, 7 cognitifs, 4 slots/cognitif, rules/mechanisms (6 catégories), statuts, 6 traces par slot. Vérificateurs externes + phases + quarantaine.
---

# NOYAU MÈRE — structure officielle (VERROUILLÉE)

Le NOYAU MÈRE est un **conteneur intelligent préstructuré** produit par KERNEL BLUEPRINT.
Il porte son identité, son contexte intellectuel, ses cognitifs, ses slots, les règles/mécanismes
associés, les statuts et les traces propres à chaque slot. Les vérificateurs externes (KLD,
KEY_STRUCTURE, QUESTIONINTENT) **travaillent sur lui** mais n'en font **pas partie**.

## Granularité (réconciliation terminologique — IMPORTANT)
- **1 NOYAU MÈRE = 1 SOUS-DOMAINE.** Cohérent avec « 1 passe KEY_STRUCTURE = 1 sous-domaine »
  (KEY_STRUCTURE a pour entrée le NOYAU MÈRE complet).
- Le noyau mère porte `subjects[1..50]`, chacun avec `dominant_ideas[1..5]`.
- Les structures cognitives (7 cognitifs × 4 slots) existent **par paire (subject × dominant_idea)** À L'INTÉRIEUR du noyau mère.
- **PUCE = question_intent** = encodage QUESTIONINTENT d'**UNE** paire (subject, idée dominante).
  C'est l'unité de travail de PHASE 1. L'« Option A fan-out » (« 1 noyau = 1 subject + 1 idée »)
  désigne la PUCE, pas le noyau mère : 1 sujet actif (5 idées valides) → 5 puces.
- Donc : NOYAU MÈRE = conteneur (sous-domaine) ; PUCE = tranche (subject, idée) encodée et adressable.

## 1. Contexte intellectuel
- `depth`, `domain`, `sub_domain`
- `subjects[1..50]` → `{ subject, dominant_ideas[1..5] }`

## 2. Cognitifs préinscrits (7) — instanciés par (subject × dominant_idea)
`qcm_recognition`, `qcm_reasoning`, `qcm_deceptive_trap`,
`tf_recognition_true`, `tf_recognition_false`, `tf_reasoning_true`, `tf_reasoning_false`

## 3. Slots de chaque cognitif (4)
`Questions`, `Réponses`, `Saviez-vous`, `Traductions`

## 4. Règles & mécanismes PAR SLOT (6 catégories) — NOUVEAU vs anciens contrats (était au niveau noyau)
Chaque slot porte `rules` + `mechanisms` couvrant explicitement :
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
- **QUESTIONINTENT** — encode le NOYAU MÈRE → produit son identité exploitable (puces). Il **ne valide pas, ne corrige pas, ne choisit pas, ne remplit pas** : il encode.

## Phases — qui écrit quelle trace
- **PHASE 1** (travail) : remplit Questions / Réponses / Saviez-vous → écrit `trace_creation`.
- **PHASE 2** (vérif P1) : `VALIDATED_OK` ou `WARNING` → écrit `trace_validation`.
- **PHASE 3** (travail) : remplit Traductions, **uniquement** pour les slots création `VALIDATED_OK` → écrit `trace_translation`.
- **PHASE 4** (vérif P3) : `VALIDATED_OK` ou `WARNING` → écrit `trace_translation_validation`.
- **QUARANTAINE** (environnement transversal) : clone le noyau mère complet, identifie les slots WARNING, corrige, garde les traces, renvoie les slots corrigés → écrit `trace_correction` + `trace_replacement`.

## Définition officielle verrouillée (texte de référence)
« Le NOYAU MÈRE est un conteneur intelligent préstructuré qui porte son identité, son contexte
intellectuel, ses cognitifs, ses slots, les règles et mécanismes associés, les statuts et les traces
propres à chaque slot. KEY_LEARNING_DIRECTION et KEY_STRUCTURE travaillent sur lui. QUESTIONINTENT
l'encode. PHASES 1 et 3 produisent le travail, PHASES 2 et 4 vérifient ce travail, et QUARANTAINE
corrige les anomalies sans briser le pipeline. »

**Why:** Verrouillé avec l'utilisateur le 2026-06-15. Fixe le NOYAU MÈRE comme conteneur de
sous-domaine (≤50 sujets) avec règles/mécanismes ET traces AU NIVEAU SLOT (pas au niveau noyau),
ce qui rend chaque slot auditável de bout en bout (création → validation → traduction → correction).
La PUCE (question_intent) reste l'unité Phase 1 (1 subject + 1 idée) : le fan-out QUESTIONINTENT
n'est pas remis en cause, il est resitué comme tranche du noyau mère.
