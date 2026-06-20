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

## Points d'insertion futurs (non patchés)
- Transmettre roster lobby→pipeline : payload `/init` OU relire lobby cache via `roomId` (moins invasif).
- Nouveau `app/Services/QuestionBank/Lobby/LobbyKernelMatchingMatrix.php`.
- Étendre `PlayerMemoryService::getForPlan` au multi-joueur (ou service collectif).
- Solo : matcher à l'entrée de partie (`SoloController` + `QuestionService`).
- MJ : aucune matrice ; seulement écriture consommation post-exposition.

## Décisions OUVERTES (non tranchées)
1. Transport roster : payload /init vs relecture cache lobby.
2. Per-player history : nouvelle table `player_kernel_cognitive_usage` vs extension `player_question_history`.
3. « Exposé » = a vu question + résolution (à confirmer).
4. Solo : la matrice couvre-t-elle aussi le choix des IA adverses ?
