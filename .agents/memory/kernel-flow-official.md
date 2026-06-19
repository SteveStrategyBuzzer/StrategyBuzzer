---
name: Kernel Flow Official
description: Flow officiel "Blueprint First". Source de vérité pour toute implémentation kernel/rotation. CORRIGÉ 2026-06-19 — QUESTIONINTENT encode l'identité DANS Rotation (après Depth+Domaine), AVANT KLD/KEY_STRUCTURE.
---

# Flow officiel Kernel — Blueprint First

Document de référence validé. Remplace toutes les interprétations précédentes.

## Séquence principale

```
KernelBlueprint → NOYAU MÈRE VIDE
→ ROTATION { BankTarget ; DepthNeedMatrix→Depth ; DomainCycle→Domaine ; QuestionIntent→encode l'identité }
→ NOYAU MÈRE IDENTIFIÉ/ENCODÉ
→ TaxonomyReader → KEY_LEARNING_DIRECTION → KEY_STRUCTURE
→ Phase 1 Création → Phase 2 Validation
→ Phase 3 Traduction → Phase 4 Validation Traduction
→ Ready_Bank → Gameplay
```
⚠️ CORRECTION 2026-06-19 : QuestionIntent est un mécanisme d'encodage APPELÉ DANS Rotation (après que Depth + Domaine sont inscrits), pas une étape après KEY_STRUCTURE. KLD et KEY_STRUCTURE travaillent donc SUR un noyau DÉJÀ identifié. Raison : pouvoir reprendre le même noyau plus tard (sujet suivant, sujets restants, idées utilisées, anti-doublon, remplissage progressif, correction Quarantaine, identité stable en Ready_Bank).

## Branche parallèle (quarantaine)

```
WARNING → Phase 6 Quarantaine → Phase 7 Correction
→ Phase 2 → Phase 3 → Phase 4 → Ready_Bank
```

## Causalité verrouillée — sens unique

```
ROTATION inscrit Depth + Domaine
QuestionIntent → encode l'identité du noyau (DANS Rotation, après Depth+Domaine)
KLD → contrôle pédagogique SUR le noyau identifié
KEY_STRUCTURE → contrôle structurel SUR le noyau identifié
Phase 1 → remplit le noyau identifié

INTERDIT : QuestionIntent choisit/valide/crée du contenu (il encode SEULEMENT)
INTERDIT : KLD/KEY_STRUCTURE travaillent sur un noyau non identifié
```
ANCIEN (ABANDONNÉ) : « KEY_STRUCTURE autorise → QuestionIntent pose la puce ». Causalité inversée : QuestionIntent encode AVANT KLD/KEY_STRUCTURE.

## Responsabilités officielles

| Étape | Responsabilité | Ce qu'elle ne fait PAS |
|---|---|---|
| KernelBlueprint | Ouvrir le frame vide | Aucune décision métier |
| BankTarget | Stop si cible atteinte (noyaux complets, pas question_groups) | Ne choisit pas le depth |
| DepthNeedMatrix | Choisit le Depth ONLY — rotation 2×8/4×8/6×8/7×8/8×8/9×8/10×8 | Ne choisit jamais le domaine |
| DomainCycle | Choisit le Domaine ONLY — Géographie→Histoire→Faune→Art→Sport→Cinéma→Cuisine→Général | Ne choisit pas le depth |
| TaxonomyReader | Propose sub_domain+subject+idee_dominante + navigation fallback sur exclusions | Ne décide pas la production globale |
| KEY_LEARNING_DIRECTION | Détecte doublon pédagogique hash(subject+idee_dominante) → retour TaxonomyReader | Ne crée pas, ne navigue pas |
| KEY_STRUCTURE | Garde structurel hash(depth+domain+sub_domain+subject+idee_dominante) — autorise ou refuse | Ne crée pas, ne remplit pas, ne décide pas |
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
