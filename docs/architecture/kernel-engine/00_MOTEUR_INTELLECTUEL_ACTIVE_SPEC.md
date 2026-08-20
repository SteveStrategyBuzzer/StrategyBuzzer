# StrategyBuzzer — Moteur intellectuel — Spécification active maître

**Version :** 1.3.0-kpr-lock  
**Date :** 2026-08-20  
**Statut :** **ACTIF — VÉRITÉ GLOBALE COURANTE**  
**Portée :** architecture globale, frontières, ownership, communications et état documentaire des modules 01→11.

> Cette version intègre le verrouillage canonique de `02_KernelRotationPlanner v3.3` pour la **partie intellectuelle**. Elle retire définitivement `ALIGN-02` et la v3.2 de toute position active. Les éventuelles interfaces KRP requises plus tard par Phase1/Phase2 restent réservées et ne sont pas inventées ici.

---

# 1. Hiérarchie des sources de vérité

```text
00_ConstitutionCognitive.md
↓
00_ArchitectureRegister.md
↓
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
↓
spécification canonique verrouillée du module concerné
↓
working/ du SEUL module actif si nécessaire
↓
code réel seulement après verrouillage
```

`00_CURRENT_HANDOFF.md` indique le point exact de reprise mais n’a aucune autorité architecturale propre.

`archive/`, les documents `SUPERSEDED` et les anciennes versions historiques ne sont jamais des sources actives.

Principe :

```text
UNE RESPONSABILITÉ
=
UN PROPRIÉTAIRE
=
UNE IMPLÉMENTATION ACTIVE
=
UNE SOURCE DE VÉRITÉ
```

---

# 2. Méthode officielle

```text
Idée métier
↓
Architecture
↓
Spécification
↓
Architecture Register
↓
VERROUILLAGE
↓
Audit du code
↓
Implantation
↓
Validation
```

Jamais :

```text
Code existant
↓
Architecture supposée
```

Une seule spécification ou un seul bloc d’implantation est travaillé à la fois.

---

# 3. Roadmap officielle

```text
01_KernelBlueprint
↓
02_KernelRotationPlanner
↓
03_Taxonomy
↓
04_ValidationDominantIdeas
↓
05_QuestionIntent
↓
06_Phase1
↓
07_ValidationPhase1
↓
08_Phase2
↓
09_ValidationPhase2
↓
10_Quarantine
↓
11_ReadyBank
```

---

# 4. Tableau de bord courant

| Module | Architecture | Contrat | Implémentation | Validation | Statut actif |
|---|---:|---:|---:|---:|---|
| 01 KernelBlueprint | **100 % v2.0 canonique** | **100 %** | Section intellectuelle alignée; autres territoires suivent leurs propriétaires | validation terminale globale différée | **CONTRAT D’ENTRÉE DISPONIBLE POUR KRP** |
| 02 KernelRotationPlanner | **100 % intellectuel** | **100 % intellectuel** | **À AUDITER** | **NON** | **v3.3 VERROUILLÉ — AUDIT-02-00 NEXT** |
| 03 Taxonomy | **100 %** | **100 %** | à auditer/adapter dans son tour | non terminale | **v1.0 VERROUILLÉE** |
| 04 ValidationDominantIdeas | brides actives | règles actives utilisées par Gemini | non | non | À SPÉCIFIER dans son tour |
| 05 QuestionIntent | **100 %** | **100 %** | historique certifié | historique certifié | verrouillé selon certificat récupéré |
| 06 Phase1 | brides seulement | non verrouillé | non | non | À VENIR |
| 07 ValidationPhase1 | brides seulement | non verrouillé | non | non | À VENIR |
| 08 Phase2 | brides seulement | non verrouillé | non | non | À VENIR |
| 09 ValidationPhase2 | brides seulement | non verrouillé | non | non | À VENIR |
| 10 Quarantine | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |
| 11 ReadyBank | frontière connue | non verrouillé | à auditer plus tard | non | À VENIR |

**Bloc actif : `AUDIT-02-00`.**

