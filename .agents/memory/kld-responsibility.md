---
name: KEY_LEARNING_DIRECTION — responsabilité exacte
description: Scope verrouillé de KEY_LEARNING_DIRECTION — registre de dossiers pédagogiques + synonymes + familles d'idées. 3 sorties. Mécanisme en 7 étapes.
---

> ⛔ **SUPERSEDED 2026-08-11** — KLD/KEY_STRUCTURE retirés du flow canonique ; responsabilités absorbées par ValidationDominantIdeas. Ne pas rebrancher. Voir [canonical-kernel-flow.md](canonical-kernel-flow.md). Conservé pour historique.


# KEY_LEARNING_DIRECTION — responsabilité exacte (verrouillée)

## Mission officielle (verrouillée 2026-07-05)

**Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage sous une formulation différente.**

KLD maintient un **registre de dossiers pédagogiques** et deux lexiques : synonymes directs + familles d'idées proches.

## Résultats attendus par exemple

```
transport + voiture  → dossier créé

transport + auto     → FAIL             (synonyme direct de voiture)
transport + char     → FAIL             (synonyme direct de voiture)
transport + bagnole  → FAIL             (synonyme direct de voiture)

transport + camion   → REVIEW_STRUCTURE (même famille véhicules_routiers que voiture)
transport + autobus  → REVIEW_STRUCTURE (même famille véhicules_routiers que voiture)

transport + train    → PASS             (famille différente — transport_ferroviaire)
transport + avion    → PASS             (famille différente — transport_aérien)
transport + bateau   → PASS             (famille différente — transport_maritime)
```

## Mécanisme en 7 étapes (verrouillé 2026-07-05)

```
1. Normaliser le sujet                              → subject_key
2. Normaliser l'idée                                → idea_key
3. Résoudre idea_key via getSynonyms()              → idea_canonical_key
4. Construire direction_key = subject_key + "::" + idea_canonical_key
5. registry.contains(direction_key) ?
   → oui → FAIL  DIRECT_PAIR_CONTEXT_DUPLICATE
6. registry.getIdeasForSubject(subject_key) → existing_ideas[]
   Pour chaque existing_idea :
     familyIndex.sameFamily(domain_code, idea_canonical_key, existing_idea) ?
   → oui → REVIEW_STRUCTURE  POSSIBLE_CONTEXTUAL_DUPLICATE
7. sinon → PASS
```

## 3 sorties

| Sortie | Signification | Déclencheur |
|---|---|---|
| `FAIL` | Dossier exact déjà enregistré (même direction ou synonyme direct) | Étape 5 |
| `REVIEW_STRUCTURE` | Idée dans la même famille qu'une direction existante — KEY_STRUCTURE tranche | Étape 6 |
| `PASS` | Direction inédite, famille différente ou inconnue | Étape 7 |

**FAIL** → rejeter, Taxonomy propose une autre idée.
**REVIEW_STRUCTURE** → forward KEY_STRUCTURE avec alerte `POSSIBLE_CONTEXTUAL_DUPLICATE`.
**PASS** → forward KEY_STRUCTURE (chemin normal, sans alerte).

## Stade du pipeline

KLD est le **garde d'entrée du chargeur d'idées**, appelé à l'intérieur de Taxonomy pendant le remplissage slot par slot. Pas une étape standalone.

## Travaille UNIQUEMENT sur 3 champs
- Sous-domaine (sub_domain)
- Sujet (subject)
- Idée Dominante (dominant_idea)

Ne raisonne JAMAIS sur : QCM, V/F, Recognition, réponses, Saviez-vous, depth/notoriété.

## Ce que KLD ne fait pas
- Pas de seuil de distance token
- Pas de DB
- Pas de hash
- Pas de jugement sur la qualité pédagogique d'une idée — KEY_STRUCTURE tranche toujours

## Ce que KEY_STRUCTURE reçoit
Quand KLD retourne REVIEW_STRUCTURE :
- l'idée proposée
- l'alerte `POSSIBLE_CONTEXTUAL_DUPLICATE`
- les directions existantes pour ce sujet (pour comparaison structurelle)

KEY_STRUCTURE décide : direction distincte, trop proche, hors depth, ou collision structurelle.

**Why:** KLD détecte le risque familial mais ne connaît ni le depth ni la structure du noyau. KEY_STRUCTURE dispose du contexte complet. "même famille" = signal de prudence, pas de rejet automatique.

**How to apply:** Si KEY_STRUCTURE n'est pas encore implémenté, traiter REVIEW_STRUCTURE comme PASS temporairement. Remplacer quand KEY_STRUCTURE est fermé.
