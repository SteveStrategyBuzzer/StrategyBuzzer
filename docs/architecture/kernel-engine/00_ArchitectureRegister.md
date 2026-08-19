# StrategyBuzzer — Architecture Register actif

**Date :** 2026-08-19  
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

# Décisions historiques directement impactées

## DEC-082
- **Statut :** OFFICIAL
- **Décision :** `DOMAIN_EXHAUSTED` est prospectif et produit par Taxonomy.
- **Évolution :** précisé par DEC-107.

## DEC-083
- **Statut :** OFFICIAL
- **Décision :** `DEPTH_EXHAUSTED` est prospectif et produit par Taxonomy.
- **Évolution :** précisé par DEC-108 : il signifie la fin d’un **tour** du Depth.

## DEC-084
- **Statut :** OFFICIAL
- **Décision :** indépendance de l’état de progression Taxonomy et de la rotation KRP.

## DEC-085
- **Statut :** OFFICIAL
- **Décision :** flux informationnel d’épuisement distinct du déclenchement du prochain Blueprint.

## DEC-086
- **Statut :** REJECTED
- **Décision :** signal normal `AVAILABLE` rejeté.

## DEC-087
- **Statut :** OFFICIAL
- **Décision :** le transport physique des signaux est un détail d’implantation ; la sémantique qui produit/possède/consomme reste contractuelle.

## DEC-088
- **Version :** antérieure
- **Date :** antérieure au 2026-08-16
- **Statut :** **SUPERSEDED**
- **Ancienne décision :** `CYCLE_TARGET / cycle_completed` ne sont pas des autorités du chemin de Depth ; `DEPTH_EXHAUSTED` était la seule autorité.
- **Justification du remplacement :** l’architecture active distingue maintenant la fin intellectuelle d’un tour (`DEPTH_EXHAUSTED`) du besoin quantitatif global gameplay (`cycle_target/cycle_completed`).
- **Remplacée par :** DEC-094.
- **Modules concernés :** 02_KernelRotationPlanner, 03_Taxonomy.

## DEC-089
- **Statut :** REJECTED
- **Décision :** SHORTFALL et états dérivés rejetés.

## DEC-090
- **Statut :** REJECTED
- **Décision :** `DepthProductionState` comme architecture parallèle rejeté.

## DEC-093
- **Statut :** OFFICIAL historique
- **Décision pertinente :** la comptabilisation de réception d’un Blueprint est idempotente et n’autorise pas des effets doubles.
- **Note :** cette décision ne possède pas la consommation Taxonomy.

---

# Décisions OFFICIAL de consolidation

## DEC-094 — Double autorité : fin de tour vs besoin global
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `DEPTH_EXHAUSTED(depth)` produit par Taxonomy termine un tour intellectuel du Depth. `DepthNeedMatrix` conserve `cycle_target` et `cycle_completed`. KRP combine les deux pour choisir le prochain Depth nécessaire.
- **Cibles :** `2=250, 4=300, 6=350, 7=350, 8=350, 9=250, 10=100` tours.
- **PRODUCTION_ON_HOLD :** seulement lorsque toutes les cibles sont satisfaites.
- **Modules :** 02, 03.
- **Remplace :** DEC-088.

## DEC-095 — Occurrence de bassin Taxonomy par tour
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** un bassin de consommation est identifié métier par `Depth + occurrence du tour de Depth + Domain`. Le même `(Depth + Domain)` dans un tour ultérieur ouvre un nouveau bassin.
- **Blueprint :** aucun nouveau slot ; l’occurrence reste interne à Taxonomy.
- **Modules :** 03, frontière 02.

## DEC-096 — Un Subdomain par occurrence de bassin
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** chaque occurrence de bassin possède exactement un Subdomain officiel ; jamais plusieurs Subdomains successifs dans la même occurrence.
- **Modules :** 03.

## DEC-097 — Création atomique Subdomain + SubjectBank
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** Gemini crée le Subdomain et sa SubjectBank dans un même travail. Un candidat ne permettant aucun Subject conforme est abandonné dans le même appel et n’est jamais persisté seul.
- **Modules :** 03.

## DEC-098 — SubjectBank max 50 sans remplissage artificiel
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** maximum 50 Subjects PASS ; 50 n’est jamais obligatoire ; aucun Subject non conforme ne peut être ajouté pour remplir les slots.
- **Modules :** 03.

## DEC-099 — Mémoire Subjects PASS/FAIL
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** Subject PASS → SubjectBank persistante. Subject FAIL → mémoire éphémère limitée à l’appel courant et non persistée.
- **Modules :** 03.

## DEC-100 — Préparation équilibrée des Subject lots
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** les Subjects sont préparés avec le minimum d’appels et une répartition équilibrée ; capacité technique cible actuelle 10 Subjects/appel ; écart entre lots ≤ 1.
- **Modules :** 03.

