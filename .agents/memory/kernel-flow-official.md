---
name: Kernel Flow Official
description: Flow officiel "Blueprint First" — 14 étapes + causalité KEY_STRUCTURE→QuestionIntent verrouillée. Source de vérité pour toute implémentation kernel/rotation.
---

# Flow officiel Kernel — Blueprint First

Document de référence validé. Remplace toutes les interprétations précédentes.

## Séquence principale

```
KernelBlueprint → BankTarget → DepthNeedMatrix → DomainCycle
→ TaxonomyReader → KEY_LEARNING_DIRECTION → KEY_STRUCTURE
→ QuestionIntent (puce) → Phase 1 Création → Phase 2 Validation
→ Phase 3 Traduction → Phase 4 Validation Traduction
→ Ready_Bank → Gameplay
```

## Branche parallèle (quarantaine)

```
WARNING → Phase 6 Quarantaine → Phase 7 Correction
→ Phase 2 → Phase 3 → Phase 4 → Ready_Bank
```

## Causalité verrouillée — sens unique

```
KEY_STRUCTURE → autorise le verrouillage
QuestionIntent → pose la puce persistante (conséquence de KEY_STRUCTURE)
Phase 1 → remplit le noyau pucé

INTERDIT : QuestionIntent dépend de lui-même pour être créé
INTERDIT : KEY_STRUCTURE dépend de QuestionIntent
```

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
| QuestionIntent | Pose la puce persistante APRÈS autorisation KEY_STRUCTURE | Ne valide rien, ne choisit rien, ne décide rien |
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

Créé APRÈS KEY_STRUCTURE { ok: true }
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
