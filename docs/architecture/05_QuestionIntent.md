# STRATEGYBUZZER — 05_QUESTIONINTENT (CONTRAT EN CONSTRUCTION)

**Version :** 0.3 — MISSION VERROUILLÉE
**Date :** 11 août 2026
**Spécification écrite :** EN COURS — Mission verrouillée ; identité du noyau en cours de définition
**Statut :** UNSPECIFIED / UNDER_CONSTRUCTION
**Implantation :** INTERDITE tant que ce contrat n'est pas verrouillé (RÈGLE DU VIDE)
**Gel :** `BLOCKED_AT_QUESTION_INTENT_CONTRACT` — aucun rotate réel, aucun encodage, aucune création Gemini à cette frontière

---

# 0. Méthode imposée (verrou utilisateur — 11 août 2026)

Le contrat métier se définit D'ABORD ; les colonnes ne viennent QU'ENSUITE.

Question directrice unique (point de départ officiel de la Mission) :

```text
Quelle transformation métier indispensable doit se produire entre
un territoire Taxonomy validé et la création des 7 cognitifs de Phase 1 ?
```

et JAMAIS :

```text
Que devons-nous mettre dans les colonnes existantes ?
```

Séquence obligatoire avant tout code :

```text
architecture
→ contrat métier
→ mécanismes
→ spécification
→ Architecture Register
→ audit du code existant
→ implantation QuestionIntent
→ ensuite seulement premier rotate réel
```

Interdits explicites pendant la construction :
- valider les mappings legacy « champ par champ » ;
- chercher d'autres valeurs pour remplir les colonnes existantes ;
- modifier QuestionIntent pour rendre le pipeline exécutable ;
- lancer le premier noyau réel ;
- tout contournement ;
- toute équivalence métier déduite du schéma DB.

Le fait qu'une colonne soit NOT NULL, exigée par un validator ou présente
dans l'ancien code ne lui confère AUCUNE autorité métier.

---

# 1. Position dans le flow — VERROUILLÉE (fait certain)

```text
CRÉATION DU KERNELBLUEPRINT (avant KRP — CORRECTION SUPERSEDED actuelle)
↓
KernelRotationPlanner
  → CHOISIT Depth + Domaine
↓
Taxonomy ↕ ValidationDominantIdeas
  → DÉTERMINE le territoire précis : Sous-domaine + Sujet + Idée Dominante
  → VALIDE l'Idée Dominante
↓
════════════════════════════════════════════════════
FRONTIÈRE ENTRANTE QUESTIONINTENT — CONNUE
════════════════════════════════════════════════════
↓
QUESTIONINTENT
  → CODE / VERROUILLE l'identité unique et persistante du noyau complet
  → ne modifie aucune composante intellectuelle
  → ne détermine aucun traitement cognitif de Phase 1
↓
════════════════════════════════════════════════════
FRONTIÈRE SORTANTE VERS PHASE 1 — IDENTITÉ À DÉFINIR
════════════════════════════════════════════════════
↓
Phase 1
  → EXPLOITE ce noyau à travers les 7 cognitifs
    (recognition / reasoning / trap / true / false…)
  — NON TRAITÉE MAINTENANT
```

**Correction documentaire obligatoire :** des documents antérieurs indiquent
encore « KernelRotationPlanner crée KernelBlueprint ». Cette formulation est
SUPERSEDED. Le Blueprint est créé AVANT KRP. KRP ne doit jamais redevenir
son créateur. Ce n'est PAS un point ouvert pour la conception de QuestionIntent.

Le pipeline reste bloqué à cette frontière jusqu'au verrouillage du présent
contrat. Le schedule `questions:kernel:process-outbox` (préexistant) peut
rester actif : il est no-op tant qu'aucun noyau réel n'est engagé.

---

# 2. Entrées certaines à l'entrée de QuestionIntent

## 2.1 Ce que l'amont a produit (fait certain)

Au moment où QuestionIntent est appelé, le pipeline a déjà déterminé :

```text
depth               ← KRP a inscrit (via KernelRotationPlanner)
domain              ← KRP a inscrit
subdomain_active    ← Taxonomy a sélectionné
subject_active      ← Taxonomy a sélectionné
dominant_idea_active ← Taxonomy a sélectionné, ValidationDominantIdeas a validé
```

