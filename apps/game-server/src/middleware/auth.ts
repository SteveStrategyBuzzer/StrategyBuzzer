import jwt from "jsonwebtoken";

export type PlayerTokenPayload = {
  playerId: number;
  playerName: string;
  avatarId: string;
  roomId: string;
  exp: number;
};

const JWT_SECRET = process.env.GAME_SERVER_JWT_SECRET || "game-server-default-secret";

export function verifyJWT(token: string): PlayerTokenPayload | null {
  try {
    const decoded = jwt.verify(token, JWT_SECRET) as PlayerTokenPayload;
    return decoded;
  } catch (error) {
    console.error("[Auth] JWT verification failed:", error instanceof Error ? error.message : error);
    return null;
  }
}

export function getJWTSecret(): string {
  return JWT_SECRET;
}
