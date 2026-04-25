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
