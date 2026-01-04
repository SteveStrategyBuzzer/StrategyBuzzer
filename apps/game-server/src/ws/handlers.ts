import type { Server as SocketIOServer, Socket } from "socket.io";
import type { RoomManager } from "../services/RoomManager.js";
import type { GameOrchestrator } from "../services/GameOrchestrator.js";
import type { GameState, Player } from "../../../../packages/shared/src/types.js";
import type { GameEvent } from "../../../../packages/shared/src/events.js";
import { verifyJWT, type PlayerTokenPayload } from "../middleware/auth.js";
import { rateLimiter } from "../middleware/rateLimiter.js";
import { rehydrateRoom, canRecoverRoom } from "../services/RoomRecovery.js";
import { validateEvent } from "../validation/validate.js";
import { MetricsService } from "../services/MetricsService.js";
import {
  JoinRoomSchema,
  BuzzSchema,
  AnswerSchema,
  SkillSchema,
  ReadySchema,
  VoiceOfferSchema,
  VoiceAnswerSchema,
  VoiceCandidateSchema,
  PingCheckSchema,
  type JoinRoomPayload,
  type BuzzPayload,
  type AnswerPayload,
  type SkillPayload,
  type ReadyPayload,
  type VoiceOfferPayload,
  type VoiceAnswerPayload,
  type VoiceCandidatePayload,
  type PingCheckPayload,
} from "../validation/schemas.js";

function extractScores(players: Record<string, Player>): Record<string, number> {
  const scores: Record<string, number> = {};
  for (const [id, player] of Object.entries(players)) {
    scores[id] = player.score;
  }
  return scores;
}

function extractRoundScores(players: Record<string, Player>): Record<string, number> {
  const roundScores: Record<string, number> = {};
  for (const [id, player] of Object.entries(players)) {
    roundScores[id] = player.roundScore;
  }
  return roundScores;
}

