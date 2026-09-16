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
| DEC-082 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DOMAIN_EXHAUSTED` par Taxonomy comme mécanisme; v4.0 sépare le fait Taxonomy du moteur interne KRP | 02,03 | AUCUNE | DEC-119/120 |
| DEC-083 | antérieure | antérieure | SUPERSEDED | Ancienne ownership de `DEPTH_EXHAUSTED` par Taxonomy | 02,03 | AUCUNE | DEC-119/120 |
| DEC-084 | antérieure | antérieure | OFFICIAL | Séparer progression Taxonomy et rotation KRP | 02,03 | AUCUNE | AUCUNE |
| DEC-085 | antérieure | antérieure | OFFICIAL | Distinguer information terminale Taxonomy et déclenchement lifecycle du prochain Blueprint | 02,03,11 | AUCUNE | précisée par DEC-119/120 |
| DEC-086 | antérieure | antérieure | REJECTED | Le fonctionnement normal ne nécessite aucun signal inverse `AVAILABLE` | 02,03 | AUCUNE | AUCUNE |
| DEC-087 | antérieure | antérieure | OFFICIAL | Le transport physique du fait Taxonomy peut varier; la sémantique métier et l’ownership restent contractuels | 02,03 | AUCUNE | précisée par DEC-119/120 |
| DEC-088 | antérieure | antérieure | SUPERSEDED | Ancienne suppression de `cycle_target/cycle_completed` du chemin KRP | 02,03 | AUCUNE | DEC-094 puis DEC-119 |
| DEC-089 | antérieure | antérieure | REJECTED | `SHORTFALL` créait un état dérivé inutile | 02 | AUCUNE | AUCUNE |
| DEC-090 | antérieure | antérieure | REJECTED | `DepthProductionState` créait une seconde source de vérité | 02 | AUCUNE | AUCUNE |
| DEC-093 | antérieure | antérieure | OFFICIAL | La réception d’un noyau est idempotente et n’autorise pas de double effet lifecycle | 02,11 | AUCUNE | AUCUNE |
| DEC-094 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne double autorité où Taxonomy produisait `DEPTH_EXHAUSTED`; v4.0 place `DEPTH_EXHAUSTED + DepthNeedMatrix` dans KRP | 02,03 | DEC-088 | DEC-119 |
| DEC-095 | 1.0 | 2026-08-16 | OFFICIAL | Un bassin Taxonomy est identifié par Depth + occurrence + Domain; occurrence interne Taxonomy, aucun slot Blueprint | 03,02 frontière | AUCUNE | précisée par DEC-120 |
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
| DEC-107 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne formulation où Taxonomy produisait `DOMAIN_EXHAUSTED`; la garde de contenu devient garde du fait terminal Taxonomy | 02,03,Admin/Ops | AUCUNE | DEC-119/120 |
| DEC-108 | 1.0 | 2026-08-16 | SUPERSEDED | Ancienne sémantique `DEPTH_EXHAUSTED(depth)` produit par Taxonomy | 02,03 | ancienne fin définitive | DEC-119/120 |
| DEC-109 | 1.0 | 2026-08-16 | OFFICIAL | Gemini : 1 tentative + 3 retries techniques sans effet métier sur échec | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-110 | 1.0 | 2026-08-16 | OFFICIAL | 3 opérations intellectuelles consécutives ayant épuisé leurs retries → `INTELLECTUAL_CREATION_UNAVAILABLE` / `BLOCKED` | 03,Admin/Ops | AUCUNE | AUCUNE |
| DEC-111 | 1.0 | 2026-08-16 | OFFICIAL | KRP persiste ses transitions d’épuisement et fermeture avant progression; répétition = NO-OP; politique 1+3 retries | 02,03 frontière,Admin/Ops | AUCUNE | précisée par DEC-119 |
| DEC-112 | 1.0 | 2026-08-16 | SUPERSEDED | Verrouillage Taxonomy v1.0; frontière KRP incorrecte sur ownership des deux moteurs d’épuisement | 03 + frontières | AUCUNE | DEC-120 |
| DEC-113 | 2.0 | 2026-08-19 | OFFICIAL | KernelBlueprint v2.0 : Factory avant KRP, `blueprint_id` immuable, Section 1 write-once, Banks/cycle externes, ancien Blueprint jamais recyclé vers KRP | 01 + frontières | AUCUNE | AUCUNE |
| DEC-114 | 3.3 | 2026-08-20 | SUPERSEDED | KRP v3.3 avait le bon cadran Domain mais attribuait encore `DOMAIN_EXHAUSTED/DEPTH_EXHAUSTED` à Taxonomy | 02 + frontières | v3.2 + ALIGN-02 | DEC-119 |
| DEC-115 | 3.4 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : KRP devait lire une réalité Taxonomy persistée | 02 + frontières | AUCUNE | DEC-119 |
| DEC-116 | 3.5 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : frontière signal/persistance incorrecte | 02 + frontières | AUCUNE | DEC-119 |
| DEC-117 | 3.6 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : séquentialité ajoutée avec mécanique Domain incomplète | 02 + frontières | AUCUNE | DEC-119 |
| DEC-118 | 3.7 | 2026-08-23 | REJECTED | Révision documentaire non autorisée : conservait à tort le même Domain tant qu’il restait `VISIBLE` | 02 + frontières | AUCUNE | DEC-119 |
| DEC-119 | 4.0 | 2026-08-24 | OFFICIAL | KRP v4.0 : cadran Domain restauré; `DOMAIN_EXHAUSTED` et `DEPTH_EXHAUSTED` sont deux moteurs internes KRP; `DEPTH_EXHAUSTED` contient `DepthNeedMatrix` et la rotation des Depths | 02 + frontière 03 | DEC-114 sur ownership + rejet DEC-115..118 | AUCUNE |
| DEC-120 | 1.1 | 2026-08-24 | OFFICIAL | Taxonomy v1.1 : conserve ses Banks et sa consommation exacte; transmet seulement le fait terminal « dernière Dominant Idea du dernier Subject de ce Domain utilisée »; aucun moteur `DOMAIN_EXHAUSTED`, aucun `DEPTH_EXHAUSTED`, aucune DepthNeedMatrix ni rotation globale dans Taxonomy | 03 + frontière 02 | DEC-112 + DEC-107/108 sur frontière | AUCUNE |
| DEC-121 | 2.2 | 2026-08-29 | OFFICIAL | `kernel_code` se construit progressivement dans le même KernelBlueprint : écritures KRP → projection `DD-DO`; écritures Taxonomy → projection `SUB-SUJ-IDE`; QuestionIntent/KernelCodeEngine alloue uniquement `VVVV`, assemble et verrouille le code final. `VVVV` est un compteur base36 persistant, transactionnel, jamais recyclé et indépendant par bassin `Depth + Domain`. Phase1 remplit ensuite les sept CognitiveSlots sans modifier `kernel_code`; l’état cognitif joueur demeure externe | 01,02,03,05 + frontière 06/11 | formulations DEC-121 v2.0/v2.1 portant `question_code-COG-VAR` | DEC-122 |
| DEC-122 | 1.0 | 2026-08-29 | OFFICIAL | Un seul Blueprint canonique contient l’identité, les 7 CognitiveSlots source et leurs traductions. Le canonique poursuit toutes les phases jusqu’à ReadyBank. Quarantine reçoit une copie complète avec chemins soupçonnés affichables en rouge; la copie corrigée reprend le pipeline de façon ciblée puis rejoint le canonique uniquement dans ReadyBank, qui remplace/corrige/remplit les slots ciblés ou vides sans toucher aux slots valides. L’état joueur `00n→11o` reste externe au Blueprint et autorise au maximum un cognitif par chacune des trois familles | 01,05,06,07,08,09,10,11 + Gameplay | anciennes formulations fragment Quarantine et `question_code-COG-VAR` | AUCUNE |
| DEC-125 | 1.1 | 2026-09-13 | OFFICIAL | Quarantaine conserve une copie complète non canonique liée au même `blueprint_id`; l’Admin peut modifier tout slot; rouge = `SUSPICION` ou `EMPTY`, vert = conforme jamais modifié, jaune = modifié manuellement jusqu’à ReadyBank. Le clic `Renvoie` place la copie dans une FIFO. À chaque `CURRENT_KERNEL_RECEIVED`, ReadyBank choisit exclusivement la plus ancienne copie prête ou KBP. Une copie reprend à Phase1, ne passe jamais par KBP/Rotation et ne compte jamais comme nouveau Blueprint. ReadyBank fusionne atomiquement par `blueprint_id + cognitive_type`. | 01,02,06,07,08,09,10,11 + Admin | complète DEC-122 | AUCUNE |
| DEC-126 | 1.1 | 2026-09-16 | OFFICIAL | L’anglais est la langue canonique de création intellectuelle : valeurs Domaine/Taxonomy puis question, choix, bonne réponse et SV des sept sources Phase1. Chaque langue cible est traduite indépendamment et directement depuis la même source anglaise; aucune traduction en chaîne. Phase2 produit les neuf langues obligatoires `fr, es, de, it, pt, ru, zh, ar, el`, sans langue facultative. `General` est un mode Shuffle gameplay, jamais un Domaine créateur. La Bible, les règles, les titres et l’Admin restent français; la langue joueur reste indépendante. Codes et identités techniques inchangés; données françaises existantes inventoriées sans conversion. | 00,02,03,05,06,07,08,09,10,11 + Gameplay/Admin | clauses Phase1 `source_language=fr` et valeurs intellectuelles françaises implicites | AUCUNE |
| DEC-127 | 1.0 | 2026-09-16 | OFFICIAL | Contrat terminal Phase2/ValidationPhase2 : identité par source_revision et translation_revision; états séparés; findings BLOCKING déterministes; admissibilité stricte des neuf cibles; retries 1+3 à 1/5/15 min; panne technique sans rouge; CONTENT_UNTRANSLATABLE décidé uniquement par ValidationPhase2; enveloppes internes et fournisseurs opaques; reprise Quarantaine sélective; publication ReadyBank atomique par CognitiveSlot et CURRENT_KERNEL_RECEIVED idempotent après sept issues terminales. | 08,09,10,11 + Admin/Gameplay | complète DEC-125/126 | AUCUNE |

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

## Décision

```text
Taxonomy
→ fait terminal de consommation
↓
KRP.DOMAIN_EXHAUSTED
→ Domain VISIBLE → ESTOMPÉ
↓ si dernier Domain actif
KRP.DEPTH_EXHAUSTED
→ DepthNeedMatrix
→ prochain Depth nécessaire
```

DomainRotation KRP avance au prochain Domain `VISIBLE` à chaque nouveau Blueprint.

DepthCycle :

```text
2 → 4 → 6 → 7 → 8 → 9 → 10 → 2 → ...
```

Depths dont `cycle_remaining = 0` sautés. HOLD seulement lorsque tous les besoins sont satisfaits.

---

# DEC-120 — Taxonomy = création/consommation + fait terminal seulement

- **Version :** 1.1
- **Date :** 2026-08-24
- **Statut :** **OFFICIAL**
- **Module propriétaire :** `03_Taxonomy`
- **Source canonique :** `specifications/03_Taxonomy.md` v1.1

## Décision centrale

Taxonomy conserve :

```text
Subdomain
SubjectBank
IdeaBanks
ValidationDominantIdeas pendant création
sélection exacte
écriture exacte
consommation exacte
curseurs et occurrences
```

Taxonomy ne possède plus :

```text
DOMAIN_EXHAUSTED moteur
DEPTH_EXHAUSTED moteur
DomainCycle KRP
VISIBLE / ESTOMPÉ
DepthNeedMatrix
cycle_target / cycle_completed / cycle_remaining
rotation Domain
rotation Depth
```

## Fait terminal

Après :

```text
dernière Dominant Idea
du dernier Subject
de l’occurrence active
↓
écriture Blueprint réussie
↓
même IdeaSlot CONSUMED
↓
remaining_subjects = 0
AND remaining_ideas = 0
```

Taxonomy transmet une seule fois le fait :

```text
« cette occurrence de Domain vient d’utiliser
sa dernière Dominant Idea exploitable »
```

KRP interprète ensuite ce fait dans son moteur interne `DOMAIN_EXHAUSTED`.

Taxonomy ne sait pas si ce Domain était le dernier Domain actif du Depth et ne transmet jamais `DEPTH_EXHAUSTED`.

## Retour du même Domain

KRP peut faire plusieurs rotations avant de réattribuer le même `depth + domain`.

Taxonomy :

```text
occurrence exploitable existe
→ reprend son curseur

