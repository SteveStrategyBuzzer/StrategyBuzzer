# Architecture Register — StrategyBuzzer Kernel Pipeline

**Source de vérité :** ce fichier est le registre centralisé de toutes les décisions architecturales officielles du pipeline Kernel.

Chaque décision inscrite ici est :
- identifiée par un numéro unique (DEC-NNN) ;
- datée ;
- associée à un statut : `OFFICIAL`, `UNDER_REVIEW`, ou `SUPERSEDED` ;
- liée au module concerné.

---

## DEC-027 — Progression individuelle des slots

**Version :** 1.3
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La validation traite tous les slots concernés avant de produire une copie Quarantine.

Une seule copie travaillable est créée à la fin de la passe lorsqu'un ou plusieurs slots sont `FAIL`.

---

## DEC-028 — Retour ciblé depuis Quarantine

**Statut :** SUPERSEDED
**Remplacé par :** DEC-030
**Module :** `01_KernelBlueprint.md`

Ancienne décision : une copie corrigée retournait au moteur propriétaire du contenu fautif.

---

## DEC-029 — Réintégration limitée au slot initialement FAIL

**Statut :** SUPERSEDED
**Remplacé par :** DEC-031
**Module :** `01_KernelBlueprint.md`

Ancienne décision : la réintégration remplaçait uniquement le slot précédemment identifié `FAIL`.

---

## DEC-030 — Retour systématique à Phase 1

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Toute copie travaillable corrigée provenant de Quarantine retourne systématiquement à Phase 1.

Cette règle s'applique aux erreurs détectées :
- en Validation Phase 1 ;
- en Validation Phase 2 ;
- dans un contenu cognitif ;
- dans une traduction ;
- dans une dépendance entre plusieurs slots.

---

## DEC-031 — Réintégration de tous les slots modifiés

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

La copie corrigée est réintégrée dans le Blueprint canonique portant le même `kernel_code`.

La réintégration peut concerner les slots initialement `FAIL`, les slots initialement `OK` mais modifiés, les slots dépendants régénérés, les traductions corrigées.

Les slots canoniques non modifiés restent inchangés.

---

## DEC-032 — Une copie par passe de validation

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

Un moteur de validation termine l'analyse de tous les slots qui lui ont été remis avant de produire une copie Quarantine.

Lorsqu'un ou plusieurs slots sont `FAIL`, il crée une seule copie travaillable contenant tous les slots en échec, toutes les erreurs détectées, tous les points de correction, et le contexte complet du noyau.

Il est interdit de créer une copie distincte pour chaque slot `FAIL` appartenant à la même passe.

---

## DEC-033 — Distinction PASS et OK

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `01_KernelBlueprint.md`

`PASS` est le verdict produit par un moteur de validation.

`OK` est l'état attribué au slot après un verdict `PASS`.

`FAIL` constitue à la fois le verdict d'échec et l'état de fermeture du slot jusqu'à sa correction.

---

## DEC-051 — Initialisation par DepthNeedMatrix

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-060
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-060 (DepthNeedMatrix V2).

---

## DEC-052 — Réception ReadyBank indépendante de la jouabilité

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Un Blueprint est comptabilisé dès sa réception canonique par ReadyBank, même si certains slots sont `FAIL` ou en correction.

---

## DEC-053 — Deux signaux indépendants

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-063
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-063 (CURRENT_KERNEL_RECEIVED signal unique).

---

## DEC-054 — États distincts des domaines

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-061
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-061 (Tour de Depth ON/OFF).

---

## DEC-055 — Complétion sans domaine sélectionnable

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-062
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-062 (fermeture de Tour et bascule de Depth).

---

## DEC-056 — Persistance obligatoire de RotationState

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-064
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-064 (kernel_rotation_state_v2).

---

## DEC-057 — Inclusion officielle du Depth 2 et ordre du DepthCycle

**Version :** 1.0
**Date :** 14 juillet 2026
**Statut :** SUPERSEDED par DEC-065
**Module :** `02_KernelRotationPlanner.md`

