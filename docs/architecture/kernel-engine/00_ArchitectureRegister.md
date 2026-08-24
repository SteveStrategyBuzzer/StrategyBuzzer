# StrategyBuzzer — Architecture Register actif

**Date :** 2026-08-24  
**Statut :** ACTIVE — registre de consolidation de la phase de spécification  
**Règle :** aucune décision n’est supprimée. Une décision remplacée devient `SUPERSEDED`; une proposition documentaire non autorisée ou refusée devient `REJECTED`.

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
| DEC-082 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DOMAIN_EXHAUSTED` par Taxonomy comme mécanisme; v4.0 sépare le fait Taxonomy du moteur interne KRP | 02,03 | AUCUNE | DEC-119 |
| DEC-083 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DEPTH_EXHAUSTED` par Taxonomy | 02,03 | AUCUNE | DEC-119 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer information d’épuisement Taxonomy et déclenchement lifecycle du prochain Blueprint | 02,03,11 | AUCUNE | précisée par DEC-119 |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal inverse `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | OFFICIAL | Le transport physique du fait Taxonomy peut varier; la sémantique métier et l’ownership restent contractuels | 02,03 | AUCUNE | précisée par DEC-119 |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin KRP | 02,03 | AUCUNE | DEC-094 puis DEC-119 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet lifecycle | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne double autorité où Taxonomy produisait `DEPTH_EXHAUSTED`; v4.0 place `DEPTH_EXHAUSTED + DepthNeedMatrix` dans KRP | 02,03 | DEC-088 | DEC-119 |
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
| DEC-107 | 1.0 | 2026-08-16 | SUPERSEDED sur frontière KRP | Ancienne formulation où Taxonomy produisait directement `DOMAIN_EXHAUSTED`; la garde de contenu sera réévaluée dans 03_Taxonomy | 02,03,Admin/Ops | AUCUNE | DEC-119 sur ownership KRP |
| DEC-108 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne sémantique `DEPTH_EXHAUSTED(depth)` produit par Taxonomy | 02,03 | ancienne fin définitive | DEC-119 |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | OFFICIAL | KRP persiste ses transitions d’épuisement et fermeture avant progression; répétition = NO-OP; politique 1+3 retries | 02,03 frontière,Admin/Ops | AUCUNE | précisée par DEC-119 |
| DEC-112 | 1.0 | 2026-08-16 | OFFICIAL | Spécification Taxonomy v1.0 verrouillée; sa frontière KRP doit maintenant être revue dans le propre tour de 03_Taxonomy | 03 + frontières | AUCUNE | future DEC Taxonomy |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | SUPERSEDED | KRP v3.3 avait le bon cadran Domain mais attribuait encore `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` à Taxonomy | 02 + frontières | v3.2 + ALIGN-02 | DEC-119 |
| DEC-115 | 3.4 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : KRP devait lire une réalité Taxonomy persistée | 02 + frontières | AUCUNE | DEC-119 |
| DEC-116 | 3.5 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : frontière signal/persistance incorrecte | 02 + frontières | AUCUNE | DEC-119 |
| DEC-117 | 3.6 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : séquentialité ajoutée avec mécanique Domain incomplète | 02 + frontières | AUCUNE | DEC-119 |
| DEC-118 | 3.7 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : conservait à tort le même Domain tant qu’il restait `VISIBLE` | 02 + frontières | AUCUNE | DEC-119 |
| DEC-119 | 4.0 | 2026-08-24 | OFFICIAL | KRP v4.0 : cadran Domain restauré; `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` deviennent deux moteurs internes KRP; Taxonomy fournit seulement le fait terminal de consommation; `DEPTH_EXHAUSTED` contient `DepthNeedMatrix` et toute la rotation des Depths | 02 + frontière 03 | DEC-114 sur ownership + rejette DEC-115..118 | AUCUNE |

---

# DEC-115 à DEC-118 — historique rejeté

Ces quatre entrées sont conservées uniquement parce que le registre ne supprime jamais l’historique.

Elles correspondent à des modifications documentaires effectuées sans autorisation explicite de modifier la Bible et ne constituent plus aucune source architecturale active.

```text
DEC-115 → REJECTED
DEC-116 → REJECTED
DEC-117 → REJECTED
DEC-118 → REJECTED
```

Aucune implantation ne doit être auditée ou construite depuis ces versions.

---

# DEC-119 — Moteurs internes KRP d’épuisement et rotation complète

- **Version :** 4.0
- **Date :** 2026-08-24
- **Statut :** **OFFICIAL**
- **Module propriétaire :** `02_KernelRotationPlanner`
- **Source canonique :** `specifications/02_KernelRotationPlanner.md` v4.0

## Décision centrale

```text
Taxonomy
= fournit uniquement un fait terminal de consommation du contenu du Domain

KRP
= décide absolument tout ce qui suit
```

Le fait Taxonomy signifie :

```text
la dernière Dominant Idea
du dernier Subject encore exploitable
du Domain attribué
vient d’être utilisée
```

Le nom technique et le transport exact seront corrigés dans `03_Taxonomy` lors de son propre tour.

## Architecture interne KRP

```text
KernelRotationPlanner
│
├── DomainRotation
│   └── avance au prochain Domain VISIBLE à chaque nouveau Blueprint
│
├── DOMAIN_EXHAUSTED
│   ├── reçoit le fait terminal Taxonomy
│   ├── VISIBLE → ESTOMPÉ
│   └── si dernier Domain actif → déclenche DEPTH_EXHAUSTED
│
└── DEPTH_EXHAUSTED
    ├── contient DepthNeedMatrix
    ├── ferme le tour du Depth
    ├── cycle_completed += 1 exactement une fois
    ├── calcule cycle_remaining
    └── choisit le prochain Depth nécessaire
```

## DomainCycle

```text
Géographie
→ Histoire
→ Faune
→ Art
→ Sport
→ Cinéma
→ Cuisine
→ Science
→ Géographie
```

À chaque nouveau Blueprint : prochain Domain `VISIBLE`.

Un Domain `ESTOMPÉ` est sauté jusqu’à la fin du tour courant.

## DEPTH_EXHAUSTED / DepthNeedMatrix

Cibles :

```text
2  = 250
4  = 300
6  = 350
7  = 350
8  = 350
9  = 250
10 = 100
```

Cycle :

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → 2 → ...
```

Les Depths dont `cycle_remaining = 0` sont sautés.

La fin de Depth 10 ne produit jamais HOLD à elle seule.

HOLD seulement lorsque tous les besoins sont satisfaits.

## Lifecycle

```text
ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ lifecycle/orchestration
→ KernelBlueprintFactory
→ NOUVEAU Blueprint
→ KRP
```

Aucune route directe `CURRENT_KERNEL_RECEIVED → KRP`.

## Frontière Taxonomy

`03_Taxonomy.md` n’est pas modifié par DEC-119.

Sa mécanique de sortie sera corrigée dans son propre tour afin de supprimer son ancienne ownership de `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` et de ne lui laisser que le fait terminal de consommation.

---

# Sources canoniques actuelles pour le bloc en cours

```text
01 → specifications/01_KernelBlueprint.md v2.0
02 → specifications/02_KernelRotationPlanner.md v4.0 / DEC-119
03 → specifications/03_Taxonomy.md v1.0 À RELIRE ENSUITE pour correction de frontière
```

---

# Prochaine étape obligatoire

Avant toute implantation KRP :

```text
validation humaine de KRP v4.0
↓
RÉAUDIT-02-v4.0
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
implantation KRP seulement
↓
validation terminale KRP
```

Taxonomy reste un bloc séparé et ne doit pas être implanté en parallèle.