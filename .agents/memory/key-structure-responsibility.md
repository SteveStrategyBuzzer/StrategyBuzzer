---
name: KEY_STRUCTURE — responsabilité exacte
description: Frontière verrouillée entre KEY_STRUCTURE (validation structurelle) et QuestionIntent (verrouillage/encodage/ks_hash).
---

# KEY_STRUCTURE — responsabilité exacte

KEY_STRUCTURE = **gardien de l'égrainage taxonomique**. VALIDATION SEULEMENT. Autorise ou refuse.

## Ce qu'il valide (qualité structurelle)
- Cohérence taxonomique (sous-domaine ∈ domaine, sujet ∈ sous-domaine, idée ∈ sujet, via taxonomy.json)
- Progression : Domaine → Sous-domaine → Sujet → **5 Idées Dominantes**
- Qualité de l'égrainage (FORMAT_MINIMAL_IRREDUCTIBLE : chaque niveau = unité de sens minimale, aucun niveau n'absorbe l'autre, pas de sous-domaine artificiel, idée ≠ phrase Phase 1)
- Adéquation au Depth demandé (subject_profile du Sujet ↔ Depth ; knowledge_frequency de l'Idée ↔ bande du Depth — LECTURE seulement)

## Ce qu'il NE fait PAS
- **Pas de ks_hash** (= QuestionIntent)
- Pas d'encodage du noyau (= QuestionIntent)
- Pas de création/remplissage (= Phase 1)
- Pas de navigation (= TaxonomyReader sur refus)
- Pas d'anti-doublon pédagogique (= KEY_LEARNING_DIRECTION, déjà passé avant)
- Ne calcule pas knowledge_frequency (= donnée taxonomy/DepthContract)

## Frontière verrouillée
```
KEY_STRUCTURE   = validation (PASS/FAIL)
QUESTIONINTENT  = verrouillage / encodage / ks_hash  → puis PHASE 1 CRÉATION
```
Si KEY_STRUCTURE répond PASS, alors SEULEMENT QuestionIntent intervient. C'est QuestionIntent qui opère le ks_hash et encode le noyau pour qu'il parte en Phase 1.

**Why:** L'utilisateur a explicitement retiré ks_hash de KEY_STRUCTURE. Mélanger validation et verrouillage casserait la causalité KEY_STRUCTURE → QuestionIntent (sens unique).

**How to apply:** Sortie KEY_STRUCTURE = PASS|FAIL + reason + identité validée, SANS ks_hash. Toute idempotence/unicité d'identité complète appartient à QuestionIntent, pas à KEY_STRUCTURE. (Contrat détaillé encore en cours : table Depth↔niveau et déclencheur des 2 stades à confirmer.)
