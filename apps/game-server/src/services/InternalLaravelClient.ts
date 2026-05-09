import jwt from "jsonwebtoken";
import { getJWTSecret } from "../middleware/auth.js";

const LARAVEL_ORIGIN = process.env.LARAVEL_ORIGIN || "http://localhost:5000";
const INTERNAL_TOKEN_TTL_SECONDS = 60;

function signInternalToken(): string {
  const secret = getJWTSecret();
  const issuedAt = Math.floor(Date.now() / 1000);
  const payload = {
    purpose: "internal_finalize",
    iss: "game-server",
    iat: issuedAt,
    exp: issuedAt + INTERNAL_TOKEN_TTL_SECONDS,
  };
  return jwt.sign(payload, secret, { algorithm: "HS256" });
}

/**
 * Resolve the Laravel finalize endpoint for a given game-server mode. Each
 * mode-specific controller verifies the same internal_finalize JWT and
 * routes the room result through its own finalization service.
 *
 * Falls back to the Duo endpoint for modes that share its match record shape
 * (LEAGUE_INDIVIDUAL, MASTER) — those continue to use DuoController-style
 * finalization until they get their own dedicated endpoint.
 */
function finalizePathForMode(mode?: string): string {
  switch (mode) {
    case "LEAGUE_TEAM":
      return "/internal/league/team/match/finalize";
    case "DUO":
    case "LEAGUE_INDIVIDUAL":
    case "MASTER":
    default:
      return "/internal/duo/match/finalize";
  }
}

/**
 * Notify Laravel that a match has ended and the authoritative result is
 * available in Redis. Fire-and-forget: errors are logged but never thrown.
 *
 * Used as a safety net when the front-end is no longer connected (disconnect /
 * timeout / natural end with closed browser). The corresponding endpoint reads
 * `gs:match:{roomId}:result` from Redis (or, for LEAGUE_TEAM, falls back to
 * the persisted game_state) and routes through the mode-specific finalization
 * service.
 *
 * @param roomId - The game-server room id (UUID).
 * @param mode   - Optional room mode tag ("DUO" | "LEAGUE_INDIVIDUAL" |
 *                 "LEAGUE_TEAM" | "MASTER"). Routing falls back to the Duo
 *                 endpoint when omitted, matching pre-Task-#50 behavior.
 */
/**
 * Persist a mid-match checkpoint to Postgres (via Laravel) so the state
 * can be recovered if Redis is lost between rounds.
 * Fire-and-forget: errors are logged but never thrown.
 */
export async function saveMatchSnapshot(
  roomId: string,
  mode: string,
  roundNumber: number,
  playerScores: Record<string, number>,
  roundsWon: Record<string, number>,
  playerStats: Record<string, unknown>
): Promise<void> {
  try {
    const token = signInternalToken();
    const response = await fetch(`${LARAVEL_ORIGIN}/internal/match/snapshot`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ roomId, mode, roundNumber, playerScores, roundsWon, playerStats }),
    });

    if (!response.ok) {
      const text = await response.text().catch(() => "");
      console.error(
        `[InternalLaravelClient] snapshot ${roomId} R${roundNumber} failed: HTTP ${response.status} ${text}`
      );
    }
  } catch (err) {
    console.error(
      `[InternalLaravelClient] snapshot ${roomId} R${roundNumber} threw:`,
      err instanceof Error ? err.message : err
    );
  }
}

/**
 * Record player memory for a completed match so Laravel can update
 * per-player question-diversity tracking in Redis.
 *
 * Laravel resolves player user_ids and group_ids from its own cached data —
 * Node sends only roomId + mode. Strictly fire-and-forget:
 *   - 5-second timeout via AbortController
 *   - try/catch with warn-only log
 *   - never throws, never blocks endMatch()
 */
export async function recordPlayerMemory(roomId: string, mode: string): Promise<void> {
  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), 5000);
  try {
    const token = signInternalToken();
    const response = await fetch(`${LARAVEL_ORIGIN}/internal/player-memory/record`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ roomId, mode }),
      signal: controller.signal,
    });
    clearTimeout(timeoutId);
    if (!response.ok) {
      const text = await response.text().catch(() => "");
      console.warn(
        `[InternalLaravelClient] player-memory ${roomId} (${mode}) failed: HTTP ${response.status} ${text}`
      );
    }
  } catch (err) {
    clearTimeout(timeoutId);
    if (err instanceof Error && err.name === "AbortError") {
      console.warn(`[InternalLaravelClient] player-memory ${roomId} timed out`);
    } else {
      console.warn(
        `[InternalLaravelClient] player-memory ${roomId} threw:`,
        err instanceof Error ? err.message : err
      );
    }
  }
}

export async function notifyMatchFinalized(roomId: string, mode?: string): Promise<void> {
  const path = finalizePathForMode(mode);
  try {
    const token = signInternalToken();
    const response = await fetch(`${LARAVEL_ORIGIN}${path}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
      },
      body: JSON.stringify({ roomId }),
    });

    const text = await response.text().catch(() => "");
    if (!response.ok && response.status !== 202) {
      console.error(
        `[InternalLaravelClient] finalize ${roomId} (${mode ?? "?"} → ${path}) failed: HTTP ${response.status} ${text}`
      );
      return;
    }
    console.log(
      `[InternalLaravelClient] finalize ${roomId} (${mode ?? "?"} → ${path}) → HTTP ${response.status}`
    );
  } catch (err) {
    console.error(
      `[InternalLaravelClient] finalize ${roomId} (${mode ?? "?"} → ${path}) threw:`,
      err instanceof Error ? err.message : err
    );
  }
}