Les besoins détaillés de Phase1/Phase2 ne sont pas une condition pour auditer et fermer la responsabilité intellectuelle de KRP.

---

# 5. Pipeline intellectuel actif

```text
KernelBlueprintFactory
↓
NOUVEAU KernelBlueprint canonique
↓
KernelRotationPlanner
  ↳ écrit uniquement depth + domain
↓
Taxonomy
  ↳ Gemini utilise les règles ValidationDominantIdeas pendant la création des Dominant Ideas
↓
QuestionIntent
  ↳ écrit kernel_code
↓
FIN DE LA PHASE INTELLECTUELLE
```

La suite sera définie par les contrats propriétaires :

```text
Phase1
↓
ValidationPhase1
↓
Phase2
↓
ValidationPhase2
↓
Quarantine / ReadyBank selon verdicts
```

Aucune architecture détaillée Phase1/Phase2 ne doit être inventée avant leur tour.

---

# 6. KernelBlueprint — contrat d’entrée de KRP

Source canonique :

```text
specifications/01_KernelBlueprint.md
```

Pour la frontière intellectuelle active :

```text
KernelBlueprintFactory
↓
blueprint_id immuable
↓
Blueprint remis à KRP
```

KRP reçoit un Blueprint nouveau avec :

```text
blueprint_id = REMPLI
depth = NULL
domain = NULL
```

Le Blueprint courant n’est jamais recyclé pour une nouvelle rotation.

Ownership Section intellectuelle :

```text
blueprint_id            → KernelBlueprintFactory

depth                   → KernelRotationPlanner
domain                  → KernelRotationPlanner

subdomain_active        → Taxonomy
subject_active          → Taxonomy
dominant_idea_active    → Taxonomy

kernel_code             → QuestionIntent
```

Les contenus/payloads futurs de gameplay ou traduction restent la responsabilité de leurs modules propriétaires et ne doivent pas être déduits depuis KRP.

---

# 7. KernelRotationPlanner — v3.3 VERROUILLÉ, PARTIE INTELLECTUELLE

Source canonique unique :

```text
specifications/02_KernelRotationPlanner.md
```

Certificat :

```text
certificates/02_KernelRotationPlanner/02_KernelRotationPlanner_CERTIFICAT_VERROUILLAGE.md
```

Architecture Register : **DEC-114**.

Documents explicitement non autoritatifs :

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE v3.2

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

## 7.1 Mission

KRP choisit le prochain couple :

```text
depth + domain
```

et l’écrit une seule fois dans le **nouveau** Blueprint reçu.

Il ne crée pas le Blueprint et ne crée aucun contenu Taxonomy.

## 7.2 DepthCycle

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → prochain Depth encore nécessaire
```

Après 10, le cycle peut revenir vers 2 si un besoin global subsiste.

## 7.3 DomainCycle de création

```text
Géographie
→ Histoire
→ Faune
→ Art
→ Sport
→ Cinéma
→ Cuisine
→ Science
```

`Général` n’est pas un domaine de création.

## 7.4 DEC-094 — double autorité

Taxonomy est l’autorité de la fin intellectuelle réelle d’un tour.

`DepthNeedMatrix` est l’autorité quantitative globale :

```text
cycle_target[2]  = 250
cycle_target[4]  = 300
cycle_target[6]  = 350
cycle_target[7]  = 350
cycle_target[8]  = 350
cycle_target[9]  = 250
cycle_target[10] = 100

