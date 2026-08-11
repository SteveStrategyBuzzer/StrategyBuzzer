---
name: Kernel Flow Official
description: Flow officiel "Blueprint First". Source de vérité pour toute implémentation kernel/rotation. CORRIGÉ 2026-06-19 — QUESTIONINTENT encode l'identité DANS Rotation (après Depth+Domaine), AVANT KLD/KEY_STRUCTURE.
---

> ⛔ **SUPERSEDED 2026-08-11** — KLD/KEY_STRUCTURE retirés du flow canonique ; responsabilités absorbées par ValidationDominantIdeas. Ne pas rebrancher. Voir [canonical-kernel-flow.md](canonical-kernel-flow.md). Conservé pour historique.


# Flow officiel Kernel — Blueprint First

Document de référence validé. Remplace toutes les interprétations précédentes.

## Séquence principale

```
KernelBlueprint → NOYAU MÈRE VIDE
→ ROTATION PAR NOYAU COMPLET { BankTarget ; DepthNeedMatrix→Depth ; DomainCycle→Domaine ; QuestionIntent→encode l'identité ; PROGRESSION DU NOYAU }
→ TaxonomyReader → KEY_STRUCTURE → KEY_LEARNING_DIRECTION (KLD)
→ Phase 1 Création → Phase 2 Validation
→ Phase 3 Traduction → Phase 4 Validation Traduction
→ Ready_Bank → Gameplay
```
⚠️ CORRECTION 2026-06-19 (ROTATION PAR NOYAU COMPLET — détail dans `rotation-par-noyau-complet.md`) :
1. QuestionIntent encode l'identité DANS Rotation (après Depth+Domaine), pas après KEY_STRUCTURE.
2. Rotation est l'orchestrateur DÉTERMINISTE de progression des noyaux (Depth→Domaine→sous-domaine→sujet actif→idées 1-5→sujet suivant→sous-domaine suivant). AUCUN hasard.
3. **KEY_STRUCTURE intervient AVANT KLD** (réordre : auparavant KLD→KEY_STRUCTURE). KEY_STRUCTURE construit/vérifie le pré-code `yy-xx-xxx-xxx-xxx-zz`, détecte les collisions, gère `zz` ; quand collision sur `yy-xx-xxx-xxx-xxx` il APPELLE KLD pour trancher (même sujet+idée = FAIL → idée/sujet/sous-domaine suivant ; différent = PASS → `zz`).
Raison : reprendre le même noyau plus tard (sujet suivant, sujets restants, idées utilisées, anti-doublon, remplissage progressif, correction Quarantaine, identité stable en Ready_Bank).

## Branche parallèle (quarantaine)

```
WARNING → Phase 6 Quarantaine → Phase 7 Correction
→ Phase 2 → Phase 3 → Phase 4 → Ready_Bank
```

## Causalité verrouillée — sens unique

```
ROTATION inscrit Depth + Domaine (progression déterministe)
QuestionIntent → encode l'identité du noyau (DANS Rotation, après Depth+Domaine)
Taxonomy → fournit sous-domaine + sujet actif + 5 idées (matière, ne décide pas la progression)
KEY_STRUCTURE → pré-code yy-xx-xxx-xxx-xxx-zz + détection collision + gestion zz ; appelle KLD si collision
KLD → arbitre la collision intellectuelle (même sujet+idée = FAIL ; différent = PASS+zz) ; signale sous-domaine épuisé
Phase 1 → remplit le noyau identifié

INTERDIT : QuestionIntent choisit/valide/crée du contenu (il encode SEULEMENT)
INTERDIT : Rotation pige au hasard (progression méthodique obligatoire)
INTERDIT : Taxonomy décide quand avancer idée/sujet/sous-domaine
```
ANCIEN (ABANDONNÉ) : « KEY_STRUCTURE autorise → QuestionIntent pose la puce » ET l'ordre « KLD → KEY_STRUCTURE ». Nouveau : QuestionIntent encode dans Rotation ; KEY_STRUCTURE AVANT KLD.

## Responsabilités officielles

