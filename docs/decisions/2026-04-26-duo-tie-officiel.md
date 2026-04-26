# ADR — Représentation officielle d'une égalité en mode Duo (Option B)

**Date** : 2026-04-26
**Statut** : Accepté
**Auteurs** : équipe StrategyBuzzer
**Tâches associées** : #75 (audit égalité Duo), #77 (Bug #1 dérogation), #76 (Redis-first)

---

## Contexte

Le mode **Duo** est une partie 1v1 organisée en manches (rounds). Le vainqueur officiel
du match est défini par la règle `roundsToWin` (par défaut 2, c-à-d *best-of-3*). À la
fin du match, l'orchestrateur Node émet l'événement `match_ended` (vu en logs sous la
forme :

```json
{
  "type": "MATCH_ENDED",
  "winnerId": "3",
  "isTie": false,
  "finalScores": { "3": 0, "bot_xxx": -26 },
  "roundsWon": { "3": 2, "bot_xxx": 0 }
}
```

Trois situations peuvent en théorie produire une **égalité officielle** :

1. **Égalité de manches gagnées** au terme du nombre maximal de manches autorisé
   (`maxRounds`). Exemple : best-of-3 où chaque joueur a gagné 1 manche et la 3ᵉ
   se termine sans départager (très rare car la 3ᵉ manche se joue justement pour
   départager, mais possible si `roundsToWin` est ajusté).
2. **Égalité de score** au sein d'une manche limite, toutes questions épuisées,
   sans qu'aucun joueur n'ait atteint le seuil de victoire de manche.
3. **Forfait simultané / déconnexion mutuelle** au-delà de la grace period : aucun
   joueur n'est présent pour réclamer la victoire.

Avant cet ADR, le contrat d'événement n'était pas explicite sur ces cas : `winnerId`
était toujours rempli (par défaut le `player1_id` ou un fallback arbitraire), ce qui
créait des matches finalisés en DB avec un faux vainqueur, faussant les statistiques
historiques (`PlayerDuoStat`, classements de saison, qualifications bot, etc.).

---

## Décision (Option B)

En cas d'égalité officielle telle que définie ci-dessus, l'événement `match_ended`
émis par Node ET la persistance Laravel (`/internal/duo/match/finalize`) DOIVENT
respecter le contrat suivant :

```json
{
  "winnerId": null,
  "isTie": true,
  "finalScores": { "<playerId1>": <int>, "<playerId2>": <int> },
  "roundsWon": { "<playerId1>": <int>, "<playerId2>": <int> }
}
```

Règles invariantes :

- **`winnerId === null` ⇔ `isTie === true`** (équivalence stricte, jamais l'une sans
  l'autre).
- En partie normale (vainqueur identifié) : `winnerId === <stringPlayerId>` ET
  `isTie === false`.
- `finalScores` et `roundsWon` sont toujours présents et complets pour les deux
  joueurs, indépendamment de `isTie`.

### Alternatives considérées

- **Option A — Sentinelle `winnerId = "tie"`** : rejetée. Casse le typage (mélange
  d'IDs joueurs et de chaînes magiques), force chaque consommateur à connaître la
  sentinelle, et empêche toute jointure SQL naïve sur la colonne winner.
- **Option C — Tirage aléatoire d'un vainqueur côté serveur** : rejetée. Viole le
  principe d'équité affiché aux joueurs et corrompt définitivement la DB (impossible
  de distinguer a posteriori un vrai vainqueur d'un vainqueur tiré au sort).
- **Option B — `winnerId = null, isTie = true`** : retenue. Sémantique claire,
  compatible avec les colonnes nullable existantes, naturel à filtrer en SQL
  (`WHERE winner_id IS NULL`), et trivial à interpréter côté UI.

---

## Conséquences

### Conformité requise (à appliquer)

1. **Node — `GameOrchestrator.endMatch()`** : lorsque la condition d'égalité est
   détectée, émettre `match_ended` avec `winnerId=null, isTie=true`. Ne JAMAIS
   remplir `winnerId` par défaut.
2. **Laravel — `DuoController::internalFinalize()`** (route interne
   `/internal/duo/match/finalize`, déléguant à
   `DuoController::applyFinalizationFromRedis()`) : accepter `winner_id=null`
   et persister `is_tie=true`. La colonne `duo_matches.winner_id` est nullable ;
   aucune migration nécessaire à ce jour.
3. **Stats joueur — `PlayerDuoStat`** : en cas d'égalité, n'incrémenter NI
   `wins` NI `losses` ; incrémenter à la place `ties` (ajouter la colonne si
   absente). Les classements de saison comptent une égalité comme zéro point
   de victoire par défaut.
4. **UI Match Result** : afficher un libellé dédié (« Match nul ») plutôt que
   « Vous avez gagné » ou « Vous avez perdu ». Les deux joueurs voient le même
   écran neutre.
5. **Bot Engine** : `BotQualificationService::onMatchEnded()` doit ignorer les
   matches avec `is_tie=true` pour le calcul des seuils de qualification (un
   match nul ne doit ni qualifier ni disqualifier le jumeau bot).

### Impacts non-régression

- Les requêtes SQL existantes du type `WHERE winner_id = ?` continueront à
  fonctionner (un match nul ne matche aucun joueur, ce qui est correct).
- Les requêtes du type `WHERE winner_id != ?` doivent être auditées : elles
  peuvent désormais inclure des matches nuls, ce qui est généralement le
  comportement souhaité (le match n'a pas été perdu non plus).
- Tout consommateur d'événement Socket.IO `match_ended` doit lire `isTie`
  AVANT de lire `winnerId` pour éviter une déréférencation null silencieuse.

### Tests d'acceptation

- E2E « égalité best-of-3 » : forcer un match qui termine 1-1-tie via les
  endpoints test-support et vérifier que :
  - L'événement Socket.IO contient `winnerId: null, isTie: true`.
  - La ligne `duo_matches` persiste `winner_id IS NULL` et `is_tie = true`.
  - Aucun `wins`/`losses` n'est incrémenté pour les deux joueurs.
  - L'écran final affiche « Match nul » dans les 10 langues.

---

## Références

- Architecture canonique : « Node = autorité unique de gameplay »
  (`replit.md` § Real-Time Multiplayer Synchronization).
- ADR connexe : `2026-04-26-duo-immediate-result-nav.md` (révoqué par #77 P77.3 :
  la dérogation Bug #1 a été retirée ; cet ADR-ci ne dépend pas d'elle).
- Contrat avatar : `docs/STRATEGIC_AVATAR_SKILL_CONTRACT.md` (pour les skills
  qui modifient le score en fin de manche, l'égalité doit être recalculée
  *après* application des effets de skill).
