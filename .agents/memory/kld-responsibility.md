---
name: KEY_LEARNING_DIRECTION — responsabilité exacte
description: Scope verrouillé de KEY_LEARNING_DIRECTION — registre de dossiers pédagogiques + synonymes directs. 3 sorties. Mécanisme en 7 étapes.
---

# KEY_LEARNING_DIRECTION — responsabilité exacte (verrouillée)

## Mission officielle (verrouillée 2026-07-05)

**Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage sous une formulation différente.**

KLD maintient un **registre de dossiers pédagogiques** — structure minimaliste et codable.

## Dossier pédagogique

Chaque idée validée crée un dossier identifié par une clé :

```
direction_key = subject_canonical_key + "::" + idea_canonical_key
```

Exemple :
```
transport::voiture   (dossier créé)

transport::auto      → résolu vers transport::voiture → FAIL
transport::char      → résolu vers transport::voiture → FAIL
transport::bagnole   → résolu vers transport::voiture → FAIL

transport::camion    → direction_key inexistant
                     → mais transport:: a déjà un dossier
                     → REVIEW_STRUCTURE
```

## Mécanisme en 7 étapes (verrouillé 2026-07-05)

```
1. Normaliser le sujet                → subject_key
2. Normaliser l'idée                  → idea_key
3. Résoudre idea_key via getSynonyms() → idea_canonical_key
4. Construire direction_key = subject_key + "::" + idea_canonical_key
5. registry.contains(direction_key) ?
   → oui → FAIL  DIRECT_PAIR_CONTEXT_DUPLICATE
6. registry.hasSubject(subject_key) ?
   → oui → REVIEW_STRUCTURE  POSSIBLE_CONTEXTUAL_DUPLICATE
7. sinon → PASS
```

## 3 sorties

| Sortie | Signification |
|---|---|
| `FAIL` | Dossier exact déjà enregistré (même direction ou synonyme) |
| `REVIEW_STRUCTURE` | Sujet déjà utilisé pour une autre direction — KEY_STRUCTURE tranche |
| `PASS` | Dossier inédit, sujet inédit |

**FAIL** → rejeter, Taxonomy propose une autre idée.
**REVIEW_STRUCTURE** → forward KEY_STRUCTURE avec alerte `POSSIBLE_CONTEXTUAL_DUPLICATE`.
**PASS** → forward KEY_STRUCTURE (chemin normal).

## Stade du pipeline

KLD est le **garde d'entrée du chargeur d'idées**, appelé à l'intérieur de Taxonomy pendant le remplissage slot par slot. Pas une étape standalone.

À ce point : aucune question, aucune réponse, aucun Saviez-vous n'existe encore.

## Travaille UNIQUEMENT sur 3 champs
- Sous-domaine (sub_domain)
- Sujet (subject)
- Idée Dominante (dominant_idea)

Ne raisonne JAMAIS sur : QCM, V/F, Recognition, Reasoning, réponses, Saviez-vous, depth/notoriété.

## Ce que KLD ne fait pas

- Pas de carte de voisins (getNeighbors() SUPPRIMÉ — trop spéculatif)
- Pas de seuil de distance token
- Pas de DB
- Pas de hash
- Pas de jugement sur la qualité pédagogique d'une idée

## Ce que KEY_STRUCTURE reçoit

Quand KLD retourne REVIEW_STRUCTURE, KEY_STRUCTURE reçoit :
- l'idée proposée
- l'alerte `POSSIBLE_CONTEXTUAL_DUPLICATE`
- le dossier existant pour ce sujet (pour comparaison structurelle)

KEY_STRUCTURE décide : direction distincte, trop proche, hors depth, ou collision structurelle.

**Why:** KLD ne peut pas trancher si camion est trop proche de voiture dans Transport — il ne connaît ni le depth ni la structure du noyau. Il signale le risque. KEY_STRUCTURE dispose du contexte complet pour décider.

**How to apply:** Si KEY_STRUCTURE n'est pas encore implémenté, traiter REVIEW_STRUCTURE comme PASS temporairement (défaut permissif). Remplacer quand KEY_STRUCTURE est fermé.