Cette hiérarchie est complète et définitive avant que QuestionIntent intervienne.

## 2.2 Ce que QuestionIntent n'a PAS le droit de faire sur ces entrées

**Hérité de KernelRotationPlanner — interdictions absolues :**

```text
✗ changer depth
✗ changer domain
✗ décider du prochain domaine
✗ décider de la rotation
✗ décider du DepthCycle
✗ connaître les besoins quantitatifs
✗ connaître la disponibilité des réservoirs
```

QuestionIntent consomme la position intellectuelle déjà choisie.
Il n'en choisit aucune partie.

**Hérité de Taxonomy — interdictions absolues :**

```text
✗ créer un sous-domaine
✗ créer un sujet
✗ créer une idée dominante
✗ choisir une autre idée que dominant_idea_active
✗ faire progresser le curseur Taxonomy
✗ appeler confirmConsumed()
✗ connaître les 50 sujets, les 5 idées candidates
✗ connaître l'historique complet Taxonomy
✗ connaître les curseurs, la mémoire Gemini, les FAIL candidates, les réservoirs
```

Tout cela reste chez Taxonomy.

**Hérité de ValidationDominantIdeas — interdictions absolues :**

```text
✗ re-valider le doublon
✗ re-valider la synonymie
✗ re-valider la collision conceptuelle
✗ re-valider la diversité du set
✗ re-valider la cohérence Domaine/Sous-domaine/Sujet
✗ re-valider la dominance
✗ re-valider la Depth conformity
```

Lorsque QuestionIntent reçoit `dominant_idea_active`, il la considère comme
l'Idée Dominante déjà retenue et validée par l'amont. Refaire ces contrôles
constituerait une deuxième validation concurrente — interdit.

## 2.3 Entrées supplémentaires éventuelles — OPEN

Toute entrée au-delà des 5 données du §2.1 relève de la rubrique §8.5
et doit être décidée explicitement — jamais supposée.

---

# 3. État intellectuel exact à l'entrée de QuestionIntent

C'est le point central de la conception.

```text
KERNELBLUEPRINT CANONIQUE
│
├── depth
├── domain
├── subdomain_active
├── subject_active
├── dominant_idea_active
│
└── identité / intention QuestionIntent :
    → ENCORE À DÉTERMINER
```

Le noyau possède déjà son **territoire intellectuel complet**.

Il ne possède pas encore nécessairement sa **directive de création pour Phase 1**.

C'est précisément là que QuestionIntent existe : entre le territoire validé
et la création des 7 cognitifs.

Son problème métier est nécessairement lié à la transformation :

```text
« de quoi parle exactement ce noyau ? »
         ↓
quelque chose permettant à Phase 1 de savoir :
« qu'est-ce que je dois créer à partir de ce noyau précis ? »
```

---

# 4. Mission minimale héritée (sans la définir encore)

QuestionIntent est situé entre :

```text
TERRITOIRE INTELLECTUEL VALIDÉ
         ↓
    QuestionIntent
         ↓
CRÉATION COGNITIVE Phase 1
```

L'historique le décrit comme un module qui prépare l'identité et l'intention
utilisées par Phase 1, sans recréer ni revalider Taxonomy.

**Attention :** la forme de cette intention n'est pas encore définie.
La Mission officielle doit être construite à partir de la question directrice
du §0, jamais déduite du legacy.

---

# 4.1 Règle canonique — le noyau principal ne quitte jamais le flow (verrouillé)

```text
NOYAU CANONIQUE PRINCIPAL
↓
reste TOUJOURS dans le flow principal
↓
Phase 1 → Validation Phase 1 → Phase 2 → Validation Phase 2 → ReadyBank

En cas de problème :

NOYAU CANONIQUE PRINCIPAL            ← reste dans le flow
↓
         └────→  PRINT / COPIE DE TRAVAIL
                 ↓
              Quarantine
                 ↓
              correction
                 ↓
              retour d'information
                 ↓
              réintégration dans le noyau canonique
```

**Règle précise (verrouillée — source : utilisateur, 11 août 2026) :**