| Étape | Responsabilité | Ce qu'elle ne fait PAS |
|---|---|---|
| KernelBlueprint | Ouvrir le frame vide | Aucune décision métier |
| BankTarget | Stop si cible atteinte (noyaux complets, pas question_groups) | Ne choisit pas le depth |
| DepthNeedMatrix | Choisit le Depth ONLY — rotation 2×8/4×8/6×8/7×8/8×8/9×8/10×8 | Ne choisit jamais le domaine |
| DomainCycle | Choisit le Domaine ONLY — Géographie→Histoire→Faune→Art→Sport→Cinéma→Cuisine→Général | Ne choisit pas le depth |
| TaxonomyReader | Propose sub_domain+subject+idee_dominante + navigation fallback sur exclusions | Ne décide pas la production globale |
| KEY_STRUCTURE (AVANT KLD) | Construit/vérifie le pré-code `yy-xx-xxx-xxx-xxx-zz`, détecte collisions, gère `zz` ; appelle KLD si collision | Ne crée pas le contenu, ne tranche pas la collision intellectuelle |
| KEY_LEARNING_DIRECTION (KLD) | Arbitre la collision : même sujet+idée = FAIL (idée/sujet/sous-domaine suivant) ; différent = PASS+zz ; signale sous-domaine épuisé | Ne construit pas la structure, ne fait pas le pré-code |
| QuestionIntent | Encode l'identité du noyau DANS Rotation (après Depth+Domaine), AVANT KLD/KEY_STRUCTURE | Ne valide rien, ne choisit rien, ne décide rien, ne crée aucun contenu |
| Phase 1 | Génère les 7 cognitifs sur le noyau pucé | Ne modifie JAMAIS key/subject/idee_dominante |
| Phase 2 | Valide chaque variant → VALIDATED_OK ou WARNING | |
| Phase 3 | Traduit UNIQUEMENT les VALIDATED_OK | |
| Phase 4 | Valide chaque traduction → VALIDATED_OK ou WARNING | |
| Ready_Bank | Reçoit VALIDATED_OK — noyau partiel accepté (ex: 4/7) | |
| Phase 6 Quarantaine | Trace du WARNING — référence QuestionIntent — ne bloque pas Ready_Bank | |
| Phase 7 Correction | Corrige WARNING → retour Phase 2 | |

## DEPTH NEED MATRIX — rotation officielle

```
Depth 2  × 8 domaines
Depth 4  × 8 domaines
Depth 6  × 8 domaines
Depth 7  × 8 domaines
Depth 8  × 8 domaines
Depth 9  × 8 domaines
Depth 10 × 8 domaines
→ recommence (peut sauter si besoin rempli, suit déficits réels)
```

## DOMAIN CYCLE — ordre officiel

```
Géographie → Histoire → Faune → Art → Sport → Cinéma → Cuisine → Général
```

## TaxonomyReader — navigation fallback sur doublon

```
Si KEY_LEARNING_DIRECTION ou KEY_STRUCTURE signalent un doublon/manque :
  1. autre idee_dominante du même sujet (dans sous-domaine)
  2. autre sujet du même sous-domaine
  3. autre sous-domaine du même domaine
Si exhausted: true → KernelRotationPlanner → DomainCycle avance
```

## QuestionIntent — rôle exact (puce persistante)

```
QuestionIntent permet la dissociation/réassociation entre :
  KernelBlueprint, variants, traductions, quarantaine, correction, Ready_Bank, gameplay

Encodé DANS Rotation, après Depth + Domaine (AVANT KLD/KEY_STRUCTURE)
Contient : domain, sub_domain, subject, idee_dominante, difficulty_depth,
           knowledge_frequency, ks_hash, kld_hash, source='rotation', frame_status=NULL
```

## Ready_Bank peut contenir

- noyau 7/7 validé
- ou uniquement les variants validés d'un noyau partiel (ex: 4/7)

**Why:** Éviter de bloquer les VALIDATED_OK sur un WARNING. Partiel accepté.

## 6 composants Rotation à créer

```
app/Services/QuestionBank/Rotation/
  ├── KernelRotationPlanner.php   orchestrateur
  ├── TaxonomyReader.php          données + navigation fallback
  ├── DepthNeedMatrix.php         curseur Depth (Redis)
  ├── DomainCycle.php             curseur Domaine (Redis)
  ├── IntentKeyBuilder.php        KEY_LEARNING_DIRECTION + KEY_STRUCTURE
  └── FrequencyEvaluator.php      évalue knowledge_frequency via Node AI
```
