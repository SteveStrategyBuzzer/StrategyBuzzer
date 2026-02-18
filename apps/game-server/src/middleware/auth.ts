import jwt from "jsonwebtoken";

export type PlayerTokenPayload = {
  playerId: string;
  pseudonym?: string;
  playerName?: string;
  avatarId?: string;
  roomCode?: string;
  iat?: number;
  exp?: number;
};

const JWT_SECRET = process.env.GAME_SERVER_JWT_SECRET;

if (!JWT_SECRET || JWT_SECRET.trim().length < 16) {
  console.error("[FATAL] Missing or weak GAME_SERVER_JWT_SECRET. Refusing to start (mirror strict Replit). ");
  process.exit(1);
}

const JWT_SECRET_STR: string = JWT_SECRET;

if (!JWT_SECRET || JWT_SECRET.trim().length < 16) {
  console.error(
    "[FATAL] Missing or weak GAME_SERVER_JWT_SECRET. Refusing to start (mirror strict Replit)."
  );
  process.exit(1);
}

export function verifyJWT(token: string): PlayerTokenPayload | null {
  try {
    const decoded = jwt.verify(token, JWT_SECRET_STR) as unknown as PlayerTokenPayload;
    return decoded;
  } catch (error) {
    console.error(
      "[Auth] JWT verification failed:",
      error instanceof Error ? error.message : error
    );
    return null;
  }
}

export function getJWTSecret(): string {
  return JWT_SECRET_STR;
}
