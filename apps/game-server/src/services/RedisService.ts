import { createRequire } from "module";

const require = createRequire(import.meta.url);

// ioredis est souvent CommonJS; en ESM, on le charge via require()
const IORedisPkg = require("ioredis");
const RedisCtor = (IORedisPkg?.default ?? IORedisPkg) as any;

const REDIS_URL = process.env.REDIS_URL || "redis://127.0.0.1:6379";
const TTL_SECONDS = Number(process.env.ROOM_STATE_TTL_SECONDS || 7200); // 2h par défaut

// Clients Redis
export const redisPub = new RedisCtor(REDIS_URL);
export const redisSub = new RedisCtor(REDIS_URL);
export const redisClient = new RedisCtor(REDIS_URL);

// Helpers de clés
function kState(roomId: string) {
  return `room:${roomId}:state`;
}
function kUsed(roomId: string) {
  return `room:${roomId}:used_questions`;
}
function kEvents(roomId: string) {
  return `room:${roomId}:events`;
}

// Room state storage
export async function setRoomState(roomId: string, state: object): Promise<void> {
  await redisClient.set(kState(roomId), JSON.stringify(state), "EX", TTL_SECONDS);
}

export async function getRoomState(roomId: string): Promise<object | null> {
  const data = await redisClient.get(kState(roomId));
  return data ? JSON.parse(data) : null;
}

export async function deleteRoomState(roomId: string): Promise<void> {
  await redisClient.del(kState(roomId));
}

// Used question IDs tracking (anti-duplication)
export async function addUsedQuestionId(roomId: string, questionId: string): Promise<void> {
  await redisClient.sadd(kUsed(roomId), questionId);
  await redisClient.expire(kUsed(roomId), TTL_SECONDS);
}

export async function getUsedQuestionIds(roomId: string): Promise<string[]> {
  return await redisClient.smembers(kUsed(roomId));
}

export async function isQuestionUsed(roomId: string, questionId: string): Promise<boolean> {
  return (await redisClient.sismember(kUsed(roomId), questionId)) === 1;
}

// Event log for crash recovery
export async function appendEventLog(roomId: string, event: object): Promise<void> {
  await redisClient.rpush(kEvents(roomId), JSON.stringify(event));
  await redisClient.expire(kEvents(roomId), TTL_SECONDS);
}

export async function getEventLog(roomId: string): Promise<object[]> {
  const events = await redisClient.lrange(kEvents(roomId), 0, -1);
  return events.map((e: string) => JSON.parse(e));
}

// Cleanup all room data
export async function cleanupRoom(roomId: string): Promise<void> {
  await redisClient.del(kState(roomId));
  await redisClient.del(kUsed(roomId));
  await redisClient.del(kEvents(roomId));
}

// Health check
export async function ping(): Promise<boolean> {
  try {
    const result = await redisClient.ping();
    return result === "PONG";
  } catch {
    return false;
  }
}
