# StrategyBuzzer — Architecture Register actif

**Date :** 2026-08-23  
**Statut :** ACTIVE — registre de consolidation de la phase de spécification  
**Règle :** aucune décision n’est supprimée. Une décision remplacée devient `SUPERSEDED`.

## Statuts

```text
DRAFT
UNDER_REVIEW
OFFICIAL
SUPERSEDED
REJECTED
```

---

# Index normalisé obligatoire

| Identifiant | Version | Date | Statut | Justification / décision | Modules concernés | Décision remplacée | Décision remplaçante |
|---|---|---|---|---|---|---|---|
| DEC-082 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DOMAIN_EXHAUSTED` par Taxonomy sans séparation fait/décision de rotation | 02,03 | AUCUNE | DEC-116 |
| DEC-083 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DEPTH_EXHAUSTED` par Taxonomy | 02,03 | AUCUNE | DEC-116 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer information d’épuisement et déclenchement lifecycle du prochain Blueprint | 02,03,11 | AUCUNE | précisée par DEC-116 |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal inverse `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | SUPERSEDED | Ancien contrat de transport des signaux Taxonomy → KRP | 02,03 | AUCUNE | DEC-116 |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin décisionnel KRP | 02,03 | AUCUNE | DEC-094 puis DEC-116 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet lifecycle | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne formulation double autorité autour de `DEPTH_EXHAUSTED` Taxonomy | 02,03 | DEC-088 | DEC-116 |
| DEC-095 | 1.0 | 2026-08-16 | OFFICIAL | Un bassin Taxonomy est identifié par Depth + occurrence du tour + Domain; occurrence interne Taxonomy, aucun slot Blueprint | 03,02 frontière | AUCUNE | AUCUNE |
| DEC-096 | 1.0 | 2026-08-16 | OFFICIAL | Un seul Subdomain officiel par occurrence de bassin | 03 | AUCUNE | AUCUNE |
| DEC-097 | 1.0 | 2026-08-16 | OFFICIAL | Subdomain + SubjectBank créés atomiquement; aucun Subdomain vide persisté | 03 | AUCUNE | AUCUNE |
| DEC-098 | 1.0 | 2026-08-16 | OFFICIAL | SubjectBank : 50 est un plafond, jamais un quota artificiel | 03 | AUCUNE | AUCUNE |
| DEC-099 | 1.0 | 2026-08-16 | OFFICIAL | Subjects PASS persistants; FAIL Subject éphémères dans l’appel courant | 03 | AUCUNE | AUCUNE |
| DEC-100 | 1.0 | 2026-08-16 | OFFICIAL | Préparation équilibrée des lots avec minimum d’appels; cible technique 10 Subjects/appel | 03 | AUCUNE | AUCUNE |
| DEC-101 | 1.0 | 2026-08-16 | OFFICIAL | ValidationDominantIdeas fournit à Gemini les règles utilisées pendant la création; aucun moteur autonome après coup | 03,04 | anciennes formulations VDI moteur autonome | AUCUNE |
| DEC-102 | 1.0 | 2026-08-16 | OFFICIAL | Un Subject préparé avec succès produit 1..5 Dominant Ideas PASS; 0 PASS = anomalie | 03,04 | anciennes règles 0..5 normal | AUCUNE |
| DEC-103 | 1.0 | 2026-08-16 | OFFICIAL | Identité anti-doublon DI contextualisée : Depth + Domain + Subdomain + Subject + Dominant Idea | 03 | AUCUNE | AUCUNE |
| DEC-104 | 1.0 | 2026-08-16 | OFFICIAL | LOOKBACK-2 traverse le cycle `10→2` sans remise à zéro | 03 | AUCUNE | AUCUNE |
| DEC-105 | 1.0 | 2026-08-16 | OFFICIAL | Dominant Idea FAIL persistante pendant sa fenêtre et jamais exploitable comme IdeaSlot | 03 | AUCUNE | AUCUNE |
| DEC-106 | 1.0 | 2026-08-16 | OFFICIAL | IdeaSlot sélectionné = `dominant_idea_active` écrit = IdeaSlot `CONSUMED`, immédiatement après écriture réussie | 01,03,11 frontière | ancienne consommation tardive | AUCUNE |
| DEC-107 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne garde liée à un signal de commande; garde de vacuité reprise comme fait Taxonomy par DEC-116 | 02,03,Admin/Ops | AUCUNE | DEC-116 |
| DEC-108 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne sémantique `DEPTH_EXHAUSTED(depth)` produit par Taxonomy | 02,03 | ancienne fin définitive | DEC-116 |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne persistance/idempotence formulée autour de deux signaux Taxonomy | 02,03 frontière,Admin/Ops | AUCUNE | DEC-116 |
| DEC-112 | 1.0 | 2026-08-16 | SUPERSEDED | Taxonomy v1.0 verrouillée; sa frontière KRP doit être réécrite dans son propre tour | 03 + frontières | AUCUNE | DEC-116 sur frontière; future DEC Taxonomy v1.1 |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | SUPERSEDED | Verrouillage KRP v3.3 basé sur `DOMAIN_EXHAUSTED` + `DEPTH_EXHAUSTED` produits par Taxonomy | 02 + frontières | v3.2 + ALIGN-02 | DEC-115 puis DEC-116 |
| DEC-115 | 3.4 | 2026-08-23 | SUPERSEDED | KRP devait lire une réalité Taxonomy persistée; corrigé en communication active factuelle | 02 + frontières 01,03,11 | DEC-114 | DEC-116 |
| DEC-116 | 3.5 | 2026-08-23 | OFFICIAL | Taxonomy pousse `DOMAIN_EXHAUSTED(depth,domain)` comme fait de Banks vides; KRP persiste ce fait mais n’applique la rotation qu’au prochain Blueprint; KRP seul décide Domain, fin de tour, prochain Depth et HOLD; aucun `DEPTH_EXHAUSTED` Taxonomy actif | 02 + frontières 01,03,11 | DEC-082,083,087,094,107,108,111,112(frontière),114,115 | AUCUNE |

---

# DEC-116 — Communication factuelle Taxonomy → KRP et ownership de rotation

- **Version :** 3.5
- **Date :** 2026-08-23
- **Statut :** **OFFICIAL**
- **Module propriétaire :** `02_KernelRotationPlanner`
- **Source canonique :** `specifications/02_KernelRotationPlanner.md` v3.5

## Décision

```text
Taxonomy
= propriétaire de ses Banks
= vérifie la vacuité réelle du Domain actif
= pousse DOMAIN_EXHAUSTED(depth, domain) lorsque le Domain est réellement vide
= n’ordonne aucune rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle du noyau suivant
= n’ordonne aucune rotation