- Le noyau principal ne quitte jamais le flow.
- Quarantine ne reçoit jamais le noyau canonique lui-même.
- Quarantine travaille sur un print / une copie de travail.
- L'identité du noyau canonique reste attachée au noyau principal.
- Les prints de Quarantine doivent conserver la référence permettant de revenir
  au bon noyau canonique.
- ⛔ Il ne faut plus parler de « sortie temporaire du flow ».

**Formulation officielle pour QuestionIntent :**

> L'identité produite par QuestionIntent identifie le noyau canonique principal
> pendant tout son parcours dans le flow. Les copies de travail éventuellement
> envoyées vers Quarantine conservent une référence à cette identité, mais le
> noyau canonique principal ne quitte jamais le flow.

Cette règle est un impératif de conception pour §9.19 (Identité du noyau) et
§9.20 (Relation avec Phase 1) : l'identité produite ici doit être stable,
référençable et ne jamais être confondue avec un print de travail.

---

# 5. Verrous négatifs des mappings (utilisateur — 11 août 2026)

Les mappings suivants sont **UNSPECIFIED et NON AUTORISÉS** :

```text
angle_large     = dominant_idea      ← NON AUTORISÉ
micro_angle     = dominant_idea      ← NON AUTORISÉ
answer_target   = dominant_idea      ← NON AUTORISÉ
concept_family  = sub_domain         ← NON AUTORISÉ
intent_key      = BP:{blueprint_id}  ← NON AUTORISÉ
semantic_key    = BP:{blueprint_id}  ← NON AUTORISÉ
language_source = en                 ← NON AUTORISÉ
```

L'encodeur qui les implémentait a été RETIRÉ (audit RÈGLE DU VIDE,
11 août 2026). Zéro ligne n'a jamais été écrite avec ces mappings.

Également sans autorité :

```text
question_intents (table)    ← relevé factuel §11, jamais source de contrat
KernelFrameValidator        ← audité APRÈS le contrat, jamais utilisé pour le produire
frame_status                ← idem
KernelFrameBuilder legacy   ← idem
```

---

# 6. Traitement explicite des champs d'identité legacy

## 6.1 kernel_code — cas particulier à auditer précisément

**Concept :** dans les spécifications amont historiques, `kernel_code` existe
comme identité du noyau et arrive vide à QuestionIntent. Plusieurs versions
attribuaient son écriture à QuestionIntent.

**Ce qui n'est PAS encore permis de déduire :**

```text
son format
ses composants
sa longueur
son algorithme
sa persistance
son rôle d'unicité
sa relation avec blueprint_reference
sa relation avec semantic_key
sa relation avec intent
```

**Procédure d'audit pendant 05_QuestionIntent :**

Si la conclusion est `kernel_code reste un slot officiel` :
→ définir sa signification métier / son propriétaire / sa construction / son consommateur

Si la conclusion est qu'il doit changer structurellement :
→ versionner aussi le contrat KernelBlueprint concerné
→ inscrire la décision à l'Architecture Register

## 6.2 ks_hash et kld_hash — legacy à justifier

Ces champs proviennent de l'ère KLD / KEY_STRUCTURE, modules maintenant
SUPERSEDED. Leur existence physique ne leur donne aucune légitimité.

Ils entrent dans 05_QuestionIntent avec le statut :

```text
ks_hash  → LEGACY À JUSTIFIER
kld_hash → LEGACY À JUSTIFIER
```

La question correcte est :

```text
Existe-t-il encore aujourd'hui une information métier distincte
que ks_hash ou kld_hash représente ?

  Réponse non → suppression (migration destructive sur ordre explicite)
  Réponse oui → nouvelle justification métier explicite
               (jamais « conservation parce que la colonne existe »)
```

---

# 7. Hors périmètre de 05_QuestionIntent

Même si ces problèmes sont découverts pendant la spécification, ils ne seront
PAS résolus dans ce document :

**GELÉ jusqu'aux Phases :**
```text
frame_en :
  kld_result
  ks_result
  ks_hash
  key_structure
  kld
→ GELÉ / FUTURE PHASE
```