cycle_remaining[depth]
= max(0, cycle_target[depth] - cycle_completed[depth])
```

KRP combine les deux autorités pour choisir le prochain Depth encore nécessaire.

## 7.5 DEC-107 — DOMAIN_EXHAUSTED

Taxonomy ne produit `DOMAIN_EXHAUSTED` qu’après :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Sinon :

```text
TAX-003 — DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT
```

KRP applique seulement un signal valide reçu :

```text
VISIBLE
↓ DOMAIN_EXHAUSTED
ESTOMPÉ
```

Aucun `ESTOMPÉ → VISIBLE` dans le même tour.

## 7.6 DEC-108 — DEPTH_EXHAUSTED

Après les 8 Domaines du tour :

```text
Taxonomy
→ DEPTH_EXHAUSTED(depth)
```

Cela signifie :

```text
FIN DU TOUR
```

et jamais satisfaction définitive du besoin global du Depth.

Après commit valide :

```text
cycle_completed[depth] += 1
```

exactement une fois.

## 7.7 DEC-111 — persistance/idempotence

- transition valide persistée avant progression ;
- signal déjà commité répété → `NO-OP` ;
- 1 tentative initiale + 3 retries techniques ;
- `KRP-002` pour échec persistant de `DOMAIN_EXHAUSTED` ;
- `KRP-003` pour échec persistant de `DEPTH_EXHAUSTED` ;
- aucune nouvelle rotation basée sur un état non commité ;
- après épuisement des retries → état opérationnel `BLOCKED`.

## 7.8 Sortie / porte Taxonomy

KRP termine avec le même nouveau Blueprint :

```text
blueprint_id           = REMPLI
depth                  = REMPLI
domain                 = REMPLI
subdomain_active       = NULL
subject_active         = NULL
dominant_idea_active   = NULL
kernel_code            = NULL
```

Cette sortie est la porte d’entrée de Taxonomy.

KRP n’a pas à exécuter Taxonomy lui-même.

## 7.9 Lifecycle externe

```text
ReadyBank
↓
CURRENT_KERNEL_RECEIVED
↓
lifecycle/orchestration externe
↓
Factory crée NOUVEAU Blueprint
↓
KRP
```

Aucun handoff direct :

```text
ReadyBank → ancien Blueprint → KRP
```

## 7.10 PRODUCTION_ON_HOLD

Autorisé uniquement lorsque :

```text
pour tous les Depths :
cycle_completed[depth] >= cycle_target[depth]
```

et aucune transition KRP n’attend de commit.

## 7.11 Extension future Phases 1–2

La responsabilité intellectuelle KRP est **complète et verrouillée**.

Les interfaces éventuelles requises plus tard par `06_Phase1` ou `08_Phase2` sont :

```text
RÉSERVÉES
NON SPÉCIFIÉES
NON BLOQUANTES pour le verrouillage intellectuel v3.3
```

Si une Phase future démontre un besoin KRP :

```text
spécification propriétaire
↓
nouvelle version KRP
↓
nouvelle DEC
↓
audit / implantation / validation
```

Aucune extension future ne peut modifier silencieusement DEC-094, DEC-107, DEC-108 ou DEC-111.

---

# 8. Taxonomy — v1.0 VERROUILLÉE

Source :

```text
specifications/03_Taxonomy.md
```

Taxonomy reçoit `depth + domain` fixés par KRP et possède ses réservoirs/progression internes.

Bassin métier :

```text
Depth + occurrence du Tour + Domain
```

L’occurrence reste interne à Taxonomy.

Un seul Subdomain officiel par occurrence.

Territoire viable :

```text
1 Subdomain
+
1..50 Subjects PASS
```

Gemini prépare les Dominant Ideas **en utilisant les règles ValidationDominantIdeas pendant la création**, pas par un moteur autonome après coup.

Préparation réussie d’un Subject :

```text
1..5 Dominant Ideas PASS
```

Après écriture Blueprint réussie :

```text
IdeaSlot sélectionné
=
dominant_idea_active écrit
=
IdeaSlot CONSUMED
```

---

# 9. ValidationDominantIdeas — frontière active

DEC-101 :

- VDI possède les règles intellectuelles de création/contrôle des Dominant Ideas ;
- Gemini utilise ces règles pendant le travail Taxonomy ;
- VDI ne lit/écrit pas directement le Blueprint ;
- VDI ne possède pas les Banks Taxonomy ;
- ne jamais restaurer une deuxième passe autonome « génération libre → moteur VDI ».

Le détail complet de 04 sera spécifié dans son tour.

---

# 10. QuestionIntent — frontière active

Entrée :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

Seule écriture Blueprint :

```text
kernel_code
```

QuestionIntent ne modifie aucun champ intellectuel amont.

---

# 11. Frontières aval — ne pas anticiper

## 06 Phase1

Propriétaire futur de la création gameplay. Contrat détaillé à spécifier avec 06.

## 07 ValidationPhase1

Validation de Phase1. Contrat détaillé à spécifier avec 07.

## 08 Phase2

Phase suivante selon sa future spécification. Aucun détail ne doit être inventé ici.

## 09 ValidationPhase2

Contrat détaillé à spécifier avec 09.

## 10 Quarantine

Station de correction contrôlée selon son futur contrat.

## 11 ReadyBank

Destination terminale d’un noyau prêt. Frontière connue :

```text
ReadyBank reçoit noyau
↓
CURRENT_KERNEL_RECEIVED
↓
Factory crée Blueprint suivant
↓
KRP
```

---

# 12. Frontières interdites

```text
KRP ne crée pas Blueprint
KRP ne crée pas Subdomain/Subject/Dominant Idea
KRP n’écrit pas kernel_code
KRP ne lit pas les Banks Taxonomy
KRP ne reçoit pas directement l’ancien Blueprint de ReadyBank

