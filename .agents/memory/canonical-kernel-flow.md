---
name: Flow canonique du pipeline noyau (2026-08-11)
description: Source de vérité du flow kernel — KLD & KEY_STRUCTURE SUPERSEDED (absorbés par ValidationDominantIdeas) ; confirmConsumed après acceptation ReadyBank, jamais après QuestionIntent.
---

# Flow canonique du pipeline noyau — VERROUILLÉ par l'utilisateur le 2026-08-11

```
CRÉATION DU KERNELBLUEPRINT   ← le Blueprint EXISTE avant KRP
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
→ CRÉATION DU BLUEPRINT SUIVANT
→ KernelRotationPlanner
```

## Amorce : le Blueprint existe AVANT KRP (précision utilisateur 2026-08-11)

**Règle :** KernelRotationPlanner ne crée JAMAIS le KernelBlueprint — il le reçoit déjà créé (KernelBlueprintFactory précède KRP). Après CURRENT_KERNEL_RECEIVED, c'est la CRÉATION DU BLUEPRINT SUIVANT qui relance le cycle, puis KRP. Ne jamais réinterpréter l'abréviation « Blueprint → KRP » comme « KRP crée le Blueprint ».

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

## État : IMPLÉMENTÉ (2026-08-11) — invariants à ne pas casser

- **A implanté ATOMIQUE** : engagement du Blueprint + encodage QuestionIntent dans UNE transaction (orchestrateur). Invariant : jamais de run ENGAGED sans intent — toute modification doit préserver cette atomicité.
- **B implanté GATED** : confirmConsumed() vit dans le listener CURRENT_KERNEL_RECEIVED, DANS la branche `!alreadyReceived`, avant l'insertion du reçu. Le reçu est LA gate d'idempotence (double event = un seul avancement de curseur).
- **Invariant de récupération** : un run ENGAGED sans intent se répare par re-lecture `peekNext` — sûr UNIQUEMENT parce que le curseur Taxonomy n'avance qu'à confirmConsumed (fin de cycle B). Quiconque déplace la consommation plus tôt casse cette récupération.
- **Tapis roulant** : `questions:kernel:advance --loop` (workflow « Kernel Pipeline ») dispatche par frame_status et délègue aux commandes de stage existantes. DEC-052 : quarantine/human_review/rejected/partial_review sont quand même REÇUS — la rotation ne bloque jamais. Garde-fou : 5 échecs de stage consécutifs → quarantine explicite (loggée) → reçu au tick suivant.
- **Amorçage MANUEL par conception** (KRP-R11) : la boucle ne démarre rien d'elle-même ; `questions:kernel:rotate` lance le premier Blueprint, ensuite le cycle s'auto-entretient via CKR. DB vide → ticks no-op gratuits (zéro Gemini).
- Mapping d'encodage legacy : voir questionintent-contract.md (section « Encodage réel »).