**Hors périmètre — sera spécifié après 05_QuestionIntent :**
```text
retry Phase 1
retry Phase 2
Quarantine
ReadyBank acceptance
confirmConsumed
CURRENT_KERNEL_RECEIVED semantics
re-rotation après échec aval
```

Les BLOCKERs concernant Phases et ReadyBank restent documentés mais ne seront
pas absorbés par QuestionIntent. Une seule spécification à la fois.

---

# 8. Acquis antérieurs à re-ratifier (héritage — aucune reconduction d'office)

Contrat historique de QuestionIntent (verrouillé 2026-06-16/19, ère KLD/KS) :

| Acquis historique | Statut dans ce contrat |
|---|---|
| PUR ENCODEUR — zéro contrôle métier, ne valide/compare/filtre/bloque rien | À RE-RATIFIER |
| Pas de QUESTIONINTENT_RULESET (cette couche ne doit pas exister) | À RE-RATIFIER |
| Facilitateur passif : « crée une identité exploitable », ne l'exploite jamais | À RE-RATIFIER |
| Encode UNE entité complète (pas de fan-out, pas de puces) | À RE-RATIFIER |
| Sorties historiques : `question_intent_id`, `ks_hash`, `kernel_print`, `status=CREATION_READY` | À RE-STATUER — datent de l'ère KLD/KS SUPERSEDED |
| Charge historique : `kld_hash`, `knowledge_frequency`, subjects[1..50]×ideas[1..5] | À RE-STATUER — idem |

Aucune ligne de ce tableau n'est une vérité actuelle : chacune doit être
confirmée, amendée ou abandonnée dans la spécification finale.

---

# 9. Les 21 rubriques du contrat — statut et questions à trancher

Chaque rubrique reste **OPEN** tant qu'elle n'est pas verrouillée par
l'utilisateur. Les 14 points de décision A-N (reconstruction officielle)
sont alignés sur les rubriques correspondantes.

## 9.1 Mission — VERROUILLÉE (utilisateur — 11 août 2026)

QuestionIntent reçoit le KernelBlueprint dont le territoire intellectuel a été
entièrement déterminé et validé par les moteurs précédents, puis construit et
verrouille l'identité unique et persistante du noyau courant afin de permettre
son identification et sa traçabilité pendant tout son cycle de production, de
correction et de stockage, ainsi que son identification efficace lors de sa
consommation ultérieure par le gameplay. QuestionIntent ne modifie aucune
composante intellectuelle du noyau et ne détermine aucun traitement cognitif
de Phase 1.

## 9.2 Position — VERROUILLÉE
Voir §1. Entre Taxonomy↕ValidationDominantIdeas et Phase 1.

## 9.3 Responsabilités — PARTIELLEMENT VERROUILLÉE [→ Point A/C]

Acquis de la Mission (§9.1) :

```text
✓ construit l'identité unique et persistante du noyau
✓ verrouille cette identité (immuable après création — conditions à définir)
✓ permet l'identification du noyau pendant tout son cycle
✓ permet la traçabilité (production, correction, stockage)
✓ permet l'identification en gameplay (consommation)
✗ ne modifie aucune composante intellectuelle du noyau
✗ ne détermine aucun traitement cognitif de Phase 1
```

Le périmètre « pur encodeur / facilitateur passif » historique est compatible
avec cette Mission — à re-ratifier formellement (§8).

Restant OPEN : liste exhaustive des verbes autorisés au-delà de
« construire » et « verrouiller ».

## 9.4 Interdictions — PARTIELLEMENT VERROUILLÉE [→ §2.2]

Héritées des modules amont (§2.2) + acquis de la Mission (§9.1) :

```text
✗ ne choisit pas le traitement cognitif de Phase 1 :
    ni recognition
    ni reasoning
    ni trap
    ni true / false
    → c'est exclusivement Phase 1 qui exploite les 7 cognitifs

✗ ne modifie pas depth, domain, subdomain_active, subject_active,
    dominant_idea_active  (hérité §2.2 — KRP + Taxonomy)

✗ ne refait pas la validation de l'Idée Dominante
    (doublon / synonyme / collision)  (hérité §2.2 — VDI)
```

Restant OPEN : interdictions supplémentaires propres au mécanisme
d'identité (qui peut modifier/invalider un code une fois verrouillé ?
conditions d'erreur acceptées, rejet, etc.).

