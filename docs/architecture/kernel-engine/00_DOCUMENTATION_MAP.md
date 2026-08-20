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
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | v2.1 VERROUILLÉ — contrat intellectuel actif |
| 02 KernelRotationPlanner | `specifications/02_KernelRotationPlanner.md` | v3.3 VERROUILLÉ |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | v1.0 VERROUILLÉ |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides actives ; règles utilisées par Gemini pendant Taxonomy |
| 05 QuestionIntent | certificat/source récupérée | verrouillé selon certificat |
| 06..11 | `working/` | à spécifier dans leur tour |

## Documents KRP explicitement non actifs

```text
docs/architecture/02_KernelRotationPlanner.md
→ v3.2 historique

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ CLOSED / PROMOTED pointer
```

Aucun de ces trois documents ne peut remplacer `specifications/02_KernelRotationPlanner.md v3.3`.

## Correction 01

La v2.1 n’impose plus les structures Gameplay/Traduction avant les modules propriétaires. Elles seront ajoutées lorsque 06/08 seront spécifiés. Cela ne bloque pas le contrat d’entrée KRP.

## Bloc actif

```text
02_KernelRotationPlanner
AUDIT-02-00 = NEXT
```

But : auditer le code KRP réel contre la v3.3 verrouillée, puis corriger uniquement KRP et ses portes Factory/Taxonomy.
