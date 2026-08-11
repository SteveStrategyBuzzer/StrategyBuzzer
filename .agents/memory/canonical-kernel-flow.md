---
name: Flow canonique du pipeline noyau (2026-08-11)
description: Source de vérité du flow kernel — KLD & KEY_STRUCTURE SUPERSEDED (absorbés par ValidationDominantIdeas) ; confirmConsumed après acceptation ReadyBank, jamais après QuestionIntent.
---

# Flow canonique du pipeline noyau — VERROUILLÉ par l'utilisateur le 2026-08-11

```
KernelBlueprint
→ KernelRotationPlanner (KRP)
→ Taxonomy ↕ ValidationDominantIdeas
→ QuestionIntent
→ Phase 1
→ Validation Phase 1
→ Phase 2
→ Validation Phase 2
→ ReadyBank (accepte le noyau canonique)
→ Taxonomy.confirmConsumed(...) idempotent
→ CURRENT_KERNEL_RECEIVED
→ KRP / Blueprint suivant
```

## ⛔ KLD et KEY_STRUCTURE : SUPERSEDED

**Règle :** Ne JAMAIS rebrancher KLD, KEY_STRUCTURE, ni créer IdeaSlotLoader→KLD→KS. Depuis la refonte Taxonomy (2026-08), leurs responsabilités pertinentes sont intégrées dans **ValidationDominantIdeas** (11 règles PHP), qui intervient pendant la création/validation des Idées Dominantes dans Taxonomy. ValidationDominantIdeas = **unique autorité** de validation des Idées Dominantes.

**Why :** J'ai proposé (et l'utilisateur a REFUSÉ le 2026-08-11) une tâche qui rebranchait Taxonomy→KLD→KEY_STRUCTURE→QuestionIntent — cela réintroduisait l'architecture que la refonte venait de supprimer. Principe projet : UNE RESPONSABILITÉ = UN PROPRIÉTAIRE = UNE IMPLÉMENTATION ACTIVE.

**Piège :** des adapters/gates KLD-KS inertes existent encore dans le code (interfaces + gate bloqué dans Rotation/). Leur présence N'EST PAS une invitation à les brancher — ils sont morts. Un audit peut décider de les supprimer, jamais de les activer.

**Conséquence :** KernelIdentifierManager (future autorité kld_hash/ks_hash) est ABANDONNÉ avec eux — ne plus le proposer.

## Deux raccordements DISTINCTS (ne jamais fusionner)

- **A — pipeline intellectuel :** Taxonomy fournit {subdomain_active, subject_active, dominant_idea_active} → QuestionIntent → Phase 1.
- **B — confirmation de consommation :** Validation Phase 2 → ReadyBank accepte → confirmConsumed() → CURRENT_KERNEL_RECEIVED → Blueprint suivant.

## Sémantique confirmConsumed()

La consommation Taxonomy = l'utilisation RÉUSSIE de l'unité pour produire un noyau **ACCEPTÉ par ReadyBank** — pas son simple passage dans QuestionIntent. Appel idempotent, uniquement dans le cycle terminal (B).

**How to apply :** toute tâche/PR touchant le pipeline noyau doit se conformer à ce flow ; vérifier les composants réels existants (l'event CURRENT_KERNEL_RECEIVED, le receiver ReadyBank et l'outbox existent déjà côté KRP V2) et réutiliser le chemin canonique au lieu d'en créer un parallèle.