## 9.5 Entrées — PARTIELLEMENT CERTAINES [→ Point C]
Les 5 données du §2.1 sont acquises. QuestionIntent reçoit-il autre chose
(règles, mécanismes, contraintes, métadonnées de rotation) ?

**Point C :** Parmi depth / domain / subdomain_active / subject_active /
dominant_idea_active — lesquelles lit-il réellement et pourquoi ?

## 9.6 Sorties — OPEN [→ Point D]
**Point D (décision la plus importante) :** Que produit-il réellement pour
Phase 1 ? Quelle est la forme du « noyau encodé » remis à Phase 1 ?
Les sorties historiques (§8) sont à re-statuer.

## 9.7 Slots Blueprint lus — OPEN [→ Point G]
**Point G (partie 1) :** Lesquels exactement (les 6 champs Part1 ? moins ? plus) ?

## 9.8 Slots Blueprint écrits — OPEN [→ Point G/E]
**Point G (partie 2) :** QuestionIntent écrit-il dans le Blueprint ?
Fait factuel : le DTO KernelBlueprint possède un slot `kernel_code` et une
méthode `fillKernelCode()` jamais appelée — capacité existante, usage NON décidé.

**Point E :** QuestionIntent crée-t-il l'identité ? Verrouille-t-il une identité
déjà commencée ? Produit-il kernel_code ? Quelle différence entre identité et intention ?

## 9.9 Données internes — OPEN [→ Point H/I]
**Point H :** QuestionIntent a-t-il besoin d'une mémoire propre ? Si oui, pourquoi ?
**Point I :** Si le même Blueprint lui est présenté deux fois : même résultat ?
nouvelle génération ? refus ? À définir.

## 9.10 Mécanismes — OPEN [→ Point I]
Encodage déterministe ? Idempotence (rejouer l'encodage du même Blueprint) ?
Atomicité avec l'engagement du Blueprint ?

## 9.11 Communication — OPEN
Qui appelle QuestionIntent (orchestrateur ? événement ?) ? Synchrone ou
asynchrone ? Signale-t-il quelque chose en aval ?

## 9.12 Contrats — OPEN [→ Point M]
**Point M :** Que garantit exactement QuestionIntent lorsque Phase 1 reçoit le
noyau ? La frontière doit être suffisamment précise pour que Phase 1 puisse
ensuite être spécifiée sans modifier QuestionIntent.
Interfaces exactes amont (Blueprint/Taxonomy) et aval (Phase 1).

## 9.13 États — OPEN [→ Point K]
**Point K :** QuestionIntent a-t-il des états internes ?
⛔ Ne pas inventer READY, FAILED, ENCODED, etc. avant la Mission.

## 9.14 Transitions — OPEN
Quelles transitions, déclenchées par qui, avec quelles gardes ?

## 9.15 Cas limites — OPEN [→ Point L]
**Point L :** Si l'entrée est invalide ou incomplète : que fait QuestionIntent ?
Pas de retry arbitraire. Pas de Quarantine automatique.
À lister puis trancher : double encodage du même Blueprint ; crash entre
engagement et encodage ; Blueprint engagé sans territoire complet ;
re-rotation après échec aval (hors périmètre §7).

## 9.16 Persistance — OPEN [→ Point H]
Où vit l'encodage (table `question_intents` ratifiée ? table dédiée au
noyau ? autre support) ? Décision AVAL de la mission — jamais amont.

## 9.17 Validation — OPEN
Qu'est-ce qui atteste qu'un encodage est conforme (validation de FORME,
sans contrôle métier) ? Qui la porte ?

## 9.18 Tests — OPEN
Critères d'acceptation de l'implantation.

## 9.19 Identité du noyau — OPEN [→ Point E/J + §6]
**Point J :** Qu'est-ce qu'un « même intent » ? Quel niveau de collision doit
être interdit ? Est-ce réellement une responsabilité de QuestionIntent ?
`kld_hash`/`ks_hash` sont abandonnés avec KLD/KS (§6.2). Qu'est-ce qui
identifie un noyau désormais : forme, grain, unicité, générateur, stabilité
dans le temps (Quarantaine, ReadyBank, gameplay, anti-répétition) ?

