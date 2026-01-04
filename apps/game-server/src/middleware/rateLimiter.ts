import { redisClient } from "../services/RedisService.js";

interface PlayerLimits {
  buzzCount: number;
  answerCount: number;
  skillCount: number;
  pingTimestamps: number[];
}

interface RoomLimits {
  eventTimestamps: number[];
}

const RATE_LIMITS = {
  buzz: { maxPerQuestion: 1 },
  answer: { maxPerQuestion: 1 },
  skill: { maxPerMatch: 3 },
  pingCheck: { maxPerSecond: 10 },
  roomEvents: { maxPerSecond: 100 },
};

class RateLimiter {
  private playerLimits: Map<string, PlayerLimits> = new Map();
  private roomLimits: Map<string, RoomLimits> = new Map();

  private getPlayerKey(roomId: string, playerId: string): string {
    return `${roomId}:${playerId}`;
  }

  private getPlayerLimits(roomId: string, playerId: string): PlayerLimits {
    const key = this.getPlayerKey(roomId, playerId);
    if (!this.playerLimits.has(key)) {
      this.playerLimits.set(key, {
        buzzCount: 0,
        answerCount: 0,
        skillCount: 0,
        pingTimestamps: [],
      });
    }
    return this.playerLimits.get(key)!;
  }

  private getRoomLimits(roomId: string): RoomLimits {
    if (!this.roomLimits.has(roomId)) {
      this.roomLimits.set(roomId, {
        eventTimestamps: [],
      });
    }
    return this.roomLimits.get(roomId)!;
  }

  async canBuzz(playerId: string, roomId: string): Promise<{ allowed: boolean; reason?: string }> {
    if (!await this.checkRoomLimit(roomId)) {
      console.log(`[RateLimiter] Room ${roomId} rate limited (DDoS protection)`);
      return { allowed: false, reason: "Too many events in this room" };
    }

    const redisKey = `ratelimit:${roomId}:${playerId}:buzz`;
    const count = await redisClient.get(redisKey);
    
    if (count && parseInt(count) >= RATE_LIMITS.buzz.maxPerQuestion) {
      console.log(`[RateLimiter] Player ${playerId} already buzzed this question in room ${roomId}`);
      return { allowed: false, reason: "Already buzzed this question" };
    }

    await redisClient.incr(redisKey);
    await redisClient.expire(redisKey, 300);
    
    const limits = this.getPlayerLimits(roomId, playerId);
    limits.buzzCount++;
    
    return { allowed: true };
  }

  async canAnswer(playerId: string, roomId: string): Promise<{ allowed: boolean; reason?: string }> {
    if (!await this.checkRoomLimit(roomId)) {
      console.log(`[RateLimiter] Room ${roomId} rate limited (DDoS protection)`);
      return { allowed: false, reason: "Too many events in this room" };
    }

    const redisKey = `ratelimit:${roomId}:${playerId}:answer`;
    const count = await redisClient.get(redisKey);
    
    if (count && parseInt(count) >= RATE_LIMITS.answer.maxPerQuestion) {
      console.log(`[RateLimiter] Player ${playerId} already answered this question in room ${roomId}`);
      return { allowed: false, reason: "Already answered this question" };
    }

    await redisClient.incr(redisKey);
    await redisClient.expire(redisKey, 300);
    
    const limits = this.getPlayerLimits(roomId, playerId);
    limits.answerCount++;
    
    return { allowed: true };
  }

  async canUseSkill(playerId: string, roomId: string): Promise<{ allowed: boolean; reason?: string }> {
    if (!await this.checkRoomLimit(roomId)) {
      console.log(`[RateLimiter] Room ${roomId} rate limited (DDoS protection)`);
      return { allowed: false, reason: "Too many events in this room" };
    }

    const redisKey = `ratelimit:${roomId}:${playerId}:skill`;
    const count = await redisClient.get(redisKey);
    
    if (count && parseInt(count) >= RATE_LIMITS.skill.maxPerMatch) {
      console.log(`[RateLimiter] Player ${playerId} used max skills in room ${roomId}`);
      return { allowed: false, reason: "Maximum skills used this match" };
    }

    await redisClient.incr(redisKey);
    await redisClient.expire(redisKey, 7200);
    
    const limits = this.getPlayerLimits(roomId, playerId);
    limits.skillCount++;
    
    return { allowed: true };
  }

  async canPingCheck(playerId: string, roomId: string): Promise<{ allowed: boolean; reason?: string }> {
    const now = Date.now();
    const oneSecondAgo = now - 1000;
    
    const limits = this.getPlayerLimits(roomId, playerId);
    limits.pingTimestamps = limits.pingTimestamps.filter(ts => ts > oneSecondAgo);
    
    if (limits.pingTimestamps.length >= RATE_LIMITS.pingCheck.maxPerSecond) {
      console.log(`[RateLimiter] Player ${playerId} ping rate limited in room ${roomId}`);
      return { allowed: false, reason: "Too many ping requests" };
    }
    
    limits.pingTimestamps.push(now);
    return { allowed: true };
  }

  private async checkRoomLimit(roomId: string): Promise<boolean> {
    const now = Date.now();
    const oneSecondAgo = now - 1000;
    
    const limits = this.getRoomLimits(roomId);
    limits.eventTimestamps = limits.eventTimestamps.filter(ts => ts > oneSecondAgo);
    
    if (limits.eventTimestamps.length >= RATE_LIMITS.roomEvents.maxPerSecond) {
      return false;
    }
    
    limits.eventTimestamps.push(now);
    
    const redisKey = `ratelimit:${roomId}:events`;
    const multi = redisClient.multi();
    multi.incr(redisKey);
    multi.expire(redisKey, 2);
    await multi.exec();
    
    return true;
  }

  async resetForQuestion(roomId: string): Promise<void> {
    console.log(`[RateLimiter] Resetting question limits for room ${roomId}`);
    
    const pattern = `ratelimit:${roomId}:*:buzz`;
    const buzzKeys = await redisClient.keys(pattern);
    if (buzzKeys.length > 0) {
      await redisClient.del(...buzzKeys);
    }
    
    const answerPattern = `ratelimit:${roomId}:*:answer`;
    const answerKeys = await redisClient.keys(answerPattern);
    if (answerKeys.length > 0) {
      await redisClient.del(...answerKeys);
    }
    
    for (const [key, limits] of this.playerLimits.entries()) {
      if (key.startsWith(`${roomId}:`)) {
        limits.buzzCount = 0;
        limits.answerCount = 0;
      }
    }
  }

  async resetForMatch(roomId: string): Promise<void> {
    console.log(`[RateLimiter] Resetting all limits for room ${roomId}`);
    
    const pattern = `ratelimit:${roomId}:*`;
    const keys = await redisClient.keys(pattern);
    if (keys.length > 0) {
      await redisClient.del(...keys);
    }
    
    for (const key of this.playerLimits.keys()) {
      if (key.startsWith(`${roomId}:`)) {
        this.playerLimits.delete(key);
      }
    }
    
    this.roomLimits.delete(roomId);
  }

  async cleanupRoom(roomId: string): Promise<void> {
    await this.resetForMatch(roomId);
  }
}

export const rateLimiter = new RateLimiter();
