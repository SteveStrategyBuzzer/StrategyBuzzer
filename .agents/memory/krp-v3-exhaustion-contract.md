---
name: KRP v3.1 — état des décisions après REVIEW CORRECTIF
description: Statut résolu de D1/D2/D3, PRODUCTION_ON_HOLD terminal, nouveaux DECs, spec v3.1 UNDER_REVIEW
---

# KRP v3.0 → v3.1 : décisions closes

**Spec :** `docs/architecture/02_KernelRotationPlanner.md` v3.1, UNDER_REVIEW, 13 août 2026.

## D1 — Canal sémantique RÉSOLU (transport = détail d'implantation)
Contrat : Taxonomy produit, KRP possède, Orchestration transporte, disponible immédiatement après consommation exacte, influence uniquement au prochain CURRENT_KERNEL_RECEIVED. Le choix physique (retour enrichi / Outbox / table / événement) sera arrêté à l'audit d'implantation. DEC-087 mis à jour, UNDER_REVIEW.

**Why :** Le contrat sémantique est complet et ne requiert pas le choix physique pour verrouiller la spec KRP.

## D2 — DEPTH_EXHAUSTED(10) RÉSOLU : PRODUCTION_ON_HOLD
`depth_state = PRODUCTION_ON_HOLD`. Aucun retour automatique Depth 2. Aucun IDLE distinct. Aucun Blueprint créé après entrée. Sortie = hors contrat actuel. DEC-092 créé UNDER_REVIEW.

**Why :** Reboucler vers Depth 2 serait revenir sur des banques déjà épuisées. L'état IDLE est moins précis que PRODUCTION_ON_HOLD qui exprime la sémantique correcte.

**How to apply :** Gate obligatoire : si `depth_state = PRODUCTION_ON_HOLD`, KernelBlueprintFactory ne crée aucune enveloppe canonique. Mécanisme de gate = détail d'implantation. Idempotent.

## D3 — RETIRÉ DU PÉRIMÈTRE KRP
KRP termine après fillRotation(depth, domain). Timeout/retry/Gemini = 03_Taxonomy + orchestration.

## Nouveaux DECs créés (UNDER_REVIEW sauf REJECTED)
- DEC-089 : SHORTFALL et états dérivés → REJECTED
- DEC-090 : DepthProductionState → REJECTED
- DEC-091 : Double condition kernel_remaining > 0 AND AVAILABLE → REJECTED
- DEC-092 : PRODUCTION_ON_HOLD terminal → UNDER_REVIEW
- DEC-088 : renommé (titre sans ambiguïté "SUPERSEDED") → UNDER_REVIEW
- DEC-087 : mis à jour (D1 résolu) → UNDER_REVIEW

## DEC-065 registre : deux clauses partiellement supersedées
- « Après Depth 10 : reprend à Depth 2 » → SUPERSEDED par DEC-092
- « PRODUCTION_ON_HOLD = aucun Depth sous cycle_target » → SUPERSEDED par DEC-088 + DEC-092
- DepthCycle lui-même (2→4→6→7→8→9→10) reste OFFICIAL

## Corrections de cohérence (v3.1)
- hedges supprimés : kernel_target, kernel_remaining, CYCLE_TARGET, cycle_completed
- DomainExhaustionChecker::isExhausted() → À RETIRER après PUSH, aucune architecture parallèle
- DEC-082 : DomainCycle réénoncé avant supersession DEC-061
- depth_state dans §20.2 : NOT_ENGAGED_PRODUCTION_ON_HOLD → PRODUCTION_ON_HOLD
- §19.1 : KRP n'attend pas Taxonomy (clarification)
- KRP-R25 ajouté, tests PRODUCTION_ON_HOLD ajoutés

## État UNDER_REVIEW
Toutes les nouvelles décisions restent UNDER_REVIEW jusqu'au verrouillage explicite du spec. DEC-087 ne devient OFFICIAL qu'au verrouillage.
