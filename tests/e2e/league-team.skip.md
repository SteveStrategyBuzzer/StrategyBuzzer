# League Team — Live stats e2e: SKIPPED (blocked, not failed)

## Why no League Team e2e test exists

League Team mode does **not** yet emit `player_stats_updated` /
`round_stats` / `match_stats` over Socket.IO from the Node-authoritative
game server. Today League Team is still served by the legacy Laravel
controller flow (REST polling against `LeagueTeamController`) and does
not consume `GameOrchestrator.ts`.

Because the live-stats wire format is *defined* by the Node game-engine
events (see `apps/game-server/src/services/GameOrchestrator.ts` lines
~489 / ~514 / ~1102 / ~1261), there is nothing for `GameplayRuntime.js`
to patch in League Team — its `[data-stat][data-player]` slots, if any,
can never tick mid-match until League Team is migrated to the
Node-authoritative pipeline.

Writing a Playwright spec that asserts "League Team self/opponent stats
update mid-match" today would always fail for the wrong reason
(architectural gap, not a regression in the live-stats engine), and
writing one that only asserts the DOM contract would be misleading —
the slots can be present in the Blade template and still never receive
events.

## Prerequisite for un-skipping this test

Migrate League Team gameplay to the Node game-server / GameOrchestrator
pipeline (the same way Duo and Master were migrated). Once
`player_stats_updated` is emitted for League Team rooms, follow the
pattern in `tests/e2e/duo.spec.ts` to add `tests/e2e/league-team.spec.ts`:

  - Spawn 9 bot teammates/opponents (League Team is 5v5) — this also
    requires extending `BotPlayerService` to League Team rooms.
  - Drive the human end of the match.
  - Assert `[data-stat][data-player="self"]`, `[data-player="teammate-N"]`,
    and `[data-player="opp-N"]` slots all tick.
  - Assert the corresponding `league_team_matches` row finalizes.

## Tracking

This skip aligns with the pending architecture audit referenced in
`docs/audits/league_team_live_stats_pending.md` (if present in your
working tree) and with the user-approved scope of task #46.

## Update — Task #50 (Phase A) lifted the architectural blocker

Task #50 (April 25 2026) migrated League Team to the same room-allocation
contract as Duo / Master / League Individual:

- `LeagueTeamController::startMatch()` allocates a `LEAGUE_TEAM` Node room
  via `GameServerService::createRoom()` and persists `room_id` +
  `lobby_code` on the `league_team_matches` row (additive migration
  `2026_04_25_000002_add_room_id_to_league_team_matches.php`,
  `Schema::hasColumn`-guarded).
- `LeagueTeamController::showGame()` issues a per-user JWT via
  `GameServerService::generatePlayerToken()` and surfaces `room_id`,
  `lobby_code`, `jwt_token`, `game_server_url`, `totalQuestions=18` to
  the view.
- `resources/views/league_team_game.blade.php` now feeds those values
  into `partials.game-context` and loads the canonical
  socket.io / `DuoSocketClient.js` / `GameplayRuntime.js` triplet
  (mirrors `resources/views/master/game-question.blade.php`). The
  runtime joins the room on page load — `[GameplayRuntime] Joined room`
  is now emitted in League Team browser sessions.
- Server-to-server finalize: `POST /internal/league/team/match/finalize`
  is registered (CSRF-exempt, JWT-verified with `purpose=internal_finalize`)
  and routes to `LeagueTeamController::internalFinalize`, which calls
  `LeagueTeamService::finalizeMatch()` (visibility lifted private→public).
  The Node side (`InternalLaravelClient.notifyMatchFinalized`) is now
  mode-aware and routes `LEAGUE_TEAM` rooms to that endpoint;
  `GameOrchestrator` forwards `room.state.config.mode` for both
  `DUO` and `LEAGUE_TEAM` rooms.

### What still blocks the live-stats e2e spec (Phase B work)

Phase A intentionally kept the legacy REST gameplay loop
(`/api/league/team/match/{id}/question` | `/buzz` | `/submit-answer`) as
the authoritative driver of question, buzz, and answer state. Until the
buzz/answer endpoints forward their events through `GameOrchestrator`,
the `[data-stat][data-player]` slots in `league_team_game.blade.php`
will not tick mid-match — the WS connection is established but
`player_stats_updated` / `round_stats` are not yet emitted for League
Team rooms.

Out of scope for Task #50 and still required before un-skipping the
spec:

- Phase B: re-route `LeagueTeamController::processBuzz()` and
  `submitAnswer()` (or a successor service) through the orchestrator so
  per-player + per-round stats are broadcast.
- `BotPlayerService` extension to spawn 9 bot teammates/opponents for a
  full 5v5 fixture.
- The Playwright spec itself, modeled on
  `tests/e2e/duo-join-room.browser.spec.js` /
  `tests/e2e/master-join-room.browser.spec.js` (Task #49 pattern).
