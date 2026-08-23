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
| DEC-082 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DOMAIN_EXHAUSTED` sans séparation temporelle des modules | 02,03 | AUCUNE | DEC-117 puis DEC-118 |
| DEC-083 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DEPTH_EXHAUSTED` par Taxonomy | 02,03 | AUCUNE | DEC-118 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer information d’épuisement et déclenchement lifecycle du prochain Blueprint | 02,03,11 | AUCUNE | précisée par DEC-118 |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal inverse `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | SUPERSEDED | Ancien contrat de transport des signaux Taxonomy → KRP | 02,03 | AUCUNE | DEC-118 |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin décisionnel KRP | 02,03 | AUCUNE | DEC-094 puis DEC-118 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet lifecycle | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne formulation double autorité autour de `DEPTH_EXHAUSTED` Taxonomy | 02,03 | DEC-088 | DEC-118 |
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
| DEC-107 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne garde liée à une réception active KRP; la vacuité reste Taxonomy mais sa communication est désormais delta-only en fermeture de sortie | 02,03,Admin/Ops | AUCUNE | DEC-118 |
| DEC-108 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne sémantique `DEPTH_EXHAUSTED(depth)` produit par Taxonomy | 02,03 | ancienne fin définitive | DEC-118 |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne persistance/idempotence formulée autour d’une réception active des signaux Taxonomy | 02,03 frontière,Admin/Ops | AUCUNE | DEC-118 |
| DEC-112 | 1.0 | 2026-08-16 | SUPERSEDED | Taxonomy v1.0 verrouillée; sa frontière KRP doit être réécrite dans son propre tour | 03 + frontières | AUCUNE | DEC-118 sur frontière; future DEC Taxonomy v1.1 |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | SUPERSEDED | Verrouillage KRP v3.3 basé sur signaux Taxonomy appliqués directement | 02 + frontières | v3.2 + ALIGN-02 | DEC-115 puis DEC-116 puis DEC-117 puis DEC-118 |
| DEC-115 | 3.4 | 2026-08-23 | SUPERSEDED | KRP devait lire une réalité Taxonomy persistée | 02 + frontières 01,03,11 | DEC-114 | DEC-116 |
| DEC-116 | 3.5 | 2026-08-23 | SUPERSEDED | Taxonomy poussait le fait Domain vide mais KRP le persistait pendant la phase Taxonomy | 02 + frontières 01,03,11 | DEC-115 | DEC-117 |
| DEC-117 | 3.6 | 2026-08-23 | SUPERSEDED | Séquentialité stricte établie, mais le moment exact d’émission Taxonomy et la règle « changement seulement » n’étaient pas assez contractuels | 02 + frontières 01,03,11 | DEC-116 | DEC-118 |
| DEC-118 | 3.7 | 2026-08-23 | OFFICIAL | Frontière finale : Taxonomy émet `DOMAIN_EXHAUSTED(depth,domain)` uniquement dans sa fermeture de sortie, après écriture Blueprint réussie + consommation du même IdeaSlot, et seulement au changement réel « Domain encore exploitable → Domain vide »; le fait est conservé sans activer KRP; KRP le consomme au prochain Blueprint et abstrait le Domain `ESTOMPÉ` de ses rotations | 02 + frontières 01,03,11 | DEC-117 + précisions ownership historiques | AUCUNE |

---

# DEC-117 — Séquentialité stricte et fait Domain vide

- **Version :** 3.6
- **Date :** 2026-08-23
- **Statut :** SUPERSEDED
- **Remplacée par :** DEC-118
- **Apport historique conservé :** un seul module métier actif à la fois; le fait Domain vide n’active pas KRP; KRP l’applique au cycle suivant.

---

# DEC-118 — Fermeture Taxonomy delta-only et application KRP différée

- **Version :** 3.7
- **Date :** 2026-08-23
- **Statut :** **OFFICIAL**
- **Module propriétaire :** `02_KernelRotationPlanner`
- **Source canonique :** `specifications/02_KernelRotationPlanner.md` v3.7

## Décision centrale

```text
UN SEUL MODULE MÉTIER ACTIF À LA FOIS
```

Taxonomy ne communique pas l’état de ses Banks en continu. Il communique uniquement un **changement de besoin**.

## Moment exact de la communication Taxonomy

Dans la fermeture de sortie Taxonomy :

```text
IdeaSlot exact sélectionné
↓
triplet Subdomain + Subject + Dominant Idea prêt
↓
écriture Blueprint réussie
↓
consommation immédiate du même IdeaSlot
↓
évaluation de l’état final de l’occurrence du Domain
```

Si le Domain reste exploitable :

```text
AUCUN SIGNAL
```

Si cette consommation provoque :

```text
ENCORE EXPLOITABLE → VIDE
```

Taxonomy émet exactement une fois pour cette occurrence :

```text
DOMAIN_EXHAUSTED(depth, domain)
```

Signification :

```text
CE DOMAIN EST VIDE
```

## Règle delta-only

Taxonomy n’émet pas un statut à chaque noyau et n’émet pas un statut à chaque passage.

Il informe uniquement lorsqu’un besoin change.

Pour une occurrence de bassin :

```text
0 ou 1 DOMAIN_EXHAUSTED normal
```

Une future nouvelle occurrence du même `(Depth + Domain)` pourra émettre son propre signal lorsqu’elle deviendra vide.

Aucun signal positif `AVAILABLE` n’est nécessaire.

## Séparation temporelle

```text
Taxonomy FIN
→ éventuellement DOMAIN_EXHAUSTED
→ fait conservé
→ KRP INACTIF
→ pipeline continue
→ ReadyBank
→ CURRENT_KERNEL_RECEIVED
→ Factory crée NOUVEAU Blueprint
→ KRP ACTIF
```

KRP n’est jamais activé par le signal Taxonomy.

## Application KRP

À sa prochaine activation :

```text
KRP consomme DOMAIN_EXHAUSTED en attente
→ VISIBLE → ESTOMPÉ
```

`ESTOMPÉ` signifie :

```text
Domain abstrait / exclu des rotations restantes du tour courant
```

KRP choisit ensuite seul le prochain Domain `VISIBLE`.

## Fin de tour

Taxonomy ne produit pas `DEPTH_EXHAUSTED`.

Quand KRP constate huit Domaines `ESTOMPÉ` :

```text
KRP ferme le tour
→ cycle_completed[depth] += 1 exactement une fois
→ DepthNeedMatrix
→ prochain Depth encore nécessaire
```

## Depth 10

```text
Tour 10 fermé
→ Matrix
→ prochain Depth nécessaire
→ retour possible vers 2
```

La fermeture du Depth 10 n’implique jamais HOLD à elle seule.

## HOLD

`PRODUCTION_ON_HOLD` seulement lorsque toutes les cibles globales sont satisfaites et qu’aucune transition KRP n’est incertaine.

## Persistance KRP

```text
KRP-002 — DOMAIN_ROTATION_STATE_PERSIST_FAILED
KRP-003 — DEPTH_TOUR_STATE_PERSIST_FAILED
```

Politique technique : 1 tentative initiale + 3 retries, puis `BLOCKED` en échec persistant.

## Taxonomy

La future spécification Taxonomy v1.1 devra intégrer cette frontière dans son propre tour. Taxonomy v1.0 reste historique sur cette frontière.

---

# Sources canoniques actuelles

```text
01 → specifications/01_KernelBlueprint.md
02 → specifications/02_KernelRotationPlanner.md v3.7
03 → v1.0 historique sur frontière KRP; bridge DEC-118 puis future v1.1
```

---

# Prochaine étape obligatoire

Le Build Replit #163 reste arrêté.

```text
KRP v3.7 / DEC-118
↓
RÉAUDIT-02-v3.7 du diff local déjà commencé
↓
KEEP / REVERT / MODIFY / MISSING
↓
implantation ciblée restante
↓
tests contractuels v3.7
↓
validation terminale KRP
```

Ne pas reprendre le Build précédent sans ce réaudit.
