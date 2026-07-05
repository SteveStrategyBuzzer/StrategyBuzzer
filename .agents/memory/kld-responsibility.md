---
name: KEY_LEARNING_DIRECTION — responsabilité exacte
description: Scope verrouillé de KEY_LEARNING_DIRECTION dans le pipeline noyau — filtre rapide + alerte de risque, 3 sorties.
---

# KEY_LEARNING_DIRECTION — responsabilité exacte (verrouillée)

Pipeline v2 (2026-07-04) : KLD est le **garde d'entrée du chargeur d'idées**, appelé à l'intérieur de Taxonomy pendant le remplissage slot par slot.

## Mission officielle (verrouillée 2026-07-05)

**Empêcher qu'un même sujet enseigne deux fois la même direction d'apprentissage sous une formulation différente.**

KLD ne compare pas des mots. **KLD compare des directions d'apprentissage.**

KLD est un **filtre rapide + émetteur d'alerte de risque**. KEY_STRUCTURE est le **juge structurel final**.

```
Transport → Voiture   = dossier pédagogique créé

Transport → Auto      → KLD FAIL          (synonyme direct — doublon certain)
Transport → Camion    → KLD REVIEW_STRUCTURE  (voisin — risque contextuel → KEY_STRUCTURE tranche)
Transport → Avion     → KLD PASS          (direction distincte)
```

## 3 sorties (verrouillées 2026-07-05)

| Sortie | Signification | Déclencheur |
|---|---|---|
| `FAIL` | Doublon certain | KLD-1, KLD-2, KLD-3, KLD-4, KLD-5 |
| `REVIEW_STRUCTURE` | Risque de doublon contextuel — KEY_STRUCTURE tranche | KLD-6 |
| `PASS` | Aucune collision détectée | Aucune règle déclenchée |

**FAIL** = KLD est certain : même dossier pédagogique, rien à vérifier.
**REVIEW_STRUCTURE** = KLD détecte un risque : idée voisine, même sujet, collision possible — KEY_STRUCTURE décide.
**PASS** = direction inédite détectée, KEY_STRUCTURE vérifie la structure (sans alerte).

## Stade du pipeline
À ce point : aucune question, aucune réponse, aucun Saviez-vous n'existe encore.

## Travaille UNIQUEMENT sur 3 champs
- Sous-domaine (sub_domain)
- Sujet (subject)
- Idée Dominante (dominant_idea)

Ne raisonne JAMAIS sur : QCM, V/F, Recognition, Reasoning, Deceptive Trap, réponses, Saviez-vous, depth/notoriété.

## KEY_LEARNING_DIRECTION_RULESET (v5 — 2026-07-05)

- **KLD-1** subject === dominant_idea → **FAIL** `INVALID_MINIMAL_PAIR`
- **KLD-2** paire directe exacte déjà dans registry, même sub_domain → **FAIL** `DIRECT_PAIR_CONTEXT_DUPLICATE`
- **KLD-3** paire inversée exacte déjà dans registry → **FAIL** `REVERSED_PAIR_CONTEXT_DUPLICATE`
- **KLD-4** synonyme direct via `LearningDirectionLexicon::getSynonyms()` (voiture≈auto≈char≈bagnole) → **FAIL** `CONCEPTUAL_COLLISION`
- **KLD-5** sub_domain différent + `LearningDirectionLexicon::getContextRules()` silencieux → **FAIL** `CONTEXT_NOT_DISTINCT`
- **KLD-6** idée voisine non synonyme strict d'un dossier existant, même sujet → **REVIEW_STRUCTURE** `POSSIBLE_CONTEXTUAL_DUPLICATE`

KLD-6 ne produit jamais FAIL. KEY_STRUCTURE est l'autorité finale pour REVIEW_STRUCTURE.

## Frontière KLD / KEY_STRUCTURE (v5 — 2026-07-05)

| Cas | KLD retourne | KEY_STRUCTURE |
|---|---|---|
| transport + voiture → transport + auto | `FAIL` | — (jamais appelé) |
| transport + voiture → transport + camion | `REVIEW_STRUCTURE` | tranche (distinct ? trop proche ? depth ?) |
| direction inédite | `PASS` | vérifie structure (sans alerte) |
| idée hors depth | `PASS` | `FAIL` structure |
| découpage illogique | `PASS` | `FAIL` structure |

**KLD = filtre rapide + alerte de risque.**
**KEY_STRUCTURE = juge structurel final.**

## Cycle chargeur d'idées (mis à jour 2026-07-05)

```
Taxonomy propose idée
    → KLD vérifie
        → FAIL          : rejeter, Taxonomy propose une autre idée
        → REVIEW_STRUCTURE : forward KEY_STRUCTURE avec alerte POSSIBLE_CONTEXTUAL_DUPLICATE
        → PASS          : forward KEY_STRUCTURE (chemin normal)
    → KEY_STRUCTURE analyse (avec ou sans alerte KLD)
        → PASS          : remplir le slot
        → FAIL          : rejeter, Taxonomy propose une autre idée
```

## Contexte v4 → v5

v4 (2026-07-04) : KLD avait 2 sorties (PASS/FAIL). Les concepts voisins "passaient KLD" sans signal.
v5 (2026-07-05) : KLD a 3 sorties. Les concepts voisins déclenchent REVIEW_STRUCTURE — KEY_STRUCTURE tranche explicitement au lieu de recevoir silencieusement un concept potentiellement trop proche.

**Why:** Un concept voisin (camion ≈ voiture) peut être une collision pédagogique à un depth élevé même sans être un synonyme direct. KLD ne peut pas trancher seul (il ne connaît ni le depth ni la structure du noyau). Il signale le risque et KEY_STRUCTURE décide.

**How to apply:** REVIEW_STRUCTURE ne bloque pas — il alerte. KEY_STRUCTURE reçoit le résultat KLD complet (status + reason + normalized fields) avant toute décision structurelle. Si KEY_STRUCTURE est absent ou non implémenté, traiter REVIEW_STRUCTURE comme PASS par défaut (défaut permissif temporaire, à remplacer quand KEY_STRUCTURE est fermé).
