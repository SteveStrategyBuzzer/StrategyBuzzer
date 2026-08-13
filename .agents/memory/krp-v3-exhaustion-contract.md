---
name: KRP v3.2 — VERROUILLÉ 13 août 2026
description: État final verrouillé de 02_KernelRotationPlanner v3.2 — décisions OFFICIAL/SUPERSEDED/REJECTED, séquence de suite obligatoire
---

# 02_KernelRotationPlanner v3.2 — VERROUILLÉ

**Fichier :** `docs/architecture/02_KernelRotationPlanner.md`
**Date de verrouillage :** 13 août 2026
**Architecture 100 % / Contrat 100 % / Implémentation 0 % / Validation 0 %**

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
2. → 00_ArchitectureRegister synchronisé (DEC-061, 065, 066, 079 → SUPERSEDED ; DEC-082→094 ajoutés OFFICIAL/REJECTED)
3. → Audit du code KRP existant (écarts code ↔ spec v3.2 documentés)
4. → Implantation
5. → Validation terminale
6. → 03_Taxonomy (SEULEMENT après)
