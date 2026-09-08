# DOCUMENTATION MAP — StrategyBuzzer Kernel Engine

**Date : 2026-09-08**

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
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | **v3.1 VERROUILLÉ — DEC-123 + DEC-124 OFFICIAL; blueprint_id seul transmis; QuestionIntent possède seul le kernel_code complet** |
| 02 KernelRotationPlanner | `specifications/02_KernelRotationPlanner.md` | **v4.0 VERROUILLÉ — DEC-119 OFFICIAL** |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | **v1.1 VERROUILLÉ — DEC-120 OFFICIAL** |
| 03 frontière historique | `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-118.md` | **NON ACTIVE — DEC-118 REJECTED, remplacée par DEC-119/120** |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides actives; règles utilisées par Gemini pendant Taxonomy |
| 05 QuestionIntent | `specifications/05_QuestionIntent.md` | **v2.2 VERROUILLÉ — DEC-123 + DEC-124 + DEC-122; construit/persiste/verrouille seul le kernel_code complet** |
| 06 Phase1 | `specifications/06_Phase1.md` | **v1.0 CONTRAT VERROUILLÉ** |
| 07 ValidationPhase1 | `specifications/07_ValidationPhase1.md` | **v1.0 FRONTIÈRE VERROUILLÉE** |
| 08 Phase2 | `specifications/08_Phase2.md` | **v0.1 RÈGLES OFFICIELLES, module à compléter** |
| 09 ValidationPhase2 | `specifications/09_ValidationPhase2.md` | **v0.1 FRONTIÈRE OFFICIELLE, module à compléter** |
| 10 Quarantine | `specifications/10_Quarantine.md` | **v0.1 RÈGLES OFFICIELLES, module à compléter** |
| 11 ReadyBank | `specifications/11_ReadyBank.md` | **v0.2 RÈGLES OFFICIELLES, module à compléter** |

## KRP — source unique

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md
Version 4.0
VERROUILLÉ
DEC-119
```

## Invariant actif

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

KRP et Taxonomy ne sont jamais actifs simultanément.

## Frontière active Taxonomy → KRP

Dans la fermeture de sortie Taxonomy :

```text
triplet écrit avec succès
↓
même IdeaSlot consommé
↓
si le besoin reste identique : silence

si dernière Dominant Idea exploitable du dernier Subject utilisée :
fait terminal de consommation de l'occurrence
```

Le fait signifie uniquement :

```text
LA DERNIÈRE DOMINANT IDEA EXPLOITABLE
DE CETTE OCCURRENCE VIENT D'ÊTRE UTILISÉE
```

```text
pas de signal à chaque noyau
pas de signal à chaque passage
pas de AVAILABLE
maximum 1 fait terminal normal par occurrence
```

Taxonomy ne produit jamais `DOMAIN_EXHAUSTED`. KRP seul reçoit ce fait terminal
et l'interprète dans son moteur interne `DOMAIN_EXHAUSTED`.

Puis :

```text
Taxonomy FIN
↓
fait conservé en attente
↓
KRP INACTIF
↓
ReadyBank → CURRENT_KERNEL_RECEIVED → Factory
↓
nouveau Blueprint
↓
KRP ACTIVE
↓
consomme le fait
↓
VISIBLE → ESTOMPÉ
↓
Domain abstrait/exclu des rotations restantes du tour
```

KRP choisit ensuite seul Domain, fin de tour, prochain Depth et HOLD.

Taxonomy n’émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

## Documents KRP non actifs

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED

KRP v3.3 / DEC-114
→ SUPERSEDED

KRP v3.4 / DEC-115
→ SUPERSEDED

KRP v3.5 / DEC-116
→ SUPERSEDED

KRP v3.6 / DEC-117
→ SUPERSEDED

working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-115.md
→ SUPERSEDED

working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-116.md
→ SUPERSEDED

working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-117.md
→ SUPERSEDED

AUDIT-02-00 v3.3
→ preuve historique; ne suffit plus comme cible d’implantation
```

## Extension KRP future

Les éventuelles interfaces Phase1/Phase2 restent :

```text
RÉSERVÉES
NON SPÉCIFIÉES
```

## Bloc actif

```text
06_Phase1
ALIGN-AUDIT-06-v1.0 = NEXT
```

But : auditer l’implantation Phase1 contre son contrat v1.0, sans rouvrir KRP
v4.0, Taxonomy v1.1 ou les responsabilités intellectuelles déjà verrouillées.