Taxonomy ne choisit pas prochain Depth/Domain
Taxonomy ne modifie pas cycle_target/cycle_completed
Taxonomy n’écrit pas kernel_code

VDI n’est pas un moteur autonome après Taxonomy

QuestionIntent ne modifie pas les cinq dimensions intellectuelles amont

Phase1/Phase2 ne sont pas inventées avant leur spécification
```

---

# 13. Architecture Register structurant

```text
DEC-094  double autorité fin de tour / besoin global
DEC-095  occurrence de bassin par tour
DEC-101  VDI utilisé par Gemini pendant création
DEC-106  consommation exacte immédiate
DEC-107  DOMAIN_EXHAUSTED + garde TAX-003
DEC-108  DEPTH_EXHAUSTED = fin d’un tour
DEC-111  persistance/idempotence KRP
DEC-112  Taxonomy v1.0 verrouillée
DEC-113  KernelBlueprint v2.0 verrouillé
DEC-114  KRP v3.3 verrouillé — partie intellectuelle
```

`DEC-088` reste `SUPERSEDED` par `DEC-094`.

---

# 14. Références actives

```text
START_HERE.md
00_ConstitutionCognitive.md
00_ArchitectureRegister.md
00_MOTEUR_INTELLECTUEL_ACTIVE_SPEC.md
00_CURRENT_HANDOFF.md
00_DOCUMENTATION_MAP.md

specifications/01_KernelBlueprint.md
specifications/02_KernelRotationPlanner.md
specifications/03_Taxonomy.md

certificates/01_KernelBlueprint/
certificates/02_KernelRotationPlanner/
certificates/03_Taxonomy/
certificates/05_QuestionIntent/

working/04_ValidationDominantIdeas/04_ValidationDominantIdeas_BRIDES_ACTIVE.md
cross-module/AdminOperations_BRIDES_ACTIVE.md
```

Non actifs pour KRP :

```text
docs/architecture/02_KernelRotationPlanner.md
02_KernelRotationPlanner_v3.3_ALIGNMENT.md
working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
```

---

# 15. Prochain bloc exact

```text
AUDIT-02-00
```

Mission :

```text
auditer le code réel de KernelRotationPlanner
contre specifications/02_KernelRotationPlanner.md v3.3
```

Sortie obligatoire :

```text
KEEP
MODIFY
REMOVE
MISSING
UNRESOLVED
```

Aucun code ne doit être patché avant la fermeture de cet audit.

Les tests d’implantation ultérieurs devront vérifier **KRP et ses portes** : nouveau Blueprint reçu, choix Depth+Domain, RotationState, DepthNeedMatrix, idempotence/persistance, signaux Taxonomy, sortie vers Taxonomy — sans exiger que Taxonomy, Phase1 ou Phase2 exécutent déjà leur métier complet.