ancienne occurrence entièrement consommée
→ ouvre une nouvelle occurrence
```

Taxonomy ne suppose jamais que le Blueprint immédiatement suivant appartient au même Domain.

---

# Sources canoniques actuelles

```text
01 → specifications/01_KernelBlueprint.md v2.0
02 → specifications/02_KernelRotationPlanner.md v4.0 / DEC-119
03 → specifications/03_Taxonomy.md v1.1 / DEC-120
```

---

# Ordre technique suivant

Les spécifications sont séparées et les implantations doivent le rester :

```text
RÉAUDIT-02-v4.0
→ implantation KRP seulement
→ validation KRP

PUIS

AUDIT-03-v1.1
→ implantation Taxonomy seulement
→ validation Taxonomy
```

Ne jamais demander à Replit d’implanter KRP et Taxonomy dans le même bloc.

# DEC-121 — Construction progressive, suffixe VVVV et anti-répétition joueur

Référence historique au verrouillage DEC-121 :

```text
05 → specifications/05_QuestionIntent.md v2.2 / DEC-121 + DEC-122
```

Référence active courante :

```text
05 → specifications/05_QuestionIntent.md v2.3 / DEC-125 + DEC-126
```

Le document historique `docs/architecture/05_QuestionIntent.md` est SUPERSEDED et retiré de l’arbre actif. Son historique demeure récupérable dans Git.


# DEC-122 — Blueprint complet, copie Quarantine et fusion ReadyBank

```text
01 → specifications/01_KernelBlueprint.md v2.1
06 → specifications/06_Phase1.md v0.1
07 → specifications/07_ValidationPhase1.md v0.1
08 → specifications/08_Phase2.md v0.1
09 → specifications/09_ValidationPhase2.md v0.1
10 → specifications/10_Quarantine.md v0.1
11 → specifications/11_ReadyBank.md v0.1
```

Les versions 0.1 verrouillent uniquement les décisions DEC-122 et leurs frontières. Elles ne déclarent pas les modules 06 à 11 entièrement spécifiés, implantés ou validés.


# DEC-125 — Quarantaine, FIFO et direction terminale

- **Version :** 1.1
- **Date :** 2026-09-13
- **Statut :** **OFFICIAL**
- **Modules :** 01, 02, 06, 07, 08, 09, 10, 11 et interface Admin

## Direction exclusive

```text
CURRENT_KERNEL_RECEIVED
→ file Quarantaine READY non vide
   → GO vers la plus ancienne copie
   → reprise à Phase1 avec le même blueprint_id
   → aucun GO KBP
   → aucune Rotation
   → aucun comptage de nouveau Blueprint