## 9.20 Relation exacte avec Phase 1 — OPEN [→ Point M]
**Point M (suite) :** Que consomme Phase 1 exactement ? Sous quelle forme le
noyau encodé lui est-il présenté ? Qu'est-ce que Phase 1 n'a PAS le droit de
faire dessus ?

## 9.21 Statut de chacun des champs legacy actuels — OPEN [→ Point N + §6]
**Point N — décision explicite, élément par élément :**

```text
kernel_code     → §6.1 (CAS PARTICULIER — audit requis)
ks_hash         → §6.2 (LEGACY À JUSTIFIER)
kld_hash        → §6.2 (LEGACY À JUSTIFIER)
intent_key      → OPEN
semantic_key    → OPEN
angle_large     → UNSPECIFIED (§5)
micro_angle     → UNSPECIFIED (§5)
answer_target   → UNSPECIFIED (§5)
concept_family  → UNSPECIFIED (§5)
language_source → UNSPECIFIED (§5)
question_intents → table à ratifier ou remplacer (§11 = relevé factuel uniquement)
```

Issues possibles par champ une fois la Mission définie : ratifié dans le
contrat / légué au legacy BankWorker / inerte / destiné à suppression
(migration destructive sur ordre explicite uniquement).

## 9.22 Cardinalité — OPEN [→ Point F]
**Point F :** QuestionIntent produit-il 1 intent par noyau, ou une structure
composée ? Aucune déduction à partir de la table question_intents.

---

# 10. Questions structurantes — ordre de déblocage

Les questions A-N du §9 se débloquent dans cet ordre logique :

```text
Q1 / Point A — Mission         (débloque tout le reste)
Q2 / Point B — Unité traitée  (1 Blueprint ? 1 paire Sujet+Idée ? autre ?)
Q3 / Point E — Identité        (forme, grain, unicité, générateur)
Q4 / Point D — Sorties minimales (de quoi Phase 1 a-t-elle besoin, sous quelle forme ?)
Q5 / Point H — Persistance     (APRÈS Q1/Q2 — où vit le noyau encodé ?)
Q6 / Point N — Sort des champs legacy (APRÈS Q1-Q5 — champ par champ)
```

Questions complémentaires à trancher dans leur rubrique :
C (entrées réelles), F (cardinalité), G (Blueprint slots), I (idempotence),
J (collision/unicité), K (états), L (erreur), M (frontière Phase 1).

---

# 11. Agenda de la spécification — questions de travail issues de la Mission

La Mission étant verrouillée (§9.1), le vrai travail de spécification est :

```text
1. Quelle information compose cette identité ?
   → Qu'est-ce qu'un noyau, exprimé comme identité ? Quels éléments le
     distinguent irréductiblement d'un autre noyau ?

2. Comment kernel_code la représente-t-il ?
   → kernel_code est-il l'identité elle-même ou son encodage ? Format ?
     Construction ? Stabilité dans le temps ?

3. Qu'est-ce qui rend deux noyaux différents ?
   → Grain de l'unicité : Depth seul ? Depth+Domaine+Sous-domaine+Sujet ?
     +Idée Dominante ? Jusqu'où ?

4. Quand le code devient-il immuable ?
   → Dès sa construction par QuestionIntent ? Après une validation aval ?
     Quelles conditions peuvent l'invalider (si elles existent) ?

5. Qui peut le lire ?
   → Orchestrateur, Phase 1, Quarantine, ReadyBank, gameplay, analytique ?
     Qui peut l'utiliser et pour quel usage précis ?

6. Quarantine l'utilise comment ?
   → Le print de travail conserve quelle référence à l'identité canonique ?
     Sous quelle forme ? Comment la réintégration retrouve le bon noyau ?

7. ReadyBank l'utilise comment ?
   → Pour référencer ? Pour retrouver ? Pour éviter les doublons de stockage ?
     Pour la mise à jour d'un noyau existant sans le recréer ?

8. Gameplay peut rechercher quoi grâce à lui ?
   → Anti-répétition par (joueur × noyau) ? Par (joueur × noyau × cognitif) ?
     Quelle granularité d'identification est nécessaire et suffisante ?
```

