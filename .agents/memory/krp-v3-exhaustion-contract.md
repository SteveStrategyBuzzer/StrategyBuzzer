---
name: KRP v3.2 — VERROUILLÉ 13 août 2026
description: État final verrouillé de 02_KernelRotationPlanner v3.2 — décisions OFFICIAL/SUPERSEDED/REJECTED, séquence de suite obligatoire
---

# 02_KernelRotationPlanner v3.2 — VERROUILLÉ

**Fichier :** `docs/architecture/02_KernelRotationPlanner.md`
**Date de verrouillage :** 13 août 2026
**Architecture 100 % / Contrat 100 % / Implémentation LOT A+B 100 % / Validation 106 tests GREEN**

## Décisions OFFICIAL (nouvelles, issues de v3.x)

| DEC | Objet |
|---|---|
| DEC-082 | DOMAIN_EXHAUSTED prospectif (+ DomainCycle réénoncé avant supersession DEC-061) |
| DEC-083 | DEPTH_EXHAUSTED prospectif |
| DEC-084 | Indépendance rotation KRP ↔ progression Taxonomy |
| DEC-085 | Deux flux distincts : informationnel et déclencheur |
| DEC-087 | Canal d'épuisement : contrat sémantique résolu, transport = détail d'implantation |
| DEC-088 | CYCLE_TARGET/cycle_completed remplacés par DEPTH_EXHAUSTED comme autorité de Depth |
| DEC-092 | Transition terminale DEPTH_EXHAUSTED(10) → PRODUCTION_ON_HOLD |
| DEC-093 | CURRENT_KERNEL_RECEIVED seul incrémenteur de kernel_received_total |
| DEC-094 | DepthCycle intellectuel officiel (remplace DEC-065) |

## Décisions REJECTED

DEC-078 (kernel_remaining autorité), DEC-080 (AVAILABLE|EMPTY), DEC-081 (SHORTFALL),
DEC-086 (signal AVAILABLE), DEC-089 (SHORTFALL états dérivés), DEC-090 (DepthProductionState),
DEC-091 (double condition kernel_remaining+AVAILABLE)

## Décisions SUPERSEDED dans ce spec

DEC-061 (superseded par DEC-082+083), DEC-065 (superseded par DEC-094),
DEC-066 (superseded par DEC-034+082), DEC-079 (superseded par DEC-093)

## Points constitutionnels verrouillés

- **DEPTH_EXHAUSTED_PENDING** : N'EST PAS un état métier officiel. Signal mémorisé prospectivement, mécanisme = détail d'implantation. États métier = ROTATION_ACTIVE | PRODUCTION_ON_HOLD uniquement.
- **Gate PRODUCTION_ON_HOLD** : appartient à l'ORCHESTRATION, pas à KernelBlueprintFactory. La Factory ne connaît ni KRP, ni Taxonomy, ni PRODUCTION_ON_HOLD.
- **Blueprint créé avant fillRotation** : OUI (DEC-058 OFFICIAL inchangé) — en cycle normal uniquement. Si PRODUCTION_ON_HOLD : Factory non appelée.
- **Statuts autorisés** : DRAFT / UNDER_REVIEW / OFFICIAL / SUPERSEDED / REJECTED — aucun statut partiel.
- **DEC-094 scope** : DepthCycle uniquement. Domaines = voir DEC-082. Transition Depth 10 = voir DEC-092.

## Séquence de suite obligatoire

1. ✅ 02_KernelRotationPlanner v3.2 VERROUILLÉ
2. ✅ 00_ArchitectureRegister synchronisé (DEC-061, 065, 066, 079 → SUPERSEDED ; DEC-082→094 ajoutés OFFICIAL/REJECTED)
3. ✅ Audit du code KRP existant (écarts code ↔ spec v3.2 documentés — task #154)
4. ✅ Implantation LOT A+B (4 migrations Neon, KernelRotationPlanner V3, KernelPipelineOrchestrator V3, 106 tests GREEN)
5. → Validation terminale (tests E2E, smoke test en prod)
6. → LOT C — Wiring Taxonomy → KRP (receiveDomainExhausted / receiveDepthExhausted) — INTERDIT jusqu'à spec 03_Taxonomy

## État de l'implémentation LOT A+B (2026-08-13)

### Migrations appliquées sur Neon

- `2026_08_13_000001` — ADD `depth_state VARCHAR(64) DEFAULT 'ROTATION_ACTIVE'`
- `2026_08_13_000002` — ADD `domain_states JSONB NULLABLE`
- `2026_08_13_000003` — ADD `pending_depth_exhausted_depth INT NULLABLE`
- `2026_08_13_000004` — ADD `domain_position SMALLINT NULLABLE`
- M-cleanup (DROP `rotation_status`, `active_tour_id`) — DIFFÉRÉ

### Fichiers clés

- `app/Services/QuestionBank/Rotation/KernelRotationPlanner.php` — V3 complète
- `app/Services/QuestionBank/Rotation/KernelPipelineOrchestrator.php` — V3 (gate + EMPTY legacy)
- `app/Services/QuestionBank/Rotation/RotationResolution.php` — DTO value object
- `app/Services/QuestionBank/Rotation/KernelBlueprintRunRepository.php` — markOnHold() supprimé
- `tests/Unit/QuestionBank/Rotation/KernelRotationPlannerV3Test.php` — 25 tests
- `tests/Unit/QuestionBank/Rotation/KernelPipelineOrchestratorTest.php` — 16 tests
- `tests/Unit/QuestionBank/Rotation/KernelRotationPlannerV2Test.php` — SUPERSEDED stub
- `tests/Unit/QuestionBank/Rotation/KernelRotationPlannerTest.php` — SUPERSEDED stub (V1)

### Gap critique à ne pas oublier (LOT C)

`ApplyCurrentKernelReceivedToRotation::applyCount()` gère receipt + counter directement
(PAS via `KernelRotationPlanner::receiveKernelReceivedV2`). Conséquence : la vérification
`pending_depth_exhausted_depth` n'est PAS déclenchée automatiquement par le flux Outbox.
Le branchement complet requiert LOT C (spec 03_Taxonomy préalable obligatoire).