Ancienne décision — remplacée par DEC-065 (DepthCycle complet incluant Depth 10).

---

## DEC-058 — Blueprint créé avant KRP

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`KernelBlueprintFactory` crée le Blueprint avant l'entrée dans KRP.
KRP reçoit un Blueprint vide et y inscrit uniquement `depth` et `domain`.

---

## DEC-059 — Identité canonique blueprint_id

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

`blueprint_id` est un UUIDv7 (time-ordered, via `Str::orderedUuid()`) généré par `KernelBlueprintFactory`.
`rotation_identifier` est supprimé.
`kernel_code` ne sert pas d'identité de Blueprint.

---

## DEC-060 — DepthNeedMatrix V2

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

DepthNeedMatrix porte : DepthCycle `[2,4,6,7,8,9,10]`, `cycle_target[depth]` (constantes), `cycle_completed[depth]`, `kernel_received_total[depth][domain]`. Elle ne porte pas les états ON/OFF des Domaines et ne prend aucune décision.

---

## DEC-061 — Tour de Depth ON/OFF

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

8 Domaines ON au début de chaque Tour. Sur EMPTY : Domaine ON → OFF (idempotent). Tour terminé à 8 Domaines OFF.

---

## DEC-062 — Fermeture de Tour et bascule de Depth

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Tour fermé à 8/8. `cycle_completed[active_depth] += 1`. Prochain Depth = premier Depth du DepthCycle pour lequel `cycle_completed < cycle_target`. KRP ne recommence jamais immédiatement le même Depth.

---

## DEC-063 — CURRENT_KERNEL_RECEIVED signal unique

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Seul déclencheur de la prochaine rotation. Canal = événement transactionnel avec Outbox. Listener = `ApplyCurrentKernelReceivedToRotation`. Idempotence = `kernel_current_kernel_receipts` (PK blueprint_id).

---

## DEC-064 — Persistance dans kernel_rotation_state_v2

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Nouvelle table `kernel_rotation_state_v2` (coexiste avec la table legacy DEPRECATED). Aucune table existante n'est supprimée.

---

## DEC-065 — DepthCycle complet incluant Depth 2 et Depth 10

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

DepthCycle = `2 → 4 → 6 → 7 → 8 → 9 → 10`. Après Depth 10 : reprend à Depth 2. PRODUCTION_ON_HOLD = aucun Depth sous `cycle_target`.

---

## DEC-066 — Conservation du Blueprint sur EMPTY

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Sur EMPTY, le même Blueprint est conservé et réutilisé. Aucun nouveau Blueprint n'est créé après un EMPTY.

---

## DEC-067 — Cycle de vie d'exécution du Blueprint

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

Quatre états techniques : `CREATED_UNENGAGED`, `ENGAGED_IN_PIPELINE`, `READY_BANK_RECEIVED`, `NOT_ENGAGED_PRODUCTION_ON_HOLD`. Distincts des slots du Blueprint.

---

## DEC-068 — KernelCodeEngine hors périmètre KRP

**Version :** 2.0
**Date :** 28 juillet 2026
**Statut :** OFFICIAL
**Module :** `02_KernelRotationPlanner.md`

KRP n'écrit jamais `kernel_code`. `kernel_code = null` à la sortie de KRP.

---

