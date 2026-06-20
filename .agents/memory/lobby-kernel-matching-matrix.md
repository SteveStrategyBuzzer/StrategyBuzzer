---
name: LOBBY_KERNEL_MATCHING_MATRIX
description: Règle verrouillée — le matching noyau/cognitif se prépare DÈS LE LOBBY (Solo/Duo/Ligue), MJ exclu. Où se branche-t-il dans le code actuel.
---

# LOBBY_KERNEL_MATCHING_MATRIX (architecture verrouillée 2026-06-20)

## Règle produit (verrouillée)
Le Gameplay prépare la partie **dès le lobby**, pas au tirage question-par-question.
- **Solo** = matching individuel (1 joueur).
- **Duo / Ligue** = matching collectif lobby.
- **MJ (Maître du Jeu) = EXCLU** : quiz préconstruit avant de connaître les joueurs ; pas de matrice collective. MJ écrit seulement la **consommation per-player APRÈS exposition** (vu question + résolution).

**Why:** en MJ le quiz est figé avant l'arrivée des joueurs → impossible de sélectionner selon l'historique collectif. Pour Solo/Duo/Ligue le roster est connu au lobby → on peut préparer une partie optimisée nouveauté.

## Ordre de sélection collectif (Duo/Ligue)
1. Noyaux **vierges pour TOUS** les joueurs → priorité max.
2. Sinon noyaux **TOUCHÉS mais PAS back_support**.
3. Dans ces noyaux : aligner historiques → cognitif/famille **le moins consommé collectivement**.
4. **BACK_SUPPORT = dernier recours uniquement** (jamais auto dès qu'un noyau est touché).
- Consommation reste **PER-PLAYER** : un cognitif vu par A reste dispo pour B ; la matrice maximise juste la nouveauté de groupe.
- Après résolution : consommation écrite pour **tous les participants exposés**.
- **READY_BANK ne change pas** (stocke des noyaux mères encodés).

## État du code actuel (gap) — voir audit
- Roster complet connu au lobby : `LobbyService::startGame()` L779 `$playerIds = array_keys($lobby['players'])` — puis **perdu**.
- Plan bâti plus tard par Node `/init` → `GameServerQuestionPipeline::initMatch()` L60 → `buildMatchPlanFor()` L663 → `MatchQuestionPlanner::buildPlan()` L54.
- **Bottleneck** : `initMatch($userId)` ne reçoit qu'**UN** user (L74) ; `PlayerMemoryService::getForPlan($this->userId)` L688 = mono-joueur. `buildPlan()` n'a ni roster ni historique collectif → sélection player-agnostic (quotas + RANDOM global via `repo->pickOne`).
- Aucune table `player × noyau × cognitif` câblée (`player_question_history` existe mais sans appelant).

## Architecture immuable (CORRECTION 2026-06-20 — verrouillée)
Laravel = rendu/API/persistance · Node+Socket.IO = autorité gameplay/orchestration · Redis = état live · Firebase = présence/WebRTC SEULEMENT · Blade = passif.
Flux cible : LOBBY Laravel → roster → init Node → GameOrchestrator → LOBBY_KERNEL_MATCHING_MATRIX → PLAN → Redis (live) → gameplay Node-authoritative → Laravel persistance après résolution.
**Why:** le transport roster n'est PAS un choix libre — l'arch dicte que Node orchestre/possède le plan, Laravel sert les données + persiste.

## Transport roster — EXISTE DÉJÀ (ne pas réinventer)
- `LobbyService::startGame()` L888-901 écrit déjà `gs_room_users:{roomId}` (JSON [user_id...], TTL 24h) en Redis.
- Déjà consommé par `recordPlayerMemory(roomId,mode)` (Node) → `PlayerMemoryController::record` L73 lit `gs_room_users:{roomId}` pour résoudre le roster. C'est le pattern officiel « Node envoie roomId+mode, Laravel résout depuis ses données ».
- GAP réel : `/init` (`GameServerController::init` L19-43) ne passe qu'**un seul `userId`** (L27) → plan mono-joueur. Fix = `initMatch` lit `gs_room_users:{roomId}` (même pattern), pas de nouveau canal.

## ⚠️ Nuance DB-bound (point D — à confirmer par user)
La matrice a besoin de READY_BANK (Postgres) + historique per-player (Postgres) = lisibles SEULEMENT par Laravel. Node (TS) n'a pas d'accès Postgres et ne doit pas en avoir.
Réconciliation : calcul matrice = **API interne Laravel** (DB-bound) ; Node **déclenche + possède le plan en Redis** + pilote. « Node monte le plan » = Node orchestre/possède, le calcul reste servi par Laravel. Alternative rejetée (expédier READY_BANK+historique en TS) = lourd/duplication.

## Points d'insertion futurs (non patchés)
- `GameServerController::init` L19-43 : exploiter roster (lecture `gs_room_users` ou payload) au lieu du seul `userId`.
- `GameServerQuestionPipeline::initMatch` L60 / `buildMatchPlanFor` L663 : passer roster + historique collectif au planner.
- `MatchQuestionPlanner::buildPlan` L54 : appliquer la matrice.
- Service matrice DB-bound (Laravel) invoqué par l'init ; plan possédé par Node/Redis.
- `GameOrchestrator.ts` + `InternalLaravelClient.ts` : Node déclenche init avec roster, possède le plan Redis ; jamais Postgres.
- `PlayerMemoryController` + `recordPlayerMemory` : étendre pour écrire consommation per-player cognitive (tous exposés).
- Solo : matcher à l'entrée (`SoloController` + `QuestionService`), roster=1 joueur.
- MJ : aucune matrice ; seulement écriture consommation post-exposition.

## SPEC 13 POINTS — VERROUILLÉE 2026-06-20 (le user ne veut plus d'alternative métier ; seulement le « comment » technique)
- **Terminologie (pt1)** : « construire » = CRÉATION (banque) uniquement. Gameplay = Sélectionner/Préparer/Bufferiser/Orchestrer/Persister. Jamais construire de contenu (IA live déjà retirée).
- **Bank-first (pt8)** : NE PAS partir du joueur. Partir de READY_BANK → Depth demandé → Domaine demandé, PUIS charger les histories joueurs pour ce depth+domaine.
- **KERNEL_AVAILABILITY_MAP (pt9)** : structure unique = READY_BANK + histories. Par noyau : kernel_code, depth, domain, état joueurs, cognitifs consommés, collective_status, collective_available_cognitives. BACK_SUPPORT JAMAIS exclu — apparaît naturellement.
- **Historique joueur (pt4)** : PLAYER→Depth→Domaine→Noyaux touchés→état cognitifs. Formes : Recognition{qcm,tf_true,tf_false}, Reasoning{qcm,tf_true,tf_false}, Deceptive Trap. Statuts VIERGE/TOUCHÉ/BACK_SUPPORT.
- **Consommation (pt5/6/7)** : consommée = question + bonne réponse + SV vus. Envoyée seule ≠ consommée. Déconnexion avant résolution ≠ consommée. Multi : pour tous les participants actifs exposés. MJ idem (après diffusion).
- **Priorité Solo (pt10)** : READY_BANK → retirer noyaux touchés du joueur → vierges → sinon touchés non-back_support → sinon back_support.
- **Priorité Duo/Ligue (pt10)** : MAP → vierges collectifs → touchés non-back_support → alignement cognitifs disponibles → sinon back_support.
- **Buffer progressif (pt11)** : quiz PAS monté en entier d'avance. Intro→Node sélectionne/prépare Q1→GAMEPLAY→Node sélectionne/prépare/bufferise Q2..Qn pendant le jeu (temps naturel entre questions). MJ = préconstruit + diffusion.

## CODE EXISTANT vs SPEC (clé technique)
- Buffer progressif **existe déjà** : `GameOrchestrator.startGame` L137-167 → `initQuestionPipeline` (Q1) → `fetchNextBlock(count=4)` (QuestionService L92) → `appendQuestions` L202. Endpoints Laravel : `/init`, `/next-block/{roomId}`, `/status`, `/cleanup` (GameServerController).
- MAIS la **sélection** est figée d'avance : `initMatch` L60 → `buildMatchPlanFor` L663 → `MatchQuestionPlanner::buildPlan` monte tout `ordered_group_ids`. → à rendre PROGRESSIF (sélection per-block depuis la MAP).
- Node ne lit jamais Postgres : MAP + sélection + rendu + persistance = API Laravel ; Node orchestre/bufferise/possède l'état Redis.

## Plan technique (non patché)
1. Table `player_kernel_cognitive_usage` (user_id, kernel_code=question_intent_id, depth, domain, cognitive_family, cognitive_form{qcm|tf_true|tf_false|trap}, match_ref, mode, consumed_at). Statuts dérivés.
2. `KernelAvailabilityMapBuilder` (Laravel) → MAP en Redis `gs_kernel_map:{roomId}` ; lit roster via `gs_room_users:{roomId}`.
3. `initMatch` + `nextBlock` : sélection progressive depuis la MAP selon priorité pt10 ; rendu question+réponses+SV+traductions depuis READY_BANK ; ne marque rien consommé.
4. Consommation : nouveau signal Node→Laravel après phase RESULT (kernel_code+cognitive_form+joueurs actifs exposés) ; pattern JWT interne comme `saveMatchSnapshot`. `recordPlayerMemory` (fin de match) insuffisant (granularité).
5. Node mécanique inchangée (init→fetchNextBlock). MJ : flux Master inchangé + écriture consommation post-exposition.

## Détails techniques restant à confirmer (PAS métier)
- Granularité signal consommation : par question (recommandé) vs par round.
- Stockage MAP : Redis `gs_kernel_map:{roomId}` (à valider).

## Décisions métier désormais TRANCHÉES par la spec (ne plus rouvrir)
- Table = nouvelle `player_kernel_cognitive_usage`. « Exposé » = question+réponse+SV vus. Solo = sélection questions (pas IA adverses). MAP/sélection = API Laravel, Node orchestre.

## (Historique) Décisions OUVERTES — désormais résolues ci-dessus
1. ✅ RÉSOLU — Transport roster = `gs_room_users:{roomId}` déjà en Redis ; `initMatch` doit le lire (pattern `recordPlayerMemory`). Orchestration = Node.
2. Per-player history : nouvelle table `player_kernel_cognitive_usage` vs extension `player_question_history`.
3. « Exposé » = a vu question + résolution (à confirmer).
4. Solo : la matrice couvre-t-elle aussi le choix des IA adverses ?
5. NOUVEAU — confirmer : calcul matrice = API Laravel (DB-bound), plan possédé/orchestré par Node en Redis, Node ne lit jamais Postgres.