KernelBlueprintFactory
= crée le NOUVEAU Blueprint

DepthNeedMatrix
= indique les Depths qui ont encore besoin de tours
= ne connaît pas la vacuité des Banks Taxonomy

KernelRotationPlanner
= reçoit/persiste le fait Domain vide
= autorité UNIQUE de rotation
= choisit seul le prochain Domain
= ferme seul le tour lorsque ses 8 Domaines sont ESTOMPÉ
= choisit seul le prochain Depth via Matrix
= décide seul de HOLD
```

## Signal factuel actif

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification : les Banks du Domain actif ne contiennent plus de contenu exploitable.

Garde Taxonomy :

```text
remaining_subjects = 0
AND
remaining_ideas = 0
```

Le signal ne contient aucune destination de rotation.

## Moment de rotation

À la réception du signal :

```text
KRP persiste VISIBLE → ESTOMPÉ
```

mais ne choisit pas immédiatement un autre territoire.

La rotation est appliquée seulement lorsque :

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle
→ Factory crée un nouveau Blueprint
→ KRP applique son RotationState
```

## Fin du tour

Taxonomy ne produit pas `DEPTH_EXHAUSTED`.

Au prochain Blueprint, si KRP constate ses huit Domaines `ESTOMPÉ` :

```text
KRP ferme le tour
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth nécessaire
```

## Depth 10

```text
10 terminé
→ Matrix
→ prochain Depth encore nécessaire
→ retour possible vers 2
```

La fin de Depth 10 ne signifie jamais HOLD à elle seule.

## Persistance

```text
KRP-002 — DOMAIN_EXHAUSTED_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
```

Politique technique : 1 tentative initiale + 3 retries, puis `BLOCKED` en échec persistant.

## Taxonomy v1.0

La logique intellectuelle interne de Taxonomy v1.0 reste une référence historique utile, mais sa frontière KRP est superseded. `03_Taxonomy` devra être réécrite intégralement en v1.1 dans son propre tour et intégrer DEC-116.

---

# Sources canoniques actuelles

```text
01 → specifications/01_KernelBlueprint.md
02 → specifications/02_KernelRotationPlanner.md v3.5
03 → v1.0 non active sur frontière KRP; bridge DEC-116 puis future v1.1
```

Pour KRP :

```text
docs/architecture/02_KernelRotationPlanner.md
→ HISTORIQUE

docs/architecture/02_KernelRotationPlanner_v3.3_ALIGNMENT.md
→ SUPERSEDED

working/02_KernelRotationPlanner/02_KernelRotationPlanner_REFERENCE_ACTIVE.md
→ PROMOTED / CLOSED
```

---

# Prochaine étape obligatoire

Le Build Replit commencé contre v3.3/v3.4 reste arrêté.

```text
KRP v3.5 / DEC-116
↓
réaudit ciblé du diff local déjà commencé
↓
KEEP / REVERT / MODIFY
↓
implantation ciblée restante
↓
tests contractuels v3.5
↓
validation terminale KRP
```

Ne pas reprendre le Build précédent sans ce réaudit.