# StrategyBuzzer — Architecture Register actif

**Date :** 2026-08-20  
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
| DEC-082 | antérieure | antérieure | OFFICIAL | Taxonomy est l’autorité réelle de fin de matière d’un Domain et produit `DOMAIN_EXHAUSTED` | 02,03 | AUCUNE | précisée par DEC-107 |
| DEC-083 | antérieure | antérieure | OFFICIAL | Taxonomy produit `DEPTH_EXHAUSTED`; sa sémantique est précisée comme fin d’un tour | 02,03 | AUCUNE | précisée par DEC-108 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer flux d’épuisement et déclenchement du prochain Blueprint | 02,03,11 | AUCUNE | AUCUNE |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | OFFICIAL | Le transport physique des signaux peut varier; leur sémantique/ownership reste contractuelle | 02,03 | AUCUNE | AUCUNE |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin décisionnel KRP | 02,03 | AUCUNE | DEC-094 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | OFFICIAL | Double autorité : `DEPTH_EXHAUSTED` ferme un tour; `DepthNeedMatrix` porte les besoins globaux `cycle_target/cycle_completed`; KRP combine les deux | 02,03 | DEC-088 | AUCUNE |
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
| DEC-107 | 1.0 | 2026-08-16 | OFFICIAL | `DOMAIN_EXHAUSTED` autorisé uniquement après garde `remaining_subjects=0 AND remaining_ideas=0`; sinon `TAX-003` | 02,03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-108 | 1.0 | 2026-08-16 | OFFICIAL | `DEPTH_EXHAUSTED(depth)` signifie fin du tour courant du Depth, pas fin définitive du besoin global | 02,03 | ancienne sémantique fin définitive | AUCUNE |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | OFFICIAL | KRP persiste `VISIBLE→ESTOMPÉ` et fermeture de tour avant progression; répétition = `NO-OP`; 1+3 retries; KRP-002/KRP-003 | 02,03 frontière,Admin/Ops | AUCUNE | AUCUNE |
| DEC-112 | 1.0 | 2026-08-16 | OFFICIAL | Spécification Taxonomy v1.0 verrouillée à Architecture/Contrat 100 % | 03 + frontières | AUCUNE | AUCUNE |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | OFFICIAL | Spécification canonique KRP v3.3 verrouillée pour la partie intellectuelle; intègre DEC-094/107/108/111; ALIGN-02 superseded; futures interfaces Phases 1–2 réservées et uniquement ajoutables par nouvelle version/DEC | 02 + frontières 01,03,06,08,11 | v3.2 + ALIGN-02 comme vérité active | AUCUNE |

---

# Décisions de consolidation KRP v3.3

## DEC-094 — Double autorité : fin de tour vs besoin global

- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `DEPTH_EXHAUSTED(depth)` produit par Taxonomy termine un tour intellectuel du Depth. `DepthNeedMatrix` conserve `cycle_target` et `cycle_completed`. KRP combine les deux pour choisir le prochain Depth nécessaire.
- **Cibles :** `2=250, 4=300, 6=350, 7=350, 8=350, 9=250, 10=100` tours.
- **PRODUCTION_ON_HOLD :** seulement lorsque toutes les cibles sont satisfaites.
- **Modules :** 02,03.
- **Remplace :** DEC-088.

## DEC-107 — DOMAIN_EXHAUSTED terminal avec garde TAX-003

- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** Taxonomy ne produit `DOMAIN_EXHAUSTED` qu’après `remaining_subjects=0 AND remaining_ideas=0`. Sinon `TAX-003 — DOMAIN_EXHAUSTION_BLOCKED_REMAINING_CONTENT` bloque le signal.
- **Modules :** 02,03,Admin/Ops.
- **Précise :** DEC-082.

## DEC-108 — DEPTH_EXHAUSTED = fin d’un tour

- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `DEPTH_EXHAUSTED(depth)` est produit lorsque les huit Domaines du tour courant sont épuisés. Il termine un tour, pas le besoin global du Depth.
- **Modules :** 02,03.
- **Précise :** DEC-083.

## DEC-111 — Persistance/idempotence KRP

- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** KRP persiste `VISIBLE→ESTOMPÉ` et la fermeture de tour avant progression. Signal déjà commité répété = `NO-OP`. Échec de persistance : 1 tentative initiale + 3 retries; défauts `KRP-002` / `KRP-003`; blocage après échec non résolu.
- **Modules :** 02,03 frontière,Admin/Ops.

## DEC-114 — Verrouillage du contrat canonique KRP v3.3

- **Version :** 3.3
- **Date :** 2026-08-20
- **Statut :** **OFFICIAL**
- **Décision :** `docs/architecture/kernel-engine/specifications/02_KernelRotationPlanner.md` devient l’unique spécification canonique du KRP pour la partie intellectuelle.
- **Justification :** éliminer définitivement la contradiction entre l’ancien ALIGN-02/v3.2 et les décisions actives DEC-094, DEC-107, DEC-108 et DEC-111; fixer la frontière Factory → nouveau Blueprint → KRP → Taxonomy.
- **Contrat intellectuel :** KRP reçoit un nouveau Blueprint déjà créé; combine RotationState + DepthNeedMatrix + signaux Taxonomy; écrit uniquement `depth + domain`; persiste ses transitions; expose le Blueprint à Taxonomy; ne reçoit jamais directement l’ancien Blueprint depuis ReadyBank.
- **Cycle :** `2→4→6→7→8→9→10→prochain Depth encore nécessaire`, avec retour possible vers 2.
- **Domaines de création :** Géographie, Histoire, Faune, Art, Sport, Cinéma, Cuisine, Science. `Général` exclu de la création.
- **Périmètre :** **complet et verrouillé pour la partie intellectuelle**.
- **Phases 1–2 futures :** les interfaces éventuellement nécessaires restent **RÉSERVÉES / NON SPÉCIFIÉES**. Une nouvelle exigence provenant de 06_Phase1 ou 08_Phase2 exige une nouvelle version KRP et une nouvelle DEC; elle ne peut pas modifier silencieusement v3.3.
- **Modules concernés :** 02 et frontières 01,03,06,08,11.
- **Décision remplacée :** v3.2/ALIGN-02 en tant que vérité active.
- **Décision remplaçante :** AUCUNE.

---

# Sources canoniques actuelles

```text
01 → specifications/01_KernelBlueprint.md
02 → specifications/02_KernelRotationPlanner.md v3.3
03 → specifications/03_Taxonomy.md
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

La spécification intellectuelle KRP est maintenant verrouillée.

```text
AUDIT-02-00
↓
audit du code réel contre specifications/02_KernelRotationPlanner.md v3.3
↓
KEEP / MODIFY / REMOVE / MISSING / UNRESOLVED
↓
implantation ciblée
↓
validation terminale du code KRP
```

Aucune conception Phase 1 ou Phase 2 n’est autorisée dans KRP avant le tour de spécification de ces modules.
