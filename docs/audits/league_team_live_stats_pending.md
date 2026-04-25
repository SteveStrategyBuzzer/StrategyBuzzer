# League Team — Live Stats Pipeline (Pending)

**Status:** ⚠️ TEMPORAIRE — League Team reste à migrer vers Node-authoritative
avant live stats WS.

**Last updated:** 2026-04-25
**Scope:** Task #39 / continuation of cancelled #42 (wire shared
GameplayRuntime into views that extend `layouts.app`).

## Summary

The League Team gameplay view (`resources/views/league_team_game.blade.php`)
already renders the full set of `[data-stat][data-player]` slots needed for
live in-match telemetry (efficiency %, current streak, average buzz time,
correct / wrong / buzz tallies, score) for every member of both teams.

However those slots stay frozen at their default values during play because
the League Team mode does **not** currently run on the Node WebSocket game
server — it has no live-stats source to bind to. Per the server-authoritative
live-stats contract, REST polling is explicitly forbidden as a substitute, so
the slots intentionally remain idle until the backend is migrated.

## Current State

| Concern                                          | Status |
| ------------------------------------------------ | :----: |
| `[data-stat][data-player]` slots in the Blade view | ✅ Ready |
| `partials.game-context` included                 | ✅ Yes (placeholder values) |
| Node room created for the match                  | ❌ Never |
| Per-user JWT issued for the Node WS              | ❌ Never |
| Buzz / answer routed through `GameOrchestrator`  | ❌ No (REST only) |
| `player_stats_updated` / `round_stats` / `match_stats` emitted | ❌ No source |
| Client-side `GameplayRuntime` loaded             | ❌ Intentionally not loaded |

The view currently includes `partials.game-context` with `$jwt_token ?? ''`
and `$match->id` as the room id. The controller never sets `$jwt_token`, so
even if `GameplayRuntime` were loaded it would bail at the
`if (!ROOM_ID || !JWT_TOKEN) return;` guard.

The Master mode wiring committed alongside this audit shows the target
shape: see `resources/views/master/game-question.blade.php` (script tags at
the bottom of `@section('content')`) and
`app/Http/Controllers/MasterGameController.php::renderQuestionView` lines
2076–2122 (room id + JWT generation).

## Why we cannot just load the runtime today

1. There is no Node room for the match → the runtime would call
   `joinRoom()` against an unknown room id and the server would refuse.
2. There is no JWT for the player → the runtime would refuse to even open
   the socket.
3. There is no source for `player_stats_updated` events → even if a room
   somehow existed, no buzz/answer flows through the orchestrator, so the
   stats pipeline produces nothing.

A REST-polling fallback is not acceptable — it would diverge from the
single-source-of-truth model used by Duo and Master and is explicitly
disallowed.

## Required Server-Side Prerequisites

These must land before `GameplayRuntime` can be wired into
`league_team_game.blade.php`:

1. **Create a Node room when the match starts.**
   Extend `App\Services\LeagueTeamService::initializeTeamMatch()` to call
   the Node game server `POST /rooms` with `mode=LEAGUE_TEAM` (already a
   valid `Mode` in `packages/shared/src/types.ts`) and persist the returned
   `room_id` on the `LeagueTeamMatch` record.
2. **Issue a per-user JWT and pass it to the view.**
   Update `App\Http\Controllers\LeagueTeamController::game()` (the action
   that renders `league_team_game`) to call
   `GameServerService::generatePlayerToken($user->id, $match->room_id)` and
   pass `room_id` + `jwt_token` to the view, the same way
   `MasterGameController::renderQuestionView()` does today.
3. **Forward gameplay events to the orchestrator.**
   `App\Services\LeagueTeamService::processBuzz()` and `submitAnswer()` (or
   their underlying helpers) must dispatch the same events the Node
   `GameOrchestrator` already understands so it produces
   `player_stats_updated`, `round_stats`, and `match_stats` for the room
   participants. The existing REST endpoints can keep working for legacy
   clients during the migration but the source of truth must shift to the
   orchestrator.

## Client-Side Activation (after the prerequisites)

Once the three items above are done, the only client change needed is to
append the same three script tags Master uses, at the bottom of
`@section('content')` in `resources/views/league_team_game.blade.php`:

```blade
<script>
(function () {
    var ctx = window.SB_GAME_CONTEXT || {};
    if (ctx.gameServerUrl) {
        window.GAME_SERVER_URL = ctx.gameServerUrl;
    } else if (!window.GAME_SERVER_URL) {
        window.GAME_SERVER_URL = window.location.protocol + '//' + window.location.hostname + ':3001';
    }
})();
</script>
<script src="https://cdn.socket.io/4.7.5/socket.io.min.js"></script>
<script src="{{ asset('js/DuoSocketClient.js') }}"></script>
<script src="{{ asset('js/GameplayRuntime.js') }}"></script>
```

No Blade-side stat math is allowed and no REST polling may be added.
`GameplayRuntime` will pick up the existing `[data-stat][data-player]`
slots automatically.

## References

- View: `resources/views/league_team_game.blade.php`
- Controller: `app/Http/Controllers/LeagueTeamController.php`
- Service: `app/Services/LeagueTeamService.php`
- Master comparison: `resources/views/master/game-question.blade.php`,
  `app/Http/Controllers/MasterGameController.php` (lines 2076–2122)
- Runtime consumer: `public/js/GameplayRuntime.js` (`player_stats_updated`,
  `round_stats`, `match_stats` handlers around lines 526–547)
- Mode enum: `packages/shared/src/types.ts` (`LEAGUE_TEAM`)
- Node room creation HTTP: `apps/game-server/src/http/routes.ts`
  (`POST /rooms`)