## DEC-101 — ValidationDominantIdeas utilisé par Gemini
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `04_ValidationDominantIdeas` possède les règles du mécanisme de création/contrôle des Dominant Ideas utilisé par Gemini dans le même travail intellectuel. Il ne lit ni n’écrit directement le Blueprint.
- **Modules :** 03, 04.

## DEC-102 — Exploitabilité d’un Subject et plafond Ideas
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** une préparation réussie d’un Subject accepté produit 1 à 5 Dominant Ideas PASS. `0 PASS` est une anomalie de préparation ; 5 n’est pas obligatoire.
- **Modules :** 03, 04.

## DEC-103 — Anti-doublon Dominant Ideas contextualisé
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** l’identité anti-doublon PASS est `Depth + Domain + Subdomain + Subject + Dominant Idea`, comparée comme direction intellectuelle contextualisée et jamais comme mot isolé.
- **Modules :** 03.

## DEC-104 — LOOKBACK-2 cyclique
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** Subdomains et Dominant Ideas utilisent les deux Depths réellement précédents du même Domain ; la fenêtre traverse `Depth 10 → nouveau Depth 2` sans remise à zéro.
- **Modules :** 03.

## DEC-105 — FAIL Bank Dominant Ideas persistante
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** Dominant Idea FAIL → FAIL Bank persistante, jamais IdeaSlot exploitable, mémoire active dans le Depth courant + LOOKBACK-2.
- **Modules :** 03.

## DEC-106 — Consommation exacte immédiate
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `IdeaSlot sélectionné = dominant_idea_active écrit = IdeaSlot CONSUMED`. `CONSUMED` suit l’écriture Blueprint réussie et n’attend pas ReadyBank.
- **Modules :** 01, 03, 11 frontière.

## DEC-107 — DOMAIN_EXHAUSTED terminal avec garde TAX-003
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `DOMAIN_EXHAUSTED` n’est autorisé qu’après consommation de la dernière Idea du dernier Subject et vérification `remaining_subjects=0 AND remaining_ideas=0`. Sinon `TAX-003` bloque le signal et la reprise se fait depuis le curseur restant.
- **Modules :** 03, 02, Admin/Ops.
- **Précise :** DEC-082.

## DEC-108 — DEPTH_EXHAUSTED = fin d’un tour
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `DEPTH_EXHAUSTED(depth)` est produit lorsque les huit Domaines du tour courant sont épuisés. Il termine un tour, pas le besoin global du Depth.
- **Modules :** 03, 02.
- **Précise :** DEC-083.

## DEC-109 — Retry Gemini
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** 1 tentative initiale + 3 retries techniques maximum par opération. Les erreurs techniques ne produisent aucune progression métier.
- **Modules :** 03, Admin/Ops.

## DEC-110 — Blocage après échecs Gemini consécutifs
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** 3 opérations intellectuelles distinctes consécutives ayant chacune épuisé leurs 4 tentatives → `INTELLECTUAL_CREATION_UNAVAILABLE` et état opérationnel `BLOCKED`. Une réussite remet le compteur à zéro.
- **Modules :** 03, Admin/Ops.

## DEC-111 — Idempotence/persistance des épuisements KRP
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** KRP persiste `VISIBLE→ESTOMPÉ` et la fermeture de tour avant progression. Signal identique répété = `NO-OP`. Persistance : 1 tentative + 3 retries ; `KRP-002` ou `KRP-003` après échec non résolu.
- **Modules :** 02, 03 frontière, Admin/Ops.

## DEC-112 — Spécification Taxonomy v1.0 verrouillée
- **Version :** 1.0
- **Date :** 2026-08-16
- **Statut :** OFFICIAL
- **Décision :** `03_Taxonomy` atteint Architecture 100 % et Contrat 100 % avec toutes les rubriques obligatoires complètes.
- **Modules :** 03 et contrats entrants/sortants.

## DEC-113 — Spécification KernelBlueprint v2.0 verrouillée
- **Version :** 2.0
- **Date :** 2026-08-19
- **Statut :** OFFICIAL
- **Décision :** `01_KernelBlueprint` atteint Architecture 100 % et Contrat 100 % après reconstruction canonique complète. `KernelBlueprintFactory` crée une nouvelle enveloppe avant KRP ; `blueprint_id` est immuable ; Section 1 est write-once dans le chemin normal ; les 7 CognitiveSlots et leur couche de traduction sont des conteneurs permanents ; Banks/cycle data restent externes ; ReadyBank ne recycle jamais l’ancien Blueprint vers KRP ; `CURRENT_KERNEL_RECEIVED` autorise la création du Blueprint suivant.
- **Justification :** fermer la reconstruction de 01 sans réintroduire les formulations historiques incompatibles avec Factory, write-once et la séparation des responsabilités.
- **Modules :** 01 et frontières 02,03,05,06,08,10,11.
- **Décision remplacée :** AUCUNE — consolidation/verrouillage du contrat actif.
- **Décision remplaçante :** AUCUNE.

---

