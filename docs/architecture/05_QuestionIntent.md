# STRATEGYBUZZER — 05_QUESTIONINTENT (CONTRAT EN CONSTRUCTION)

**Version :** 0.1 — CADRE DE TRAVAIL
**Date :** 11 août 2026
**Spécification écrite :** NON — ce document est le CADRE de la spécification à produire, il ne contient AUCUNE décision métier nouvelle
**Statut :** UNSPECIFIED / UNDER_CONSTRUCTION
**Implantation :** INTERDITE tant que ce contrat n'est pas verrouillé (RÈGLE DU VIDE)
**Gel :** `BLOCKED_AT_QUESTION_INTENT_CONTRACT` — aucun rotate réel, aucun encodage, aucune création Gemini à cette frontière

---

# 0. Méthode imposée (verrou utilisateur — 11 août 2026)

Le contrat métier se définit D'ABORD ; les colonnes ne viennent QU'ENSUITE.

Question directrice unique :

```text
Que doit exactement faire QUESTIONINTENT avec ce noyau Taxonomy validé
pour préparer Phase 1 ?
```

et JAMAIS :

```text
Que devons-nous mettre dans les colonnes existantes ?
```

Séquence obligatoire avant tout code :

```text
reconstruction du contrat complet
→ définition officielle des entrées
→ définition officielle des sorties
→ définition de l'identité du noyau
→ détermination de ce qui devient legacy
→ inscription à l'Architecture Register
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
KernelBlueprint (créé AVANT KRP)
↓
KernelRotationPlanner (depth + domain)
↓
Taxonomy ↕ ValidationDominantIdeas
↓
[ QUESTIONINTENT — À DÉFINIR ]   ← FRONTIÈRE — STOP
↓
Phase 1
```

Le pipeline reste bloqué à cette frontière jusqu'au verrouillage du présent
contrat. Le schedule `questions:kernel:process-outbox` (préexistant) peut
rester actif : il est no-op tant qu'aucun noyau réel n'est engagé et
n'effectue aucune création Gemini.

---

# 2. Entrées CERTAINES déjà acquises (fait certain — 11 août 2026)

Données validées que Taxonomy fournit, portées par le KernelBlueprint :

```text
depth
domain
subdomain_active
subject_active
dominant_idea_active
```

Toute entrée supplémentaire éventuelle relève de la rubrique « Entrées »
(§5.5) et doit être décidée explicitement — jamais supposée.

---

# 3. Verrous négatifs (utilisateur — 11 août 2026)

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

Rappels d'architecture en vigueur :
- ⛔ KLD / KEY_STRUCTURE : SUPERSEDED (absorbés par ValidationDominantIdeas) ;
- ⛔ KernelIdentifierManager : ABANDONNÉ ;
- DEC-052 concerne la COMPTABILISATION rotation — « reçu » et « accepté »
  ne doivent pas être fusionnés sans contrat officiel. Cette clarification
  est une spécification SÉPARÉE, à traiter APRÈS le présent contrat
  (une seule spécification à la fois).

---

# 4. Acquis antérieurs à RE-RATIFIER (héritage — aucune reconduction d'office)

Contrat historique de QUESTIONINTENT (verrouillé 2026-06-16/19, ère KLD/KS) :

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

# 5. Les 21 rubriques du contrat — statut et questions à trancher

Chaque rubrique reste **OPEN** tant qu'elle n'est pas verrouillée par
l'utilisateur. Les questions sont posées SANS proposition de réponse.

## 5.1 Mission — OPEN
La question directrice du §0.

## 5.2 Position — VERROUILLÉE
Voir §1. Entre Taxonomy↕ValidationDominantIdeas et Phase 1.

## 5.3 Responsabilités — OPEN
Le périmètre « pur encodeur » historique est-il reconduit tel quel ?
Liste exhaustive des verbes autorisés (encoder, identifier, tracer…) ?

## 5.4 Interdictions — OPEN
Les interdictions historiques (§4) sont-elles reconduites ? Nouvelles
interdictions propres au flow 2026-08-11 ?

## 5.5 Entrées — PARTIELLEMENT CERTAINES
Les 5 données du §2 sont acquises. QuestionIntent reçoit-il autre chose
(règles, mécanismes, contraintes, métadonnées de rotation) ?

## 5.6 Sorties — OPEN
Que produit exactement QuestionIntent ? Quelle est la forme du « noyau
encodé » remis à Phase 1 ? Les sorties historiques (§4) sont à re-statuer.

## 5.7 Slots Blueprint lus — OPEN
Lesquels exactement (les 6 champs Part1 ? moins ? plus) ?

## 5.8 Slots Blueprint écrits — OPEN
QuestionIntent écrit-il dans le Blueprint ? Fait factuel : le DTO
KernelBlueprint possède un slot `kernel_code` et une méthode
`fillKernelCode()` jamais appelée — capacité existante, usage NON décidé.

## 5.9 Données internes — OPEN
QuestionIntent possède-t-il un état interne propre, ou est-il sans état ?

