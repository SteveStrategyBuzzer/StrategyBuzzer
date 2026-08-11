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

## État après audit RÈGLE DU VIDE (2026-08-11) : plomberie conservée, inventions RETIRÉES

Une première implantation des raccordements A/B avait inventé des règles métier (mapping d'encodage, retries=5→quarantine, « tout statut terminal ⇒ réception », « réception ⇒ confirmConsumed », frame_status legacy = définition des Phases). Le protocole anti-ambiguïté du user les a fait RETIRER sans les remplacer :

- **Conservé (plomberie conforme)** : Blueprint créé AVANT KRP → planV2 → peekNext → fillTaxonomy → engagement (ROTATION_ASSIGNED) ; receiver + outbox + CKR + comptabilisation idempotente par reçu (DEC-052/DEC-063) ; fix `occurred_at` de l'outbox (bug réel, colonne NOT NULL jamais écrite) ; migration additive question_intents (blueprint_id UNIQUE, dominant_idea, advance_attempts — colonnes INERTES, aucun writer).
- **RETIRÉ** : encodeur QuestionIntent, avancer de Phases, commande/schedule/workflow du tapis roulant, appel confirmConsumed dans le listener CKR.
- **3 BLOCKERS OUVERTS** — aucun code ne doit exister au-delà de ces frontières tant que le user n'a pas tranché : (1) contrat QuestionIntent — EN COURS de spécification via `docs/architecture/05_QuestionIntent.md` (gel `BLOCKED_AT_QUESTION_INTENT_CONTRACT`, 7 mappings UNSPECIFIED/NON AUTORISÉS) ; (2) définition officielle des Phases 1-2/Validations + politique retry/quarantaine ; (3) « réception canonique » ReadyBank et condition de confirmConsumed — le user a précisé : « reçu » ≠ « accepté », ne pas les fusionner sans contrat officiel ; spécification SÉPARÉE à traiter APRÈS 05_QuestionIntent (une seule spec à la fois). ⚠️ DEC-052 couvre la COMPTABILISATION rotation (compter même avec slots FAIL), PAS la consommation Taxonomy — ne jamais re-déduire « reçu ⇒ consommé ».