→ file Quarantaine READY vide
   → GO KBP
   → KBP crée le nouveau Blueprint canonique
   → KBP transmet blueprint_id à Rotation
   → Rotation effectue alors sa sélection et sa progression normales
```

Chaque événement prend une seule direction durable et idempotente. Plusieurs clics `Renvoie` s’accumulent et sont servis un à un selon leur ordre accepté.

## Copie et réconciliation

La copie Quarantaine est complète, persistée séparément et conserve le `blueprint_id` du canonique. Elle ne devient jamais canonique. Les slots suspects sont copiés avant d’être vidés dans le canonique. Tous les slots de la copie sont modifiables par l’Admin. Toute modification invalide immédiatement l’ancien PASS et rend le slot jaune jusqu’à sa décision ReadyBank.

La copie complète reprend toujours à Phase1. Chaque slot traverse seulement les opérations nécessaires à son état; les slots admissibles continuent même si d’autres échouent. ReadyBank fusionne atomiquement les seuls slots admissibles par `blueprint_id + cognitive_type`; les autres positions restent vides. Une copie peut revenir plusieurs fois en Quarantaine. L’Admin peut supprimer une copie non en cours; aucune ancienne version n’est conservée.


## Complément DEC-125 — indice de reprise jaune

Chaque slot jaune porte également un indice de reprise persistant lié à sa révision manuelle et à la version de copie. Cet indice suit sa progression de Phase1 jusqu’à ReadyBank, permet la reprise après interruption et interdit qu’une ancienne reprise termine ou écrase une correction plus récente.

ReadyBank retire le jaune uniquement après sa décision terminale sur la révision exacte identifiée par cet indice.

# DEC-126 — Langue intellectuelle canonique anglaise

- **Version :** 1.1
- **Date :** 2026-09-16
- **Statut :** **OFFICIAL**
- **Modules :** 00, 02, 03, 05, 06, 07, 08, 09, 10, 11, Admin et Gameplay

## Domaines intellectuels créateurs

| Identité stable | Valeur intellectuelle anglaise |
|---|---|
| GEO | Geography |
| HIS | History |
| FAU | Wildlife |
| ART | Art |
| SPO | Sports |
| CIN | Cinema |
| CUI | Cuisine |
| SCI | Science |

Les codes techniques restent les identités stables. Les libellés anglais ne
deviennent jamais des clés de rotation.

`General` n’est ni une neuvième ligne de cette table ni un Domaine de création.
Il appartient exclusivement au gameplay comme mode Shuffle : il sélectionne et
mélange des questions issues des huit Domaines créateurs. Aucun Blueprint,
réservoir Taxonomy ou contenu Phase1 n’est créé sous `General`.

## Trois couches linguistiques

```text
Architecture, Bible, règles, titres et Admin → français
Valeurs intellectuelles et sept sources Phase1 → anglais
Interface et contenu joué → langue choisie par le joueur
```

Phase2 reçoit `source_language = en` et produit exactement neuf langues
obligatoires, sans langue facultative :

```text
fr, es, de, it, pt, ru, zh, ar, el
```

Chaque langue cible est traduite indépendamment et directement depuis la même
source anglaise. Une traduction cible ne peut jamais devenir la source d’une
autre traduction.

Cette décision ne convertit aucune donnée persistante. Les données françaises
existantes sont seulement inventoriées pour un travail technique ultérieur.

# DEC-127 — Contrat terminal Phase2 et ValidationPhase2

- **Version :** 1.0
- **Date :** 2026-09-16
- **Statut :** **OFFICIAL**
- **Décision 7 révisée :** **APPROUVÉE EXPLICITEMENT ET INSCRITE COMME AUTORITÉ**
- **Modules :** 08, 09, 10, 11, Admin et Gameplay

DEC-127 verrouille le contrat fonctionnel complet de traduction et validation
linguistique décrit par `08_Phase2.md v1.0` et
`09_ValidationPhase2.md v1.0`.

Il verrouille notamment :

- l’identité persistante d’une traduction et ses deux révisions distinctes;
- les machines d’état séparées de Phase2 et ValidationPhase2;
- la reprise jaune Quarantaine et le refus des claims périmés;
- les findings `BLOCKING` déterministes et leur preuve obligatoire;
- l’admissibilité stricte de chacune des neuf langues et du CognitiveSlot;
- quatre tentatives par cycle, aux planchers `1 / 5 / 15 minutes`;
- la réouverture idempotente par événement de résolution;
- la distinction absolue entre défaillance technique et défaut du contenu;
- l’autorité exclusive de ValidationPhase2 sur `CONTENT_UNTRANSLATABLE`;
- la séparation entre enveloppe interne et échanges fournisseurs opaques;
- l’attribution atomique de `translation_revision`;
- la copie Quarantaine complète avec reprise sélective;
- la publication et le retrait atomiques d’un CognitiveSlot;
- les trois issues terminales et l’idempotence de
  `CURRENT_KERNEL_RECEIVED`.

L’autorité DEC-127 inclut expressément le maintien en Gameplay de tout
CognitiveSlot `PUBLISHED`, courant et non modifié, même si d’autres slots sont
bloqués. Toute modification Admin réelle d’un slot publié invalide
atomiquement son manifeste, retire uniquement ce slot dans toutes les langues,
crée la révision jaune et persiste son indice de reprise. Une cible modifiée ne
périme pas les huit autres, mais le slot complet demeure non publié jusqu’au
retour de neuf traductions admissibles.

ReadyBank vérifie atomiquement `blueprint_id + cognitive_type +
source_revision + les neuf translation_revision`. Tout retour périmé est un
NO-OP. Un slot publié, courant et non modifié pendant un retour Quarantaine
reste disponible sans republication, révision ou revalidation. La republication
du slot multilingue complet exige de nouveau la source anglaise et les neuf
traductions courantes admissibles.

Les trois issues terminales, les sept issues avant
`CURRENT_KERNEL_RECEIVED`, l’idempotence par `cause_reference` et la priorité
Quarantaine READY sur KBP sont obligatoires.

DEC-127 ne choisit aucun fournisseur, ne modifie aucun code, schéma ou donnée et
n’autorise aucune conversion des contenus historiques.
