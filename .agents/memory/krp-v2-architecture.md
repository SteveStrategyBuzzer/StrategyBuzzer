---
name: KRP V2 architecture
description: KernelRotationPlanner V2 — contrat, classes, tables, tests. Implantation 2026-07-28.
---

## Règle centrale

KernelBlueprintFactory crée le Blueprint AVANT KRP. KRP reçoit un Blueprint vide et écrit uniquement depth + domain.

## Interface V2 (méthodes actives)

- `planV2(KernelBlueprint $blueprint, ?string $previousDomain): string` → ROTATION_ASSIGNED | NOT_ENGAGED_PRODUCTION_ON_HOLD
- `applyEmptyTransitionV2(string $emptyDomain): void` → Domaine ON→OFF ; si 8/8 → cycle_completed++
- `receiveKernelReceivedV2(string $blueprintId, int $depth, string $domain): void` → comptabilisation idempotente

## DepthCycle officiel (DEC-065)

`[2, 4, 6, 7, 8, 9, 10]` — Après 10 : reprend à 2.

## Cycle_target par Depth

`2=250, 4=300, 6=350, 7=350, 8=350, 9=250, 10=100`

## blueprint_id

UUIDv7 via `Str::orderedUuid()`. Généré par KernelBlueprintFactory. Immuable. ≠ kernel_code ≠ rotation_identifier (supprimé).

## Tables V2 créées (M-01..M-08, migrations 2026_07_28_*)

- `kernel_blueprint_runs` — cycle de vie Blueprint (CREATED_UNENGAGED → ENGAGED_IN_PIPELINE → READY_BANK_RECEIVED | NOT_ENGAGED_PRODUCTION_ON_HOLD)
- `kernel_rotation_state_v2` — une seule ligne (tour_domain_states JSON, lock_version)
- `kernel_depth_matrix` — 7 lignes seedées (un par Depth du cycle)
- `kernel_depth_domain_totals` — 56 lignes seedées (7×8 = Depth × Domaine)
- `kernel_current_kernel_receipts` — idempotence CURRENT_KERNEL_RECEIVED (PK blueprint_id)
- `kernel_pipeline_outbox` — événements transactionnels ReadyBank→KRP

## Classes nouvelles

- `DepthTourState` — valeur immuable ON/OFF 8 domaines ; `applyEmpty()` retourne nouvelle instance
- `DepthNeedMatrix` — service DB lecture/écriture cycle_completed + kernel_received_total
- `KernelBlueprintFactory` — crée Blueprint + kernel_blueprint_runs ; garde 1 seul Blueprint actif
- `KernelPipelineOrchestrator` — boucle Factory→KRP→Taxonomy (MAX_EMPTY_LOOP=16)
- `Events/CurrentKernelReceived` — payload event_id/blueprint_id/depth/domain/occurred_at
- `Listeners/ApplyCurrentKernelReceivedToRotation` — listener idempotent + marque Outbox

## Legacy DEPRECATED (conservé pour retour arrière)

`plan()`, `initialize()`, `buildDepthNeedMatrix()`, `chooseDepth()`, `advanceDomainIndex()`, `ALLOWED_DEPTHS=[4,6,7,8,9]`, `DEPTH_TARGETS` — tous dans KernelRotationPlanner.php, marqués DEPRECATED, ne pas appeler en V2.

**Why:** retrait physique planifié dans patch séparé post-validation terminale pour éviter de casser BankWorker.

## Tests V2

72 tests, 133 assertions GREEN (DepthTourStateTest 23, DepthNeedMatrixTest 23, KernelBlueprintFactoryTest 9, KernelRotationPlannerV2Test 17, ApplyCurrentKernelReceivedToRotationTest 5).
Legacy tests toujours GREEN : 105 tests, 239 assertions.
2 failures pré-existantes dans BankAIGeneratorRouterTest (non liées à V2).

## BankWorker

Strictement intact. Aucune migration ne touche ses tables.
