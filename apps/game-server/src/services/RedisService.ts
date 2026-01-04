import Redis from "ioredis";

const REDIS_URL = process.env.REDIS_URL || "redis://127.0.0.1:6379";

// Create Redis clients for pub/sub and general operations
export const redisPub = new Redis(REDIS_URL);
export const redisSub = new Redis(REDIS_URL);
export const redisClient = new Redis(REDIS_URL);

// Room state storage
export async function setRoomState(roomId: string, state: object): Promise<void> {
  await redisClient.set(`room:${roomId}:state`, JSON.stringify(state), "EX", 7200); // 2h TTL
}

export async function getRoomState(roomId: string): Promise<object | null> {
  const data = await redisClient.get(`room:${roomId}:state`);
  return data ? JSON.parse(data) : null;
}

export async function deleteRoomState(roomId: string): Promise<void> {
  await redisClient.del(`room:${roomId}:state`);
}

// Used question IDs tracking (anti-duplication)
export async function addUsedQuestionId(roomId: string, questionId: string): Promise<void> {
  await redisClient.sadd(`room:${roomId}:used_questions`, questionId);
  await redisClient.expire(`room:${roomId}:used_questions`, 7200);
}

export async function getUsedQuestionIds(roomId: string): Promise<string[]> {
  return await redisClient.smembers(`room:${roomId}:used_questions`);
}

export async function isQuestionUsed(roomId: string, questionId: string): Promise<boolean> {
  return (await redisClient.sismember(`room:${roomId}:used_questions`, questionId)) === 1;
}

// Event log for crash recovery
export async function appendEventLog(roomId: string, event: object): Promise<void> {
  await redisClient.rpush(`room:${roomId}:events`, JSON.stringify(event));
  await redisClient.expire(`room:${roomId}:events`, 7200);
}

export async function getEventLog(roomId: string): Promise<object[]> {
  const events = await redisClient.lrange(`room:${roomId}:events`, 0, -1);
  return events.map((e: string) => JSON.parse(e));
}

// Cleanup all room data
export async function cleanupRoom(roomId: string): Promise<void> {
  await redisClient.del(`room:${roomId}:state`);
  await redisClient.del(`room:${roomId}:used_questions`);
  await redisClient.del(`room:${roomId}:events`);
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
