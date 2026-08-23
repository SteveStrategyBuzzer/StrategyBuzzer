# START HERE — StrategyBuzzer Kernel Engine

Ce dossier est la **mémoire architecturale persistante** du moteur intellectuel. Un changement de chat ne doit jamais modifier la source de vérité.

## Ordre obligatoire de lecture

1. `00_ConstitutionCognitive.md`
2. `00_ArchitectureRegister.md`
3. `00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md`
4. `00_CURRENT_HANDOFF.md`
5. les spécifications verrouillées nécessaires dans `specifications/`
6. le boundary bridge explicitement actif du module suivant, seulement si requis par la frontière courante

## Hiérarchie

- `specifications/` = contrats canoniques verrouillés seulement ;
- `working/` = reconstruction, références et boundary bridges explicitement déclarés ;
- `cross-module/` = brides transversales qui ne constituent pas un moteur 01–11 ;
- `audits/` = preuves d’audit, ne remplacent jamais les contrats ;
- `certificates/` = preuves de verrouillage/validation, ne remplacent jamais les contrats ;
- `archive/` + documents marqués `SUPERSEDED/HISTORIQUE` = non autoritatifs.

## État de reprise actuel

```text
01 KernelBlueprint
→ contrat canonique disponible pour la frontière intellectuelle

02 KernelRotationPlanner
→ specifications/02_KernelRotationPlanner.md
→ v3.7 VERROUILLÉ — PARTIE INTELLECTUELLE
→ DEC-118
→ RÉAUDIT-02-v3.7 NEXT

03 Taxonomy
→ spécification v1.0 historique sur la frontière KRP
→ boundary bridge actif : working/03_Taxonomy/03_Taxonomy_BOUNDARY_BRIDGE_DEC-118.md
→ réécriture complète dans son propre tour
```

## KRP — règle active fondamentale

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Taxonomy communique uniquement un **changement réel de besoin** dans sa fermeture de sortie :

```text
triplet Blueprint écrit avec succès
↓
même IdeaSlot consommé
↓
si le Domain reste exploitable : silence

si ENCORE EXPLOITABLE → VIDE :
DOMAIN_EXHAUSTED(depth,domain)
```

Signification :

```text
CE DOMAIN EST VIDE
```

Le fait reste en attente sans activer KRP.

KRP le consomme seulement lors de sa prochaine activation après :

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ Factory
→ NOUVEAU Blueprint
→ KRP
```

KRP fait alors :

```text
VISIBLE → ESTOMPÉ
```

`ESTOMPÉ` = Domain abstrait/exclu des rotations restantes du tour courant.

Taxonomy ne choisit aucun prochain Domain/Depth et n’émet pas `DEPTH_EXHAUSTED` dans le contrat actif.

## KRP — interdiction de sources historiques

Ne jamais reconstruire ou auditer KRP depuis :

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
```

Source KRP unique :

```text
docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md v3.7
```

## Interdictions générales

- ne jamais utiliser `archive/` comme vérité active ;
- ne jamais déduire l’architecture depuis le code ;
- ne jamais travailler deux spécifications en parallèle ;
- ne jamais modifier une spécification verrouillée sans révision complète/versionnement lorsque son architecture évolue ;
- ne jamais inventer un contrat manquant depuis un ancien chat ;
- ne jamais promouvoir un fichier `BRIDES`, `REFERENCE` ou `RECONSTRUCTION` dans `specifications/` sans verrouillage/certification explicite.

## Extension KRP Phases 1–2

KRP est complet pour la partie intellectuelle. Les éventuelles interfaces provenant plus tard de Phase1/Phase2 sont **réservées, non spécifiées et non bloquantes pour la fermeture intellectuelle actuelle**.

Toute extension future de KRP exige une nouvelle version complète + une nouvelle DEC.

## Branche officielle

```text
replit/intellectual-engine-current-2026-08-16
```

## Prochaine opération exacte

```text
RÉAUDIT-02-v3.7
```

Comparer le diff local Replit déjà commencé contre la v3.7 canonique. Classer `KEEP / REVERT / MODIFY / MISSING`. Aucun nouveau patch avant fermeture de ce réaudit.
