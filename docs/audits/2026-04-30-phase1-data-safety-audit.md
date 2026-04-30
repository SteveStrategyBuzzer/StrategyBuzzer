# Audit Phase 1 — Sécurisation des données critiques

**Date** : 30 avril 2026
**Contexte** : Suite au rollback #18, audit obligatoire avant toute remise en place UI.
**Périmètre** : achats, identifiants joueurs, gains/pertes, quêtes, progression.
**Statut** : Lecture seule — aucun code modifié.

---

## 1. Cartographie des flux critiques

### 1.1 Identifiants joueurs (`user_id` / `player_code`)

| Élément | Stockage | Owner | Validation |
|---|---|---|---|
| `users.id` | Postgres (auto-increment) | Laravel (DB) | Auth Laravel + Sanctum |
| `users.player_code` (ex. `STRAT-XXXX`) | Postgres, **UNIQUE en DB** | `App\Services\PlayerCodeService` | Index unique (migration `2025_10_14_101025`) |
| Identité côté Game Server | JWT HS256 (TTL 2h) | Émis par `App\Services\GameServerService::generatePlayerToken` | Vérifié dans `apps/game-server/src/middleware/auth.ts` |
| Identité côté Question API | JWT HS256 court (60s) avec `aud=question-api` + `payload_hash` | `QuestionApiClient` | Verif côté Node |

**Secrets partagés** : `GAME_SERVER_JWT_SECRET` (Game Server + interne Laravel), `QUESTION_API_JWT_SECRET` (avec fallback sur le précédent).

### 1.2 Achats / paiements

| Élément | Stockage | Owner | Idempotence |
|---|---|---|---|
| Intent d'achat | `purchase_intents` (Postgres) | `CoinsController`, `BoutiqueController` | `stripe_session_id` UNIQUE |
| Paiement | `payments` (Postgres) | Webhook Stripe | `stripe_session_id` + `stripe_payment_intent_id` UNIQUE |
| Évènements webhook | `stripe_webhook_events` (Postgres) | `StripeWebhookController` | `event_id` UNIQUE + colonne `processed_at` |
| Crédit final des coins | `coin_ledger` + `users.coins` / `users.competence_coins` | `CoinLedgerService::credit` (appelé par le webhook) | DB::transaction + lockForUpdate sur `Payment` et `PurchaseIntent` |

**Endpoints** : `POST /stripe/webhook` (CSRF exempt), `GET /coins/success`, `/modes/success`, `/master/success` (UI seulement, ne créditent pas).

### 1.3 Gains / pertes (coins)

| Élément | Stockage | Owner | Idempotence |
|---|---|---|---|
| Solde | `users.coins` (intelligence), `users.competence_coins` | `CoinLedgerService` (seul mutateur applicatif) | Transaction DB |
| Journal | `coin_ledger` (delta, raison, ref_type, ref_id, balance_after, coin_type) | Écrit dans la même transaction que la mise à jour du solde | Pas de contrainte unique sur (user_id, ref_type, ref_id) |

