# DOCUMENTATION MAP — StrategyBuzzer Kernel Engine

**Date : 2026-08-23**

## Autorité

| Type | Dossier | Autorité |
|---|---|---|
| Constitution | `00_ConstitutionCognitive.md` | OUI |
| Architecture Register | `00_ArchitectureRegister.md` | OUI |
| Master actif | `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` | OUI globale |
| Spécification verrouillée | `specifications/` | OUI module concerné |
| Boundary bridge explicitement déclaré | `working/` | OUI uniquement pour la frontière indiquée |
| Audit | `audits/` | preuve seulement |
| Certificat | `certificates/` | preuve de statut |
| Archive / superseded | historique Git, fichiers marqués SUPERSEDED | NON |

## État canonique

| Module | Source | Statut |
|---|---|---|
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | contrat intellectuel disponible pour KRP |
| 02 KernelRotationPlanner | `specifications/02_KernelRotationPlanner.md` | **v3.4 VERROUILLÉ — PARTIE INTELLECTUELLE — DEC-115** |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | v1.0 historique sur la frontière KRP; détails internes à reconstruire en v1.1 |
| 03 frontière temporaire | `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-115.md` | **ACTIVE uniquement pour ownership KRP/Taxonomy** |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides actives; règles utilisées par Gemini pendant Taxonomy |
| 05 QuestionIntent | certificat/source récupérée | verrouillé selon certificat |
| 06..11 | `working/` | à spécifier dans leur tour |

## KRP — source unique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 3.4
VERROUILLÉ — PARTIE INTELLECTUELLE
DEC-115
```

## Ownership actif

```text
Taxonomy
= réalité de ses réservoirs
= aucune décision de rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclencheur lifecycle

Factory
= nouveau Blueprint

KRP
= autorité UNIQUE de rotation
```

Les anciennes interfaces :

```text
Taxonomy → DOMAIN_EXHAUSTED
Taxonomy → DEPTH_EXHAUSTED
```

sont SUPERSEDED comme frontière active.

## Documents KRP non actifs

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED

AUDIT-02-00 v3.3
→ preuve historique; ne suffit plus comme cible d’implantation après DEC-115
```

## Extension KRP future

Les éventuelles interfaces Phase1/Phase2 restent :

```text
RÉSERVÉES
NON SPÉCIFIÉES
```

## Bloc actif

```text
02_KernelRotationPlanner
RÉAUDIT-02-v3.4 = NEXT
```

But : auditer le diff local déjà commencé par Replit contre la v3.4, puis reprendre uniquement les corrections compatibles.