Ces questions suivent l'ordre logique : 1→2→3 définissent l'identité
(débloquent 9.19) ; 4 définit l'immuabilité (débloque 9.10) ; 5 définit les
consommateurs (débloque 9.11, 9.12) ; 6-7-8 définissent les usages aval
(débloquent 9.20 et partiellement 9.21).

---

# 12. Relevé FACTUEL de `question_intents` (Neon, 11 août 2026)

⚠️ Ce relevé est de l'INFORMATION D'INVENTAIRE pour la rubrique 9.21.
Il ne porte AUCUNE autorité métier (§0, §5). Table VIDE : 0 ligne.

Nettoyage du 11 août 2026 : la migration anticipée `2026_08_11_120000`
(blueprint_id UNIQUE, dominant_idea, advance_attempts, élargissements) a été
RETIRÉE intégralement — down équivalent appliqué sur Neon, tailles historiques
restaurées, registre migrations purgé. Le schéma ci-dessous est l'état
PRÉ-TÂCHE restauré. Les colonnes de l'ère KLD/KS (`kernel_code`, `ks_hash`,
`kld_hash` — migration préexistante 2026_07_03, toutes vides) demeurent :
leur sort relève de la rubrique 9.21 et du §6.

Constat factuel notable : parmi les 7 champs verrouillés UNSPECIFIED,
seuls `intent_key`, `language_source`, `domain` (et `difficulty_depth`)
sont réellement NOT NULL — `angle_large`, `micro_angle`, `answer_target`,
`concept_family`, `semantic_key`, `sub_domain` sont NULLABLE. L'argument
« la DB l'exige » était donc factuellement faux pour la majorité d'entre eux.

| Colonne | Type | Nullabilité | Ère d'origine (factuel) |
|---|---|---|---|
| id | bigint (PK) | NOT NULL | technique |
| intent_key | varchar(255) UNIQUE | NOT NULL | BankWorker |
| language_source | char(2) default 'en' | NOT NULL | BankWorker |
| domain | varchar(64) | NOT NULL | BankWorker |
| sub_domain | varchar(64) | nullable | BankWorker |
| difficulty_depth | smallint | NOT NULL | BankWorker |
| subject | varchar(255) | nullable | BankWorker |
| angle_large | varchar(255) | nullable | BankWorker |
| micro_angle | varchar(255) | nullable | BankWorker |
| answer_target | text | nullable | BankWorker |
| potential_trap | text | nullable | BankWorker |
| concept_family | varchar(191) | nullable | BankWorker |
| source | varchar(32) | nullable | BankWorker |
| semantic_key | varchar(255) UNIQUE partiel | nullable | BankWorker |
| dialysis_status | varchar(32) default 'pending' | NOT NULL | Dialyse |
| dialysed_at / locked_at / locked_by | timestamps / varchar | nullable | Dialyse |
| variantes_present / variantes_missing | jsonb | nullable | Dialyse |
| variantes_count | smallint default 0 | NOT NULL | Dialyse |
| dialysis_summary / dialysis_last_issue | varchar | nullable | Dialyse |
| dialysis_action_count | smallint default 0 | NOT NULL | Dialyse |
| frame_en | jsonb | nullable | ère frame |
| frame_status | varchar(32) | nullable | ère frame |
| frame_validated_at | timestamp | nullable | ère frame |
| kernel_code | varchar(32) UNIQUE partiel | nullable | ère KLD/KS (SUPERSEDED) |
| ks_hash | varchar(64) | nullable | ère KLD/KS (SUPERSEDED) |
| kld_hash | varchar(64) | nullable | ère KLD/KS (SUPERSEDED) |
| created_at / updated_at | timestamp | nullable | technique |

Index/unicités : `intent_key` UNIQUE ; `semantic_key` UNIQUE partiel (non
null) ; `kernel_code` UNIQUE partiel (non null) ;
index (domain, sub_domain, difficulty_depth), concept_family,
dialysis_status, frame_status partiel.

---

**Tant que ce contrat n'est pas fourni et verrouillé :**

```text
Taxonomy
↓
FRONTIÈRE QUESTIONINTENT
↓
STOP
```