## 5.10 Mécanismes — OPEN
Encodage déterministe ? Idempotence (rejouer l'encodage du même Blueprint) ?
Atomicité avec l'engagement du Blueprint ?

## 5.11 Communication — OPEN
Qui appelle QuestionIntent (orchestrateur ? événement ?) ? Synchrone ou
asynchrone ? Signale-t-il quelque chose en aval ?

## 5.12 Contrats — OPEN
Interfaces exactes amont (Blueprint/Taxonomy) et aval (Phase 1).

## 5.13 États — OPEN
Le noyau encodé a-t-il des états propres à QuestionIntent ? Lesquels ?

## 5.14 Transitions — OPEN
Quelles transitions, déclenchées par qui, avec quelles gardes ?

## 5.15 Cas limites — OPEN
À lister puis trancher : double encodage du même Blueprint ; crash entre
engagement et encodage ; Blueprint engagé sans territoire complet ;
re-rotation après échec aval ; etc.

## 5.16 Persistance — OPEN
Où vit l'encodage (table `question_intents` ratifiée ? table dédiée au
noyau ? autre support) ? Décision AVAL de la mission — jamais amont.

## 5.17 Validation — OPEN
Qu'est-ce qui atteste qu'un encodage est conforme (validation de FORME,
sans contrôle métier) ? Qui la porte ?

## 5.18 Tests — OPEN
Critères d'acceptation de l'implantation.

## 5.19 Identité du noyau — OPEN (question centrale)
`kld_hash`/`ks_hash` sont abandonnés avec KLD/KS. Qu'est-ce qui identifie
un noyau désormais : forme, grain, unicité, générateur, stabilité dans le
temps (Quarantaine, ReadyBank, gameplay, anti-répétition) ?

## 5.20 Relation exacte avec Phase 1 — OPEN
Que consomme Phase 1 exactement ? Sous quelle forme le noyau encodé lui
est-il présenté ? Qu'est-ce que Phase 1 n'a PAS le droit de faire dessus ?

## 5.21 Statut de chacun des champs legacy actuels — OPEN
À statuer champ par champ UNE FOIS la mission définie (§6 fournit le
relevé factuel). Issues possibles par champ : ratifié dans le contrat /
légué au legacy BankWorker / inerte / destiné à suppression (migration
destructive sur ordre explicite uniquement).

---

# 6. Relevé FACTUEL de `question_intents` (Neon, 11 août 2026)

⚠️ Ce relevé est de l'INFORMATION D'INVENTAIRE pour la rubrique 5.21.
Il ne porte AUCUNE autorité métier (§0, §3). Table VIDE : 0 ligne.

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
| sub_domain | varchar(256) | nullable | BankWorker |
| difficulty_depth | smallint | NOT NULL | BankWorker |
| subject | varchar(256) | nullable | BankWorker |
| angle_large | varchar(512) | nullable | BankWorker |
| micro_angle | varchar(512) | nullable | BankWorker |
| answer_target | text | nullable | BankWorker |
| potential_trap | text | nullable | BankWorker |
| concept_family | varchar(256) | nullable | BankWorker |
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
| blueprint_id | char(36) UNIQUE | nullable | additive 2026-08-11 — INERTE |
| dominant_idea | varchar(512) | nullable | additive 2026-08-11 — INERTE |
| advance_attempts | smallint default 0 | NOT NULL | additive 2026-08-11 — INERTE |
| created_at / updated_at | timestamp | nullable | technique |

Index/unicités : `intent_key` UNIQUE ; `semantic_key` UNIQUE partiel (non
null) ; `kernel_code` UNIQUE partiel (non null) ; `blueprint_id` UNIQUE ;
index (domain, sub_domain, difficulty_depth), concept_family,
dialysis_status, frame_status partiel.

---

# 7. Questions structurantes ouvertes (déblocage des rubriques)

- **Q1 — Mission** : que doit exactement faire QuestionIntent avec le noyau
  Taxonomy validé pour préparer Phase 1 ? (§5.1 — débloque 5.3, 5.4, 5.6)
- **Q2 — Unité encodée** : le contrat historique définit le NOYAU MÈRE comme
  entité complète (1 sous-domaine, structure interne sujets × idées) ; le
  flux actuel fournit UN territoire actif par Blueprint (1 sous-domaine +
  1 sujet + 1 idée dominante). Qu'encode QuestionIntent exactement ?
  (débloque 5.6, 5.19, la lecture de « entité complète » du §4)
- **Q3 — Identité du noyau** : forme, grain, unicité, générateur. (§5.19)
- **Q4 — Sorties minimales** : de quoi Phase 1 a-t-elle besoin pour démarrer,
  sous quelle forme ? (§5.6, §5.20)
- **Q5 — Persistance** : où vit le noyau encodé ? (§5.16 — APRÈS Q1/Q2)
- **Q6 — Sort des champs legacy** : issue par champ, une fois Q1–Q5
  tranchées. (§5.21)

---

**Tant que ce contrat n'est pas fourni et verrouillé :**

```text
Taxonomy
↓
FRONTIÈRE QUESTIONINTENT
↓
STOP
```
