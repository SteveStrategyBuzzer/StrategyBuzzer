import type { Server as SocketIOServer, Socket } from "socket.io";
import type { RoomManager } from "../services/RoomManager.js";
import type { GameState, Player } from "../../../../packages/shared/src/types.js";
import type { GameEvent } from "../../../../packages/shared/src/events.js";
import { verifyJWT, type PlayerTokenPayload } from "../middleware/auth.js";

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

type JoinRoomPayload = {
  roomId?: string;
  lobbyCode?: string;
  playerId: string;
  playerName: string;
  avatarId?: string;
  strategicAvatarId?: string;
  division?: string;
  token?: string;
};

type BuzzPayload = {
  roomId: string;
  clientTimeMs: number;
};

type AnswerPayload = {
  roomId: string;
  answer: number | string | boolean;
};

type SkillPayload = {
  roomId: string;
  skillId: string;
  targetPlayerId?: string;
};

export function setupSocketHandlers(io: SocketIOServer, roomManager: RoomManager): void {
  io.on("connection", (socket: Socket) => {
    console.log(`[WS] Client connected: ${socket.id}`);
    
    let currentRoomId: string | null = null;
    let currentPlayerId: string | null = null;
    let authenticatedPayload: PlayerTokenPayload | null = (socket as any).playerData || null;

    socket.on("join_room", (payload: JoinRoomPayload) => {
      try {
        if (payload.token) {
          const tokenPayload = verifyJWT(payload.token);
          if (!tokenPayload) {
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
          socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
          return;
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
        
        console.log(`[WS] Player ${playerName} joined room ${roomId}`);
      } catch (error) {
        console.error("[WS] Error joining room:", error);
        socket.emit("error", { code: "JOIN_ERROR", message: "Error joining room" });
      }
    });

    socket.on("buzz", (payload: BuzzPayload) => {
      try {
        if (!currentPlayerId) {
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        const event = roomManager.registerBuzz(payload.roomId, currentPlayerId, payload.clientTimeMs);
        
        if (!event) {
          socket.emit("error", { code: "BUZZ_FAILED", message: "Could not register buzz" });
          return;
        }
        
        io.to(payload.roomId).emit("event", { event });
        
        const state = roomManager.getState(payload.roomId);
        if (state && state.phase === "ANSWER_SELECTION") {
          io.to(payload.roomId).emit("phase_changed", {
            phase: state.phase,
            lockedPlayerId: state.lockedAnswerPlayerId,
            phaseEndsAtMs: state.phaseEndsAtMs,
          });
        }
      } catch (error) {
        console.error("[WS] Error processing buzz:", error);
        socket.emit("error", { code: "BUZZ_ERROR", message: "Error processing buzz" });
      }
    });

    socket.on("answer", (payload: AnswerPayload) => {
      try {
        if (!currentPlayerId) {
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        const room = roomManager.getRoom(payload.roomId);
        if (!room) {
          socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
          return;
        }
        
        if (room.state.lockedAnswerPlayerId !== currentPlayerId) {
          socket.emit("error", { code: "NOT_YOUR_TURN", message: "Not your turn to answer" });
          return;
        }
        
        console.log(`[WS] Answer from ${currentPlayerId}: ${payload.answer}`);
        
        socket.emit("answer_received", { success: true });
        
        const previousScores = extractScores(room.state.players);
        
        const state = roomManager.getState(payload.roomId);
        if (state) {
          const currentScores = extractScores(state.players);
          const scoresChanged = Object.keys(currentScores).some(
            playerId => currentScores[playerId] !== previousScores[playerId]
          );
          
          if (scoresChanged) {
            io.to(payload.roomId).emit("score_update", {
              scores: currentScores,
              roundScores: extractRoundScores(state.players),
            });
          }
          
          io.to(payload.roomId).emit("phase_changed", {
            phase: state.phase,
            phaseEndsAtMs: state.phaseEndsAtMs,
            questionIndex: state.questionIndex,
            roundNumber: state.currentRound,
          });
        }
        
      } catch (error) {
        console.error("[WS] Error processing answer:", error);
        socket.emit("error", { code: "ANSWER_ERROR", message: "Error processing answer" });
      }
    });

    socket.on("skill", (payload: SkillPayload) => {
      try {
        if (!currentPlayerId) {
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        console.log(`[WS] Skill ${payload.skillId} from ${currentPlayerId}`);
        
      } catch (error) {
        console.error("[WS] Error processing skill:", error);
        socket.emit("error", { code: "SKILL_ERROR", message: "Error processing skill" });
      }
    });

    socket.on("ready", (payload: { roomId: string; isReady: boolean }) => {
      try {
        if (!currentPlayerId) {
          socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
          return;
        }
        
        console.log(`[WS] Player ${currentPlayerId} ready: ${payload.isReady}`);
        
        io.to(payload.roomId).emit("player_ready", {
          playerId: currentPlayerId,
          isReady: payload.isReady,
        });
      } catch (error) {
        console.error("[WS] Error processing ready:", error);
      }
    });

    socket.on("voice_offer", (payload: { roomId: string; targetId: string; offer: unknown }) => {
      if (!currentRoomId || !authenticatedPayload) {
        socket.emit("error", { code: "UNAUTHORIZED", message: "Not authenticated or not in a room" });
        return;
      }
      socket.to(currentRoomId).emit("voice_offer", {
        from: currentPlayerId,
        offer: payload.offer,
      });
    });

    socket.on("voice_answer", (payload: { roomId: string; targetId: string; answer: unknown }) => {
      if (!currentRoomId || !authenticatedPayload) {
        socket.emit("error", { code: "UNAUTHORIZED", message: "Not authenticated or not in a room" });
        return;
      }
      socket.to(currentRoomId).emit("voice_answer", {
        from: currentPlayerId,
        answer: payload.answer,
      });
    });

    socket.on("voice_ice_candidate", (payload: { roomId: string; targetId: string; candidate: unknown }) => {
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

    socket.on("ping_check", (payload: { clientTime: number }) => {
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