export function setupSocketHandlers(io: SocketIOServer, roomManager: RoomManager, gameOrchestrator: GameOrchestrator): void {
  io.on("connection", (socket: Socket) => {
    console.log(`[WS] Client connected: ${socket.id}`);
    
    let currentRoomId: string | null = null;
    let currentPlayerId: string | null = null;
    let authenticatedPayload: PlayerTokenPayload | null = (socket as any).playerData || null;

    socket.on("join_room", async (data: unknown) => {
      MetricsService.incrementEventReceived("join_room");
      const result = validateEvent(JoinRoomSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for join_room:", result.error.issues);
        MetricsService.incrementValidationError("join_room");
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid join_room payload" });
        return;
      }
      const payload = result.data;
      
      try {
        if (payload.token) {
          const tokenPayload = verifyJWT(payload.token);
          if (!tokenPayload) {
            MetricsService.incrementAuthError();
            MetricsService.incrementEventsFailed();
            socket.emit("error", { code: "INVALID_TOKEN", message: "Invalid or expired token" });
            return;
          }
          authenticatedPayload = tokenPayload;
          console.log(`[WS] Token verified for player: ${tokenPayload.playerName}`);
        }

        let roomId = payload.roomId;
        
        if (!roomId && payload.lobbyCode) {
          roomId = roomManager.getRoomIdByCode(payload.lobbyCode.toUpperCase());
        }
        
        if (!roomId) {
          MetricsService.incrementRoomNotFoundError();
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
          return;
        }

        if (!roomManager.hasRoom(roomId)) {
          console.log(`[WS] Room ${roomId} not in memory, attempting recovery...`);
          const canRecover = await canRecoverRoom(roomId);
          if (canRecover) {
            const recoveredRoom = await rehydrateRoom(roomManager, roomId);
            if (!recoveredRoom) {
              MetricsService.incrementEventsFailed();
              socket.emit("error", { code: "RECOVERY_FAILED", message: "Failed to recover room" });
              return;
            }
            console.log(`[WS] Room ${roomId} recovered successfully`);
          } else {
            MetricsService.incrementRoomNotFoundError();
            MetricsService.incrementEventsFailed();
            socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
            return;
          }
        }

        const playerId = authenticatedPayload?.playerId?.toString() || payload.playerId;
        const playerName = authenticatedPayload?.playerName || payload.playerName;
        const avatarId = authenticatedPayload?.avatarId || payload.avatarId;
        
        const event = roomManager.joinRoom(roomId, playerId, playerName, {
          avatarId: avatarId,
          strategicAvatarId: payload.strategicAvatarId,
          division: payload.division,
        });
        
        if (!event) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "JOIN_FAILED", message: "Could not join room" });
          return;
        }
        
        currentRoomId = roomId;
        currentPlayerId = playerId;
        
        socket.join(roomId);
        
        const state = roomManager.getState(roomId);
        socket.emit("state", { state });
        
        if (state) {
          socket.emit("phase_changed", {
            phase: state.phase,
            phaseEndsAtMs: state.phaseEndsAtMs,
            questionIndex: state.questionIndex,
            roundNumber: state.currentRound,
          });
        }
        
        socket.to(roomId).emit("event", { event });
        MetricsService.incrementEventsProcessed();
        
        console.log(`[WS] Player ${playerName} joined room ${roomId}`);
      } catch (error) {
        console.error("[WS] Error joining room:", error);
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "JOIN_ERROR", message: "Error joining room" });
      }
    });

    socket.on("buzz", async (data: unknown) => {
      MetricsService.incrementEventReceived("buzz");
      const result = validateEvent(BuzzSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for buzz:", result.error.issues);
        MetricsService.incrementValidationError("buzz");
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid buzz payload" });
        return;
      }
      const payload = result.data;
      
      try {
        if (!currentPlayerId) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        const rateLimitResult = await rateLimiter.canBuzz(currentPlayerId, payload.roomId);
        if (!rateLimitResult.allowed) {
          console.log(`[WS] Rate limited buzz from ${currentPlayerId}: ${rateLimitResult.reason}`);
          socket.emit("rate_limited", { event: "buzz", reason: rateLimitResult.reason });
          return;
        }
        
        const buzzLatency = Date.now() - payload.clientTimeMs;
        MetricsService.recordBuzzLatency(buzzLatency);
        
        gameOrchestrator.handleBuzz(payload.roomId, currentPlayerId, payload.clientTimeMs);
        MetricsService.incrementEventsProcessed();
      } catch (error) {
        console.error("[WS] Error processing buzz:", error);
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "BUZZ_ERROR", message: "Error processing buzz" });
      }
    });

    socket.on("answer", async (data: unknown) => {
      MetricsService.incrementEventReceived("answer");
      const answerReceivedAt = Date.now();
      const result = validateEvent(AnswerSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for answer:", result.error.issues);
        MetricsService.incrementValidationError("answer");
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid answer payload" });
        return;
      }
      const payload = result.data;
      
      try {
        if (!currentPlayerId) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        const room = roomManager.getRoom(payload.roomId);
        if (!room) {
          MetricsService.incrementRoomNotFoundError();
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
          return;
        }
        
        if (room.state.lockedAnswerPlayerId !== currentPlayerId) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_YOUR_TURN", message: "Not your turn to answer" });
          return;
        }
        
        const rateLimitResult = await rateLimiter.canAnswer(currentPlayerId, payload.roomId);
        if (!rateLimitResult.allowed) {
          console.log(`[WS] Rate limited answer from ${currentPlayerId}: ${rateLimitResult.reason}`);
          socket.emit("rate_limited", { event: "answer", reason: rateLimitResult.reason });
          return;
        }
        
        if (room.state.phaseStartedAtMs) {
          const answerLatency = answerReceivedAt - room.state.phaseStartedAtMs;
          MetricsService.recordAnswerLatency(answerLatency);
        }
        
        console.log(`[WS] Answer from ${currentPlayerId}: ${payload.answer}`);
        socket.emit("answer_received", { success: true });
        
        gameOrchestrator.handleAnswer(payload.roomId, currentPlayerId, payload.answer);
        MetricsService.incrementEventsProcessed();
      } catch (error) {
        console.error("[WS] Error processing answer:", error);
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "ANSWER_ERROR", message: "Error processing answer" });
      }
    });

    socket.on("skill", async (data: unknown) => {
      MetricsService.incrementEventReceived("skill");
      const result = validateEvent(SkillSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for skill:", result.error.issues);
        MetricsService.incrementValidationError("skill");
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid skill payload" });
        return;
      }
      const payload = result.data;
      
      try {
        if (!currentPlayerId) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        const rateLimitResult = await rateLimiter.canUseSkill(currentPlayerId, payload.roomId);
        if (!rateLimitResult.allowed) {
          console.log(`[WS] Rate limited skill from ${currentPlayerId}: ${rateLimitResult.reason}`);
          socket.emit("rate_limited", { event: "skill", reason: rateLimitResult.reason });
          return;
        }
        
        console.log(`[WS] Skill ${payload.skillId} from ${currentPlayerId}`);
        MetricsService.incrementEventsProcessed();
        
      } catch (error) {
        console.error("[WS] Error processing skill:", error);
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "SKILL_ERROR", message: "Error processing skill" });
      }
    });

    socket.on("ready", (data: unknown) => {
      MetricsService.incrementEventReceived("ready");
      const result = validateEvent(ReadySchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for ready:", result.error.issues);
        MetricsService.incrementValidationError("ready");
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid ready payload" });
        return;
      }
      const payload = result.data;
      
      try {
        if (!currentPlayerId) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        console.log(`[WS] Player ${currentPlayerId} ready: ${payload.isReady}`);
        
        io.to(payload.roomId).emit("player_ready", {
          playerId: currentPlayerId,
          isReady: payload.isReady,
        });
        MetricsService.incrementEventsProcessed();
      } catch (error) {
        console.error("[WS] Error processing ready:", error);
        MetricsService.incrementEventsFailed();
      }
    });

    socket.on("voice_offer", (data: unknown) => {
      const result = validateEvent(VoiceOfferSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for voice_offer:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid voice_offer payload" });
        return;
      }
      const payload = result.data;
      
      if (!currentRoomId || !authenticatedPayload) {
        socket.emit("error", { code: "UNAUTHORIZED", message: "Not authenticated or not in a room" });
        return;
      }
      socket.to(currentRoomId).emit("voice_offer", {
        from: currentPlayerId,
        offer: payload.offer,
      });
    });

    socket.on("voice_answer", (data: unknown) => {
      const result = validateEvent(VoiceAnswerSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for voice_answer:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid voice_answer payload" });
        return;
      }
      const payload = result.data;
      
      if (!currentRoomId || !authenticatedPayload) {
        socket.emit("error", { code: "UNAUTHORIZED", message: "Not authenticated or not in a room" });
        return;
      }
      socket.to(currentRoomId).emit("voice_answer", {
        from: currentPlayerId,
        answer: payload.answer,
      });
    });

    socket.on("voice_ice_candidate", (data: unknown) => {
      const result = validateEvent(VoiceCandidateSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for voice_ice_candidate:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid voice_ice_candidate payload" });
        return;
      }
      const payload = result.data;
      
      if (!currentRoomId || !authenticatedPayload) {
        socket.emit("error", { code: "UNAUTHORIZED", message: "Not authenticated or not in a room" });
        return;
      }
      socket.to(currentRoomId).emit("voice_ice_candidate", {
        from: currentPlayerId,
        candidate: payload.candidate,
      });
    });

    socket.on("disconnect", () => {
      console.log(`[WS] Client disconnected: ${socket.id}`);
      
      if (currentRoomId && currentPlayerId) {
        const event = roomManager.leaveRoom(currentRoomId, currentPlayerId);
        if (event) {
          socket.to(currentRoomId).emit("event", { event });
        }
      }
    });

    socket.on("ping_check", async (data: unknown) => {
      const result = validateEvent(PingCheckSchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for ping_check:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid ping_check payload" });
        return;
      }
      const payload = result.data;
      
      if (currentPlayerId && currentRoomId) {
        const rateLimitResult = await rateLimiter.canPingCheck(currentPlayerId, currentRoomId);
        if (!rateLimitResult.allowed) {
          socket.emit("rate_limited", { event: "ping_check", reason: rateLimitResult.reason });
          return;
        }
      }
      
      socket.emit("pong_check", {
        clientTime: payload.clientTime,
        serverTime: Date.now(),
      });
    });
  });
}

export function emitPhaseChanged(io: SocketIOServer, roomId: string, state: GameState): void {
  io.to(roomId).emit("phase_changed", {
    phase: state.phase,
    phaseEndsAtMs: state.phaseEndsAtMs,
    questionIndex: state.questionIndex,
    roundNumber: state.currentRound,
    lockedPlayerId: state.lockedAnswerPlayerId,
  });
}

export function emitScoreUpdate(io: SocketIOServer, roomId: string, state: GameState): void {
  io.to(roomId).emit("score_update", {
    scores: extractScores(state.players),
    roundScores: extractRoundScores(state.players),
  });
}