**Mutateurs identifiés** :
- `StripeWebhookController` — crédite à la fin d'un paiement.
- `QuestService` — crédite récompenses de quêtes principales.
- `DailyQuestService` — crédite récompenses de quêtes du jour (via `$user->increment(...)`, voir §2).
- `SeasonService` — récompenses de saison.
- `AdRewardService` — coins des pubs récompensées.
- `LobbyService`, `DuoMatchmakingService`, `LeagueIndividualService`, `LeagueTeamService`, `SoloController` — gains/pertes en fin de match.
- `BoutiqueController` — débit lors d'un achat boutique.
- `AvatarController`, `DivisionService` — débits divers.
- `AuthController` — bonus de bienvenue (250 pièces de compétence à l'inscription).

### 1.4 Quêtes & progression

| Élément | Stockage | Owner | Idempotence |
|---|---|---|---|
| Définitions | `quests` (Postgres) | Seeders | — |
| Progression principale | `user_quest_progress` | `QuestService` | UNIQUE (user_id, quest_id) + `lockForUpdate` + flags `completed`/`rewarded` |
| Quêtes du jour | `user_daily_quests` | `DailyQuestService` | UNIQUE (user_id, quest_id, quest_date) + `lockForUpdate` + colonne `completed_at` |
| Rotation | `RotateDailyQuests` (cron Artisan) | Queue worker | — |

**Endpoints utilisateur** : `POST /daily-quests/action` (liste blanche d'actions, mappage serveur, blocage si déjà complété aujourd'hui).

### 1.5 État temps réel & match (Redis)

| Clé Redis | Contenu | TTL | Persistance DB |
|---|---|---|---|
| `room:{id}:state` | État complet du match en cours | 2 h | Non — perdu si Redis tombe |
| `room:{id}:events` | Journal d'événements (rehydratation) | 2 h | Non |
| `gs:match:{id}:result` | Résultat autoritatif lu par Laravel à la finalisation | 2 h | Lu puis poussé en DB par `*Controller::internalFinalize` |
| `finalize:{mode}:{matchId}` | Verrou distribué de finalisation | court | — |

**Communication services** :
- Frontend ↔ Backend : HTTP (auth Laravel).
- Frontend ↔ Game Server : WebSocket (Socket.IO + JWT).
- Backend → Game Server : HTTP / Redis pub/sub.
- Game Server → Backend : HTTP avec JWT court `purpose=internal_finalize`, finalisation tirée depuis Redis (le client envoie seulement `roomId`).

---

## 2. Risques identifiés (par sévérité)

### 🔴 CRITIQUE — Doivent être corrigés avant tout retour UI

| # | Risque | Localisation | Impact |
|---|---|---|---|
| C1 | **Pas d'idempotence sur les crédits de fin de match.** `DuoMatchmakingService`, `LeagueTeamService`, `LeagueIndividualService`, `SoloController`, `LobbyService` (côté credit) appellent `CoinLedgerService::credit` sans vérifier si une ligne `coin_ledger` existe déjà pour ce `(user_id, ref_type='match', ref_id=match_id)`. | Voir tableau §1.3 | Un retry réseau, un double-clic, un re-finalize Node→Laravel ou la rejouabilité d'un évènement Redis peuvent **doubler les gains** d'un joueur. |
| C2 | **`coin_ledger` n'a pas d'index UNIQUE sur `(user_id, ref_type, ref_id, reason)`.** Migration `2025_10_02_114439_create_coin_ledger_table.php`. | DB | Même si on ajoute la vérification applicative en C1, sans contrainte DB la course concurrente reste possible. La DB doit être l'ultime garde-fou. |
| C3 | **`CoinLedgerService::credit/debit` n'utilise PAS `lockForUpdate()` sur la ligne `users`.** Il fait un `refresh()` non verrouillant puis save. | `app/Services/CoinLedgerService.php` | Sous deux requêtes simultanées (par ex. webhook Stripe + finalize match), la lecture–calcul–écriture peut entraîner un solde incorrect (lost update). Déjà documenté dans `docs/audits/2026-03-14-economic-ledger-cleanup.md` mais non fixé. |
| C4 | **`DailyQuestService` crédite via `$user->increment('competence_coins', ...)` au lieu du ledger.** | `app/Services/DailyQuestService.php` ligne 138 | Aucune trace dans `coin_ledger` → audit/réconciliation impossible pour ces gains, et règle « DB = vérité, ledger = seule porte » violée. |
| C5 | **`AuthController` crédite 250 pièces à l'inscription par mutation directe** (sans ledger). | `app/Http/Controllers/AuthController.php` ligne 209 | Idem — bonus de bienvenue invisible dans l'audit, et un `register` rejoué (test, replay) crédite 2× sans détection. |
| C6 | **État de match en cours uniquement dans Redis** (`room:{id}:state`, `room:{id}:events`). | Game Server | Si Redis tombe (c'est le cas en ce moment), **toutes les parties en cours sont irrécupérables** : pas de fallback Postgres, pas de snapshot. Le rollback récent peut avoir aggravé ce point. |
| C7 | **Pas de table générique `critical_actions_log`.** Seuls existent `coin_ledger` (coins), `stripe_webhook_events` (paiements), `admin_question_audit_log` (IA). | DB | Aucun audit transverse pour : changements d'avatar, achats boutique non-coin, attribution de quête, transitions de saison, modifications de profil. En cas de litige, impossible de dire « qui a fait quoi quand ». |

### 🟠 ÉLEVÉ — À traiter en Phase 2

| # | Risque | Localisation | Impact |
|---|---|---|---|
| H1 | **Webhook Stripe non rate-limité.** Route dans le groupe `web`. | `routes/web.php` | DoS possible en saturant l'endpoint. Stripe lui-même retry, donc impact réel modéré, mais bonne hygiène à ajouter. |
| H2 | **`StripeService::Session::create` ne pose pas d'`idempotency_key` Stripe.** | `app/Services/StripeService.php` | Si le user clique « payer » 2× très vite, on crée 2 sessions Stripe distinctes. La protection actuelle (`PurchaseIntent` uniqueness) joue côté nous, mais on perd la déduplication amont chez Stripe. |
| H3 | **`BoutiqueController::debit` n'utilise pas de verrouillage sur l'item acheté.** | ligne 324 | Double-clic = potentiellement 2 achats, 2 débits. La déduction coins est dans `CoinLedgerService` (qui souffre de C3), donc le risque s'additionne. |
| H4 | **Pas de sauvegarde Postgres planifiée.** `app/Console/Kernel.php` n'a aucune commande de dump. Dossier `backups/` ne contient qu'un ZIP d'août 2025. | Infra | En cas de corruption ou de nouveau rollback, **on n'a pas de point de restauration récent**. |
| H5 | **`Cloud_SQL_Export_2025-09-05 (09:06:02).sql`** (dump complet, données users) est **dans le repo à la racine**. | Repo | Risque de fuite si le repo devient public. À déplacer/supprimer/gitignorer. |

### 🟡 MOYEN — À surveiller

| # | Risque |
|---|---|
| M1 | Beaucoup de fichiers parasites créés à la racine (artefacts d'éditions ratées : `[`, `[],`, etc.) — pas un risque sécu mais bruit qui complique l'audit du repo. |
| M2 | TTL Redis de 2 h sur l'état de match — si une partie dure plus de 2 h, perte. Peu probable mais non géré. |
| M3 | Pas de mécanisme de réconciliation périodique entre `users.coins` et `SUM(coin_ledger.delta)` — une dérive silencieuse ne serait jamais détectée. |

---

## 3. Ce qui marche déjà bien (à préserver)

- ✅ **Webhook Stripe** : signature vérifiée, idempotence par `event_id`, transaction + `lockForUpdate` sur `Payment` et `PurchaseIntent`, retry-safe (HTTP 500 + `processed_at` dans un `finally`). C'est un excellent modèle à dupliquer ailleurs.
- ✅ **`QuestService`** : `lockForUpdate` + flag `rewarded` + transaction → bonne référence d'idempotence à étendre aux fins de match.
- ✅ **Finalisation de match** : verrou Redis `finalize:{mode}:{matchId}` + check `status === 'finished'` côté DB → empêche le double-finalize au niveau match (mais pas le double-credit côté `coin_ledger`, voir C1).
- ✅ **`player_code`** : UNIQUE en DB, pas seulement en PHP.
- ✅ **JWT** : `playerId` toujours dérivé de `Auth::user()`, jamais du frontend. Pas d'endpoint sensible accepter un `user_id` en input trouvé.
- ✅ **Game Server → Laravel** : finalisation pull-based (Laravel relit Redis, ne fait pas confiance au body HTTP).

---

## 4. Plan recommandé pour Phase 2

Je propose de regrouper les corrections en **5 tâches indépendantes** (donc parallélisables ou ordonnançables), à valider ensemble avant tout code :

1. **T-A — Verrou pessimiste & idempotence dans `CoinLedgerService`** (corrige C2 + C3)
   - Ajouter migration : index UNIQUE partiel sur `coin_ledger(user_id, ref_type, ref_id, reason)` quand `ref_type` et `ref_id` sont non-null.
   - Modifier `CoinLedgerService::credit/debit` : `User::lockForUpdate()->find($user->id)` au lieu de `refresh()`.
   - Ajouter méthode `creditOnce(...)` qui catch la violation d'unicité et renvoie l'entrée existante (idempotent).

2. **T-B — Brancher tous les crédits de fin de match sur `creditOnce`** (corrige C1)
   - `DuoMatchmakingService`, `LeagueTeamService`, `LeagueIndividualService`, `SoloController`, `LobbyService` : passer `ref_type='match'`, `ref_id=$match->id`.
   - Brancher aussi `DailyQuestService` (corrige C4) et le bonus `AuthController` (corrige C5) sur le ledger.

3. **T-C — Table d'audit transverse `critical_actions_log`** (corrige C7)
   - Migration : `id, user_id, action, payload (jsonb), ip, user_agent, created_at`.
   - Trait `LogsCriticalAction` réutilisable + middleware optionnel.
   - Brancher sur : achats boutique, changements d'avatar, claim quête, login/logout, finalize match.

4. **T-D — Persistance de secours pour l'état de match** (corrige C6)
   - Snapshot périodique (toutes les N rounds) de `room:{id}:state` dans une table `match_snapshots(match_id, snapshot jsonb, created_at)`.
   - À la finalisation, supprimer les snapshots (ou TTL).
   - Permet la reprise si Redis tombe en plein match.

5. **T-E — Hygiène & sauvegarde** (corrige H1, H2, H4, H5)
   - Throttle sur `/stripe/webhook` (généreux : 60/s).
   - `idempotency_key` Stripe = `purchase_intent.uuid`.
   - Commande Artisan `db:backup` planifiée quotidiennement → stockage hors-repo.
   - Retirer `Cloud_SQL_Export_*.sql` du repo, ajouter au `.gitignore`, le rotater.
   - Déduplication `BoutiqueController` via verrou `purchase_intent` ou ledger idempotent.

---

## 5. Validation Phase 3 (à faire après code)

Tests à écrire / faire passer :
- `tests/Feature/CoinLedgerIdempotencyTest.php` — double credit même `(ref_type, ref_id)` ⇒ une seule ligne, un seul crédit.
- `tests/Feature/StripeDoublePurchaseTest.php` — webhook envoyé 2× ⇒ une seule fulfillment.
- `tests/Feature/MatchFinalizationDoubleTest.php` — finalize Node + finalize fallback frontend ⇒ une seule ligne `coin_ledger`.
- `tests/Feature/QuestDoubleClaimTest.php` — déjà couvert pour main, **à ajouter pour daily**.
- `tests/Feature/MatchSnapshotRecoveryTest.php` — Redis flush en plein match ⇒ rehydratation depuis Postgres.
- Grep CI : interdire `->coins =`, `->competence_coins =`, `increment('coins'`, `increment('competence_coins'` hors `CoinLedgerService`.

---

## 6. Règle absolue (rappel)

Aucune feature UI (#18 et autres) n'est rejouée tant que **C1 → C7** ne sont pas tous corrigés et que les tests Phase 3 ne passent pas.
