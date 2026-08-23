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
| DEC-082 | antérieure | antérieure | SUPERSEDED | Ancienne ownership : Taxonomy produisait `DOMAIN_EXHAUSTED` | 02,03 | AUCUNE | DEC-115 |
| DEC-083 | antérieure | antérieure | SUPERSEDED | Ancienne ownership : Taxonomy produisait `DEPTH_EXHAUSTED` | 02,03 | AUCUNE | DEC-115 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer la réalité d’épuisement intellectuel du déclenchement lifecycle du prochain Blueprint | 02,03,11 | AUCUNE | précisée par DEC-115 |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal inverse `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | SUPERSEDED | Ancien contrat de transport de signaux Taxonomy → KRP | 02,03 | AUCUNE | DEC-115 |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin décisionnel KRP | 02,03 | AUCUNE | DEC-094 puis DEC-115 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet lifecycle | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne formulation : `DEPTH_EXHAUSTED` Taxonomy fermait un tour; Matrix portait les besoins globaux | 02,03 | DEC-088 | DEC-115 |
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
| DEC-107 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne garde avant production de `DOMAIN_EXHAUSTED` par Taxonomy | 02,03,Admin/Ops | AUCUNE | DEC-115 |
| DEC-108 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne sémantique de signal `DEPTH_EXHAUSTED(depth)` | 02,03 | ancienne fin définitive | DEC-115 |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne persistance/idempotence KRP formulée autour des signaux d’épuisement | 02,03 frontière,Admin/Ops | AUCUNE | DEC-115 |
| DEC-112 | 1.0 | 2026-08-16 | SUPERSEDED | Verrouillage Taxonomy v1.0; ses détails internes restent historiques mais sa frontière KRP doit être réécrite | 03 + frontières | AUCUNE | DEC-115 sur la frontière; future DEC Taxonomy v1.1 |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | SUPERSEDED | Verrouillage KRP v3.3 basé encore sur les signaux Taxonomy d’épuisement | 02 + frontières | v3.2 + ALIGN-02 | DEC-115 |
| DEC-115 | 3.4 | 2026-08-23 | OFFICIAL | Taxonomy expose la réalité de ses réservoirs; ReadyBank déclenche le lifecycle; KRP est l’autorité UNIQUE qui interprète cette réalité et applique toutes les rotations Domain/Depth/HOLD | 02 + frontières 01,03,11 | DEC-082,083,087,094,107,108,111,112(frontière),114 | AUCUNE |

---

# DEC-115 — Ownership canonique de la rotation KRP

- **Version :** 3.4
- **Date :** 2026-08-23
- **Statut :** **OFFICIAL**
- **Module propriétaire :** `02_KernelRotationPlanner`
- **Source canonique :** `specifications/02_KernelRotationPlanner.md` v3.4

## Décision

La rotation intellectuelle possède désormais quatre responsabilités séparées :

```text
Taxonomy
= propriétaire de ses Banks et de la réalité intellectuelle de ce qu’il reste à exploiter
= n’ordonne aucune rotation

ReadyBank / CURRENT_KERNEL_RECEIVED
= déclenche le lifecycle du prochain noyau
= n’ordonne aucune rotation

KernelBlueprintFactory
= crée le NOUVEAU Blueprint

KernelRotationPlanner
= autorité UNIQUE de rotation
= lit RotationState + DepthNeedMatrix + réalité Taxonomy
= décide seul de conserver/changer Domain, fermer un tour, sélectionner le prochain Depth et HOLD
```

## Frontière Taxonomy

Taxonomy expose/persiste une réalité de réservoir :

```text
contenu exploitable restant
OU
aucun contenu exploitable restant
```

Le nom technique de l’interface n’est pas contractuel.

Les anciennes sorties de commande :

```text
DOMAIN_EXHAUSTED(depth, domain)
DEPTH_EXHAUSTED(depth)
```

ne sont plus des sorties contractuelles actives de Taxonomy.

## Domain rotation

Au prochain nouveau Blueprint :

```text
contenu restant
→ KRP conserve le même Depth + Domain

aucun contenu restant
→ KRP persiste VISIBLE → ESTOMPÉ
→ KRP choisit le prochain Domain selon SON DomainCycle
```

## Fin de tour

KRP ferme lui-même le tour lorsque les huit Domaines sont `ESTOMPÉ` :

```text
KRP ferme le tour
→ persistance
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth encore nécessaire
```

## Depth 10

La fermeture de Depth 10 n’implique jamais HOLD à elle seule.

```text
10 terminé
→ Matrix
→ prochain Depth encore nécessaire
→ retour possible à 2
```

## HOLD

`PRODUCTION_ON_HOLD` seulement lorsque tous les Depths ont atteint leurs cibles globales et qu’aucune transition KRP n’est incertaine.

## Persistance

KRP persiste ses propres transitions de rotation, non des commandes Taxonomy :

```text
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
```

Politique technique : 1 tentative + 3 retries, puis `BLOCKED` en échec persistant.

## Taxonomy v1.0

Les mécanismes intellectuels internes de Taxonomy v1.0 restent une référence historique utile, mais sa frontière de signaux KRP est superseded. `03_Taxonomy` devra être réécrite intégralement en v1.1 dans son propre tour avant implantation/validation Taxonomy.

---

# Sources canoniques actuelles

```text
01 → specifications/01_KernelBlueprint.md
02 → specifications/02_KernelRotationPlanner.md v3.4
03 → v1.0 non active sur frontière KRP; boundary bridge DEC-115 puis future v1.1
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

Le Build Replit commencé contre v3.3 reste arrêté.

```text
KRP v3.4 / DEC-115
↓
réaudit ciblé du diff local IMPL-02-01 déjà commencé
↓
KEEP / REVERT / MODIFY selon v3.4
↓
implantation ciblée restante
↓
tests contractuels v3.4
↓
validation terminale KRP
```

Ne pas reprendre le Build v3.3 tel quel.