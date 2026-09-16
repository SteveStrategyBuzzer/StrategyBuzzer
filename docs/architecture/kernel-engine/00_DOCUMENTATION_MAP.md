# DOCUMENTATION MAP — StrategyBuzzer Kernel Engine

**Date : 2026-09-16**

**Autorité courante :** DEC-119, DEC-120, DEC-125, DEC-126 et DEC-127 —
OFFICIAL selon leur module. Les versions précédentes restent historiques; les
clauses annotées `SUPERSEDED` sont conservées comme historique non actif.

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
| 01 KernelBlueprint | `specifications/01_KernelBlueprint.md` | **v3.2 VERROUILLÉ — DEC-125; Blueprint persistant unique, copie complète et retour Phase1** |
| 02 KernelRotationPlanner | `specifications/02_KernelRotationPlanner.md` | **v4.0 VERROUILLÉ — DEC-119 OFFICIAL** |
| 03 Taxonomy | `specifications/03_Taxonomy.md` | **v1.1 VERROUILLÉ — DEC-120 OFFICIAL** |
| 03 frontière historique | `working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-118.md` | **NON ACTIVE — DEC-118 REJECTED, remplacée par DEC-119/120** |
| 04 ValidationDominantIdeas | `working/04_ValidationDominantIdeas/` | brides actives; règles utilisées par Gemini pendant Taxonomy |
| 05 QuestionIntent | `specifications/05_QuestionIntent.md` | **v2.3 VERROUILLÉ — DEC-125; aucun coordinateur de circulation** |
| 06 Phase1 | `specifications/06_Phase1.md` | **v1.2 CONTRAT DOCUMENTAIRE — DEC-125/126** |
| 07 ValidationPhase1 | `specifications/07_ValidationPhase1.md` | **v1.2 CONTRAT DOCUMENTAIRE — DEC-125/126** |
| 08 Phase2 | `specifications/08_Phase2.md` | **v1.0 CONTRAT TERMINAL — DEC-127** |
| 09 ValidationPhase2 | `specifications/09_ValidationPhase2.md` | **v1.0 CONTRAT TERMINAL — DEC-127** |
| 10 Quarantine | `specifications/10_Quarantine.md` | **v1.1 FRONTIÈRES VERROUILLÉES — DEC-125/127** |
| 11 ReadyBank | `specifications/11_ReadyBank.md` | **v0.4 FRONTIÈRE PHASE2 VERROUILLÉE — DEC-127; module global à compléter** |

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

> **Chemin historique — SUPERSEDED BY DEC-125, portée limitée :** la ligne
> directe vers Factory ci-dessous ne s’applique que si la file Quarantine prête
> est vide. Sinon ReadyBank envoie exclusivement `GO` à la première copie FIFO.

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

## Cycle Quarantine actif — DEC-125

Une suspicion ou un slot vide vide la position canonique et crée une copie
persistante complète non canonique. Les sept slots sont visibles et modifiables
par l’Admin : rouge `SUSPICION`/`EMPTY`, vert conforme non encore modifié,
jaune modifié manuellement jusqu’à ReadyBank. Toute copie complète repart en
Phase1, puis chaque source ou traduction reprend uniquement à l’étape indiquée
par son indice persistant. ReadyBank publie atomiquement un CognitiveSlot avec
sa source anglaise PASS et ses neuf traductions admissibles, ou ne publie
aucune position du slot. Un renvoi met en file FIFO sans démarrer; chaque
arrivée choisit exclusivement la première copie Quarantine prête, sinon KBP.

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

## Bloc documentaire courant

```text
DEC-127
08_Phase2 v1.0
09_ValidationPhase2 v1.0
VALIDATION DOCUMENTAIRE = PASS
IMPLÉMENTATION DEC-127 = NON, tâche technique distincte
```

Le prochain travail technique doit appliquer DEC-127 sans rouvrir KRP v4.0,
Taxonomy v1.1, l’identité Blueprint, la langue canonique anglaise ou le contrat
des neuf traductions.

## Annexe officielle de conformité DEC-127

| Type | Source | Autorité |
|---|---|---|
| Projection de conformité vérifiable | [`DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md`](DEC-127_PHASE2_VALIDATION_COMPLIANCE_ANNEX.md) | NON autonome — DEC-127 et les spécifications propriétaires priment |

En cas d’écart, l’annexe est corrigée. Elle ne remplace aucune spécification et
n’autorise aucune implantation.
