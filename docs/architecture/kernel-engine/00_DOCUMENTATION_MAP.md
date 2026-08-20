# DOCUMENTATION MAP — StrategyBuzzer Kernel Engine

**Date : 2026-08-20**

## Autorité

| Type | Dossier | Autorité |
|---|---|---|
| Constitution | `00_ConstitutionCognitive.md` | OUI |
| Architecture Register | `00_ArchitectureRegister.md` | OUI |
| Master actif | `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md` | OUI globale |
| Spécification verrouillée | `specifications/` | OUI module concerné |
| Working | `working/` | NON, sauf bride exacte non encore promue |
| Audit | `audits/` | preuve seulement |
| Certificat | `certificates/` | preuve de statut |
| Archive / superseded | `archive/`, historique Git, fichiers marqués SUPERSEDED | NON |

## État canonique

| Module | Source | Statut |
|---|---|---|
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | v2.0 VERROUILLÉ — contrat canonique; frontière intellectuelle disponible pour KRP |
| 02 KernelRotationPlanner | `specifications/02_KernelRotationPlanner.md` | **v3.3 VERROUILLÉ — PARTIE INTELLECTUELLE** |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | v1.0 VERROUILLÉ |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides actives; règles utilisées par Gemini pendant Taxonomy |
| 05 QuestionIntent | certificat/source récupérée | verrouillé selon certificat |
| 06..11 | `working/` | à spécifier dans leur tour |

## KRP — source unique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 3.3
VERROUILLÉ — PARTIE INTELLECTUELLE
DEC-114
```

Le certificat est :

```text
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

## Documents KRP explicitement non actifs

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

Aucun de ces documents ne peut remplacer `specifications/02_KernelRotationPlanner.md v3.3`.

## Extension KRP future

KRP est complet et verrouillé pour la partie intellectuelle.

Les éventuelles interfaces nécessaires aux futures Phases 1–2 restent :

```text
RÉSERVÉES
NON SPÉCIFIÉES
```

Elles ne peuvent être ajoutées que depuis la spécification propriétaire de la Phase concernée, avec nouvelle version KRP + nouvelle DEC si KRP est affecté.

## Bloc actif

```text
02_KernelRotationPlanner
AUDIT-02-00 = NEXT
```

But : auditer le code KRP réel contre la v3.3 verrouillée, puis corriger uniquement KRP et ses portes Factory/Taxonomy.