# Prochaine étape obligatoire

`01_KernelBlueprint` passe maintenant à :

```text
AUDIT-01-00
↓
audit du code réel contre specifications/01_KernelBlueprint.md v2.0
↓
implantation par micro-blocs
↓
validation terminale
```

`02_KernelRotationPlanner v3.3` reste fermé au travail tant que 01 n’a pas terminé implantation + validation.

---

# Index normalisé obligatoire

| Identifiant | Version | Date | Statut | Justification | Modules concernés | Décision remplacée | Décision remplaçante |
|---|---|---|---|---|---|---|---|
| DEC-082 | antérieure | antérieure | OFFICIAL | Taxonomy est l’autorité réelle de fin de matière d’un Domain ; signal prospectif | 02,03 | AUCUNE | précisée par DEC-107 |
| DEC-083 | antérieure | antérieure | OFFICIAL | Taxonomy est l’autorité réelle de fin d’un tour de Depth | 02,03 | AUCUNE | précisée par DEC-108 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer information d’épuisement et déclenchement du prochain Blueprint | 02,03,11 | AUCUNE | AUCUNE |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal AVAILABLE | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | OFFICIAL | Le canal physique peut varier sans changer la sémantique inter-module | 02,03 | AUCUNE | AUCUNE |
| DEC-088 | antérieure | antérieure | SUPERSEDED | L’ancienne suppression de cycle_target/cycle_completed empêchait les besoins globaux | 02,03 | AUCUNE | DEC-094 |
| DEC-089 | antérieure | antérieure | REJECTED | SHORTFALL créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | DepthProductionState créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception doit être idempotente | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | OFFICIAL | Séparer fin intellectuelle d’un tour et besoin quantitatif global | 02,03 | DEC-088 | AUCUNE |
| DEC-095 | 1.0 | 2026-08-16 | OFFICIAL | Distinguer les bassins lors des retours d’un même Depth+Domain | 03,02 frontière | AUCUNE | AUCUNE |
| DEC-096 | 1.0 | 2026-08-16 | OFFICIAL | Un seul Subdomain exploité par occurrence | 03 | AUCUNE | AUCUNE |
| DEC-097 | 1.0 | 2026-08-16 | OFFICIAL | Subdomain valide seulement avec SubjectBank viable | 03 | AUCUNE | AUCUNE |
| DEC-098 | 1.0 | 2026-08-16 | OFFICIAL | 50 est un plafond, pas un quota | 03 | AUCUNE | AUCUNE |
| DEC-099 | 1.0 | 2026-08-16 | OFFICIAL | PASS persistant, FAIL Subject éphémère | 03 | AUCUNE | AUCUNE |
| DEC-100 | 1.0 | 2026-08-16 | OFFICIAL | Lots équilibrés avec minimum d’appels | 03 | AUCUNE | AUCUNE |
| DEC-101 | 1.0 | 2026-08-16 | OFFICIAL | VDI fournit les règles à Gemini, pas une deuxième passe autonome | 03,04 | anciennes formulations VDI moteur autonome | AUCUNE |
| DEC-102 | 1.0 | 2026-08-16 | OFFICIAL | Subject préparé = 1..5 Ideas PASS ; 0 = anomalie | 03,04 | anciennes règles 0..5 normal | AUCUNE |
| DEC-103 | 1.0 | 2026-08-16 | OFFICIAL | Une DI est une direction contextualisée | 03 | AUCUNE | AUCUNE |
| DEC-104 | 1.0 | 2026-08-16 | OFFICIAL | LOOKBACK traverse 10→2 | 03 | AUCUNE | AUCUNE |
| DEC-105 | 1.0 | 2026-08-16 | OFFICIAL | FAIL DI persistante pendant sa fenêtre | 03 | AUCUNE | AUCUNE |
| DEC-106 | 1.0 | 2026-08-16 | OFFICIAL | Idea sélectionnée = écrite = consommée | 01,03,11 frontière | ancienne consommation tardive | AUCUNE |
| DEC-107 | 1.0 | 2026-08-16 | OFFICIAL | Empêcher DOMAIN_EXHAUSTED avec matière restante | 02,03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-108 | 1.0 | 2026-08-16 | OFFICIAL | DEPTH_EXHAUSTED ferme un tour, pas le besoin global | 02,03 | ancienne sémantique fin définitive | AUCUNE |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Retry Gemini sans effet métier | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | Échecs consécutifs → indisponibilité opérationnelle visible | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | OFFICIAL | Persister/idempotenter les transitions d’épuisement | 02,03 frontière,Admin/Ops | AUCUNE | AUCUNE |
| DEC-112 | 1.0 | 2026-08-16 | OFFICIAL | Taxonomy remplit toutes les rubriques obligatoires | 03 | AUCUNE | AUCUNE |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 consolide la frontière Factory, write-once, enveloppe permanente et prochain Blueprint après ReadyBank | 01 + frontières | AUCUNE | AUCUNE |