## DEC-069 — Mission officielle de QuestionIntent / KernelCodeEngine

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md` — v1.1 (12 août 2026)

KernelCodeEngine reçoit le KernelBlueprint dont le territoire intellectuel a été entièrement déterminé et validé, construit son kernel_code canonique selon la structure officielle StrategyBuzzer, attribue un suffixe séquentiel unique dans le bassin (Depth + Domaine), écrit ce kernel_code dans le KernelBlueprint et rend cette identité immuable. KernelCodeEngine ne modifie aucune composante intellectuelle du noyau et ne détermine aucun traitement cognitif de Phase 1.

---

## DEC-070 — kernel_code : propriétaire exclusif = KernelCodeEngine

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KernelCodeEngine est le seul moteur autorisé à écrire `kernel_code` dans le KernelBlueprint et dans `kernel_blueprint_runs`. Aucun autre moteur ne peut créer, modifier ou invalider un kernel_code existant. KRP ne l'écrit jamais (DEC-068). Taxonomy, VDI, Phase 1 ne l'écrivent jamais.

---

## DEC-071 — Format officiel du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Format : `DD-DO-SUB-SUJ-IDE-VVVV` — 22 caractères, UPPERCASE ASCII sans espace.
`DD` = Depth 2 chiffres ; `DO` = code Domaine 2 lettres ; `SUB/SUJ/IDE` = 3 chars normalisés (NFD+strip+A-Z0-9, pad X) ; `VVVV` = suffixe base36 4 chars.
Regex : `^[0-9]{2}-[A-Z]{2}-[A-Z0-9]{3}-[A-Z0-9]{3}-[A-Z0-9]{3}-[0-9A-Z]{4}$`

---

## DEC-072 — Suffixe VVVV : base36 4 chars, capacité 1 679 616 par bassin

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Alphabet base36 : `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`. Capacité : 36^4 = 1 679 616 par bassin Depth × Domaine. Première valeur : `0000`. Dernière valeur : `ZZZZ` (entier 1 679 615). Ordre strict : entier base10 → base36. Aucun algorithme aléatoire, aucun UUID, aucun hash.

---

## DEC-073 — Compteur indépendant par (Depth, domain_code)

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Table `kernel_code_sequences` — clé primaire composite `(depth, domain_code)`. `next_value` = prochain entier base10 à convertir. Chaque bassin `(02, GE)`, `(02, HI)`, `(04, GE)` etc. possède sa propre séquence indépendante. Allocation atomique par transaction avec `LOCK FOR UPDATE` sur la ligne de séquence. Source de vérité de l'identité : `kernel_blueprint_runs.kernel_code`, jamais `kernel_code_sequences`.

---

## DEC-074 — Immutabilité du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Transition autorisée : `NULL → valeur canonique`. Transition interdite : `valeur → autre valeur`. KernelCodeEngine lui-même ne régénère jamais l'identité d'un noyau déjà identifié. Idempotence : même Blueprint présenté deux fois → même kernel_code retourné, compteur avancé une seule fois.

---

## DEC-075 — Non-recyclage des suffixes consommés

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

Un suffixe VVVV consommé n'est jamais remis dans le bassin, même si la validation aval échoue, si un print Quarantine est créé, ou si le noyau est corrigé. Le noyau canonique principal reste dans le flow. Son kernel_code ne change jamais. Après `ZZZZ` : `QUESTION_INTENT_SUFFIX_EXHAUSTED`, FAIL CLOSED — aucun overflow silencieux.

---

## DEC-076 — KernelCodeEngine : zéro responsabilité cognitive

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KernelCodeEngine ne produit aucun contenu cognitif. Il ne choisit pas recognition, reasoning, deceptive_trap, true/false, réponses, formulations, difficulté cognitive. Il n'appelle pas Gemini, OpenAI, Phase 1, Quarantine, ReadyBank, confirmConsumed(). Ces responsabilités appartiennent exclusivement à Phase 1 et aux modules aval.

---

## DEC-077 — KLD / KEY_STRUCTURE / ks_hash / kld_hash exclus du kernel_code

**Version :** 1.0
**Date :** 11 août 2026
**Statut :** OFFICIAL
**Module :** `05_QuestionIntent.md`

KLD et KEY_STRUCTURE sont SUPERSEDED (absorbés par ValidationDominantIdeas). KernelCodeEngine ne les écrit pas et ne les lit pas. `ks_hash`, `kld_hash` et `question_intents.kernel_code` ont été supprimés physiquement le 11 août 2026 (audit #142/#147 : 0 writer, 0 reader, 0 données). Migrations : `2026_08_11_300000` (ks_hash, kld_hash) + `2026_08_11_310000` (kernel_code + index). Chaîne UP→DOWN vérifiée (#146 PASS). Stockage canonique = `kernel_blueprint_runs.kernel_code` (KernelCodeEngine, DEC-070).
