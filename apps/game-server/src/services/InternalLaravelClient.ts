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
 * Notify Laravel that a Duo match has ended and the authoritative result
 * is available in Redis. Fire-and-forget: errors are logged but never thrown.
 *
 * Used as a safety net when the front-end is no longer connected (disconnect /
 * timeout / natural end with closed browser). The corresponding endpoint reads
 * `gs:match:{roomId}:result` from Redis and routes through DuoMatchmakingService::finishMatch.
 */
export async function notifyMatchFinalized(roomId: string): Promise<void> {
  try {
    const token = signInternalToken();
    const response = await fetch(`${LARAVEL_ORIGIN}/internal/duo/match/finalize`, {
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
        `[InternalLaravelClient] finalize ${roomId} failed: HTTP ${response.status} ${text}`
      );
      return;
    }
    console.log(
      `[InternalLaravelClient] finalize ${roomId} → HTTP ${response.status}`
    );
  } catch (err) {
    console.error(
      `[InternalLaravelClient] finalize ${roomId} threw:`,
      err instanceof Error ? err.message : err
    );
  }
}
