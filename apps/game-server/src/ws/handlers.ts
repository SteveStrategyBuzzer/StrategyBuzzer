import type { Server as SocketIOServer, Socket } from "socket.io";
import type { RoomManager } from "../services/RoomManager.js";
import type { GameOrchestrator } from "../services/GameOrchestrator.js";
import type { GameState, Player } from "@strategybuzzer/shared";
import type { GameEvent } from "@strategybuzzer/shared";
import { buildInitialInventory } from "@strategybuzzer/shared";
import { verifyJWT, type PlayerTokenPayload } from "../middleware/auth.js";
import { rateLimiter } from "../middleware/rateLimiter.js";
import { rehydrateRoom, canRecoverRoom } from "../services/RoomRecovery.js";
import { validateEvent } from "../validation/validate.js";
import { MetricsService } from "../services/MetricsService.js";
import { canActivateSkill, applySkillEffect, applyAnswerPhaseSkill, consumeSkillUse } from "@strategybuzzer/game-engine";
import { getAvatarSkillIds } from "../services/AvatarSkillsMap.js";
import { BotPlayerService } from "../services/BotPlayerService.js";
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
  TimeSyncSchema,
  QuestionPageReadySchema,
  ResultPageReadySchema,
  type JoinRoomPayload,
  type BuzzPayload,
  type AnswerPayload,
  type SkillPayload,
  type ReadyPayload,
  type VoiceOfferPayload,
  type VoiceAnswerPayload,
  type VoiceCandidatePayload,
  type PingCheckPayload,
  type TimeSyncPayload,
  type QuestionPageReadyPayload,
  type ResultPageReadyPayload,
} from "../validation/schemas.js";
import { timeSyncService } from "../services/TimeSyncService.js";

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

const startingRooms = new Set<string>();

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
          avatarId: avatarId ?? undefined,
          strategicAvatarId: payload.strategicAvatarId ?? undefined,
          color: payload.color ?? undefined,
          division: payload.division ?? undefined,
        });
        
        if (!event) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "JOIN_FAILED", message: "Could not join room" });
          return;
        }

        // Initialize skill inventory from the player's strategic avatar
        const room = roomManager.getRoom(roomId);
        if (room) {
          const skillIds = getAvatarSkillIds(payload.strategicAvatarId);
          if (skillIds.length > 0 && !room.state.skillInventory[playerId]) {
            room.state.skillInventory[playerId] = buildInitialInventory(skillIds);
            console.log(`[WS] Initialized skill inventory for ${playerId} (${payload.strategicAvatarId}): ${skillIds.join(", ")}`);
          }

          // Auto-spawn bot when the first human player joins a bot-flagged DUO room
          if (
            room.pipelineConfig?.hasBot === true &&
            room.state.config.mode === "DUO"
          ) {
            const players = Object.values(room.state.players);
            const humanCount = players.filter(p => !p.id.startsWith("bot_")).length;
            // Only consider the bot as "already present" if it is actively connected.
            // A disconnected bot record left in the state must not block re-spawning.
            const botAlready = players.some(p => p.id.startsWith("bot_") && p.isConnected);
            if (humanCount === 1 && !botAlready) {
              setTimeout(() => {
                try {
                  new BotPlayerService(roomId);
                  console.log(`[WS] Bot auto-spawned for room ${roomId}`);
                } catch (err) {
                  console.error(`[WS] Failed to auto-spawn bot for room ${roomId}:`, err);
                }
              }, 2000);
            }
          }
        }
        
        currentRoomId = roomId;
        currentPlayerId = playerId;
        
        socket.join(roomId);
        socket.join(`player:${playerId}`);
        
        const state = roomManager.getState(roomId);
        socket.emit("state", { state });
        
        if (state) {
          // Re-émission de la phase courante à la connexion socket.
          // `source: 'join_sync'` distingue cette ré-émission d'une véritable
          // transition de phase (qui n'a pas ce drapeau, voir
          // GameOrchestrator.emitPhaseChanged). Les pages clientes — notamment
          // /duo/answer — utilisent ce flag pour éviter le rebond
          // Answer→Question→Answer sur le buzz winner qui vient juste
          // d'arriver pendant que la phase serveur est encore QUESTION_ACTIVE
          // (V3 NON-BLOCKING : la phase ne bascule sur ANSWER_SELECTION qu'au
          // timeout). Sans ce drapeau, le `phase_changed` d'arrivée était
          // confondu avec une vraie transition et déclenchait le rebond.
          socket.emit("phase_changed", {
            phase: state.phase,
            phaseStartedAtMs: state.phaseStartedAtMs ?? Date.now(),
            phaseEndsAtMs: state.phaseEndsAtMs,
            questionIndex: state.questionIndex,
            roundNumber: state.currentRound,
            activeEffects: state.activeEffects,
            source: "join_sync",
          });
          
          // Emit comprehensive game_state for initial hydration
          const playersRoster: Record<string, {
            id: string;
            name: string;
            avatarId?: string;
            avatarUrl?: string;
            strategicAvatarId?: string;
            score: number;
            roundScore: number;
            roundsWon: number;
            isConnected: boolean;
            isReady: boolean;
            isHost?: boolean;
          }> = {};
          
          for (const [pid, player] of Object.entries(state.players)) {
            playersRoster[pid] = {
              id: player.id,
              name: player.name,
              avatarId: player.avatarId,
              avatarUrl: player.avatarId,
              strategicAvatarId: player.strategicAvatarId,
              score: player.score,
              roundScore: player.roundScore,
              roundsWon: player.roundsWon,
              isConnected: player.isConnected,
              isReady: ((player as Player & { isReady?: boolean }).isReady) === true,
              isHost: player.isHost,
            };
          }
          
          // Sanitize current question (remove correct answer info)
          // Include timer metadata matching what question_published delivers
          let currentQuestionSanitized = null;
          const activeQuestion = state.questions[state.questionIndex] || state.currentQuestion;
          if (activeQuestion) {
            const q = activeQuestion;
            // Sanitize choices to ensure they are plain strings (like question_published does).
            // Also filter out "null"/"undefined" strings that appear in true/false questions.
            const rawChoices = q.choices || (q as any).answers;
            let sanitizedChoices: string[] | undefined = undefined;
            if (rawChoices && Array.isArray(rawChoices)) {
              sanitizedChoices = rawChoices
                .map((choice: unknown) => {
                  if (typeof choice === "string") return choice;
                  if (choice && typeof choice === "object") {
                    const obj = choice as Record<string, unknown>;
                    if (typeof obj.text === "string") return obj.text;
                    if (typeof obj.answer === "string") return obj.answer;
                    if (typeof obj.label === "string") return obj.label;
                  }
                  return String(choice);
                })
                .filter((c: string) => c !== "null" && c !== "undefined" && c.trim() !== "");
            }
            currentQuestionSanitized = {
              id: q.id,
              text: q.text,
              type: q.type,
              choices: sanitizedChoices,
              difficulty: q.difficulty,
              category: q.category,
              subCategory: q.subCategory,
              theme: q.category || q.subCategory || "Culture générale",
              timeLimitMs: q.timeLimitMs || state.config.timers.questionActive,
              buzzWindowDurationMs: state.config.timers.questionActive,
              answerDurationMs: state.config.timers.answerSelection,
            };
          }
          
          socket.emit("game_state", {
            sessionId: state.sessionId,
            lobbyCode: state.lobbyCode,
            phase: state.phase,
            phaseEndsAtMs: state.phaseEndsAtMs,
            phaseStartedAtMs: state.phaseStartedAtMs,
            currentRound: state.currentRound,
            questionIndex: state.questionIndex,
            totalQuestions: state.questions.length,
            players: playersRoster,
            currentQuestion: currentQuestionSanitized,
            lockedAnswerPlayerId: state.lockedAnswerPlayerId,
            buzzQueue: state.buzzQueue,
            roundResults: state.roundResults,
            config: {
              mode: state.config.mode,
              questionsPerRound: state.config.questionsPerRound,
              roundsToWin: state.config.roundsToWin,
              maxRounds: state.config.maxRounds,
              buzzEnabled: state.config.buzzEnabled,
              timers: state.config.timers,
            },
          });
        }
        
        socket.to(roomId).emit("event", { event });

        const updatedState = roomManager.getState(roomId);

        // Broadcast updated state to all players in the room (including the joining player,
        // since socket.join(roomId) was already called above). A second direct socket.emit()
        // is unnecessary and causes the joining player to process the state event twice.
        io.to(roomId).emit("state", { state: updatedState });

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
        
        // Task #78 — Non-buzzer "participatif" mode for Duo.
        //
        // Eligibility tiers:
        //   1) Player is in buzzQueue OR holds lockedAnswerPlayerId
        //      → full scoring (first=+2, second=+1, wrong=-1, timeout=-2)
        //   2) Player is NOT in the queue but the room is in an answer-collecting
        //      phase (ANSWER_SELECTION / ANSWER_COLLECTION) and the round had
        //      at least ONE buzzer → "participatif", scored ALWAYS 0 pts.
        //      Lets the non-buzzer keep playing on /duo/answer (same UI, same
        //      audio cues for correct/wrong) without affecting the scoreboard.
        //
        // No-buzz rounds (queue empty) skip ANSWER_SELECTION entirely on the
        // server, so no participatif path is needed there — handled upstream.
        const isInBuzzQueue = room.state.buzzQueue.some(b => b.playerId === currentPlayerId);
        const isLocked = room.state.lockedAnswerPlayerId === currentPlayerId;
        const isAnswerPhase = room.state.phase === "ANSWER_SELECTION" || room.state.phase === "ANSWER_COLLECTION";
        const hasBuzzers = room.state.buzzQueue.length > 0;
        const isParticipatif = !isInBuzzQueue && !isLocked && isAnswerPhase && hasBuzzers;
        if (!isInBuzzQueue && !isLocked && !isParticipatif) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "NOT_YOUR_TURN", message: "You have not buzzed for this question" });
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

        const room = roomManager.getRoom(payload.roomId);
        if (!room) {
          socket.emit("error", { code: "ROOM_NOT_FOUND", message: "Room not found" });
          MetricsService.incrementEventsFailed();
          return;
        }

        // Validate via formal skill-engine
        const canActivate = canActivateSkill(room.state, currentPlayerId, payload.skillId);
        if (!canActivate.allowed) {
          socket.emit("skill_failed", {
            skillId: payload.skillId,
            reason: canActivate.reason ?? "activation_denied",
          });
          MetricsService.incrementEventsFailed();
          return;
        }

        // ── VISUAL-EFFECT ANSWER-PHASE SKILLS ───────────────────────────────────
        // These emit a targeted skill_effect only to the activating player.
        // Guard: only the player who currently holds the buzz lock may use these.
        // The server computes answer-hint metadata; then consumes the skill use.
        const ANSWER_PHASE_VISUAL_SKILLS = ["illuminate_numbers", "acidify_error", "ai_suggestion"] as const;
        if ((ANSWER_PHASE_VISUAL_SKILLS as readonly string[]).includes(payload.skillId)) {
          if (room.state.lockedAnswerPlayerId !== currentPlayerId) {
            socket.emit("skill_failed", {
              skillId: payload.skillId,
              reason: "not_your_turn",
              message: "Answer-phase skills can only be used by the buzzing player",
            });
            console.log(`[WS] ${payload.skillId} rejected: ${currentPlayerId} is not the locked buzzer`);
            return;
          }
          const question = room.state.questions[room.state.questionIndex];
          const correctIndex = question?.correctIndex ?? -1;
          const qRaw = question as Record<string, unknown>;
          const choicesArr = Array.isArray(qRaw?.choices) ? (qRaw.choices as unknown[]) : null;
          const answersArr = Array.isArray(qRaw?.answers) ? (qRaw.answers as unknown[]) : null;
          const totalAnswers = choicesArr ? choicesArr.length : (answersArr ? answersArr.length : 4);

          // Delegate answer-hint computation to game-engine (single source of truth)
          const effectPayload = applyAnswerPhaseSkill(payload.skillId, correctIndex, totalAnswers) ?? { skillId: payload.skillId };

          // Consume the skill use from inventory WITHOUT adding an activeEffect entry.
          // Visual one-shot skills deliver their effect via socket emit; no need to
          // persist them in game state as an activeEffect.
          room.state = consumeSkillUse(room.state, currentPlayerId, payload.skillId);

          // Send effect only to the activating player
          socket.emit("skill_effect", effectPayload);
          console.log(`[WS] ${payload.skillId} applied for ${currentPlayerId}: ${JSON.stringify(effectPayload)}`);
          MetricsService.incrementEventsProcessed();
          return;
        }

        // ── cancel_error (REVEAL / ROUND_SCOREBOARD) ───────────────────────────
        if (payload.skillId === "cancel_error") {
          const applied = gameOrchestrator.handleCancelError(payload.roomId, currentPlayerId);
          if (!applied) {
            socket.emit("skill_failed", {
              skillId: "cancel_error",
              reason: "no_negative_delta",
            });
            MetricsService.incrementEventsFailed();
            return;
          }
          // Consume the skill use
          room.state = applySkillEffect(
            room.state,
            currentPlayerId,
            currentPlayerId,
            "cancel_error",
            { questionsAffected: 1 }
          );
          socket.emit("skill_effect", { skillId: "cancel_error", applied: true });
          MetricsService.incrementEventsProcessed();
          return;
        }

        // ── premonition (ROUND_SCOREBOARD) ──────────────────────────────────────
        if (payload.skillId === "premonition") {
          const category = gameOrchestrator.getNextQuestionCategory(payload.roomId);
          // Consume the skill use
          room.state = applySkillEffect(
            room.state,
            currentPlayerId,
            currentPlayerId,
            "premonition",
            { questionsAffected: 1 }
          );
          // Send next question category only to the activating player
          socket.emit("skill_effect", {
            skillId: "premonition",
            nextCategory: category ?? null,
          });
          console.log(`[WS] premonition for ${currentPlayerId}: next category = ${category}`);
          MetricsService.incrementEventsProcessed();
          return;
        }

        // ── STANDARD SKILLS (reduce_time, shuffle_answers, score_shield, etc.) ──
        // Determine target
        let targetId = payload.targetPlayerId;
        if (!targetId) {
          targetId = roomManager.findAttackTarget(payload.roomId, currentPlayerId) || undefined;
        }
        if (!targetId) {
          // Self-targeting skill or fallback to self
          targetId = currentPlayerId;
        }

        // Compute questionsAffected based on round (for reduce_time)
        const currentRound = room.state.currentRound;
        let questionsAffected = 5;
        if (currentRound === 3) questionsAffected = 3;
        else if (currentRound >= 4) questionsAffected = 1;

        // Apply effect via skill-engine — updates activeEffects and skillInventory
        room.state = applySkillEffect(
          room.state,
          currentPlayerId,
          targetId,
          payload.skillId,
          { questionsAffected }
        );

        const targetPlayer = room.state.players[targetId];
        const attackerPlayer = room.state.players[currentPlayerId];

        io.to(payload.roomId).emit("skill_activated", {
          skillId: payload.skillId,
          attackerId: currentPlayerId,
          attackerName: attackerPlayer?.name,
          targetId,
          targetName: targetPlayer?.name,
          questionsAffected,
          activeEffects: room.state.activeEffects,
        });

        console.log(`[WS] Skill ${payload.skillId}: ${attackerPlayer?.name} → ${targetPlayer?.name} (${questionsAffected} questions)`);
        MetricsService.incrementEventsProcessed();
        
      } catch (error) {
        console.error("[WS] Error processing skill:", error);
        MetricsService.incrementEventsFailed();
        socket.emit("error", { code: "SKILL_ERROR", message: "Error processing skill" });
      }
    });

    socket.on("ready", async (data: unknown) => {
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
        if (!currentPlayerId || !currentRoomId || currentRoomId !== payload.roomId) {
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

        const player = room.state.players[currentPlayerId];
        if (!player) {
          MetricsService.incrementEventsFailed();
          socket.emit("error", { code: "PLAYER_NOT_FOUND", message: "Player not found in room" });
          return;
        }
        
        (player as Player & { isReady?: boolean }).isReady = payload.isReady;

        console.log(`[WS] Player ${currentPlayerId} ready: ${payload.isReady}`);
        
        io.to(payload.roomId).emit("player_ready", {
          playerId: currentPlayerId,
          isReady: payload.isReady,
        });

        const updatedState = roomManager.getState(payload.roomId);
        io.to(payload.roomId).emit("state", { state: updatedState });
        const players = Object.values(room.state.players); 
        if (room.state.config.mode === "DUO" && room.state.phase === "LOBBY" && players.length === room.state.config.maxPlayers && players.every(p => p.isConnected && ((p as Player & { isReady?: boolean }).isReady) === true)) {
          if (!startingRooms.has(payload.roomId)) {
            startingRooms.add(payload.roomId);
            console.log("[WS] AUTO START DUO");
            try {
              const startResult = await gameOrchestrator.startGame(payload.roomId);
              if (!startResult.success) {
                // Reset ready states so players can retry cleanly
                const failedRoom = roomManager.getRoom(payload.roomId);
                if (failedRoom) {
                  for (const p of Object.values(failedRoom.state.players)) {
                    (p as Player & { isReady?: boolean }).isReady = false;
                  }
                  io.to(payload.roomId).emit("game_start_error", {
                    code: "QUESTION_INIT_FAILED",
                    message: "Erreur lors du chargement des questions. Veuillez réessayer.",
                  });
                  const resetState = roomManager.getState(payload.roomId);
                  io.to(payload.roomId).emit("state", { state: resetState });
                  console.error(`[WS] startGame failed for room ${payload.roomId}: ${startResult.error}`);
                }
              }
            } finally {
              startingRooms.delete(payload.roomId);
            }
          } else {
            console.log("[WS] startGame already in progress for room, skipping duplicate trigger", payload.roomId);
          }
        } else if (
          (room.state.phase === "RESULT" || room.state.phase === "REVEAL") &&
          payload.isReady === true
        ) {
          // RESULT-phase short-circuit: ALL *connected* human players have pressed
          // the GO button on /duo/result. Bots emit ready:true on every RESULT entry
          // but must never drive this transition on their own.
          // Disconnected humans (temporarily gone during page navigation) are NOT
          // treated as "ready" — the old `!p.isConnected` shorthand caused the
          // transition to fire the instant the bot pressed GO while the human was
          // navigating, which made the result page flash and disappear immediately.
          // The 60 s fallback timeout covers the case where a human truly leaves.
          const humanPlayers = players.filter(p => p.isBot !== true);
          const connectedHumans = humanPlayers.filter(p => p.isConnected);
          if (
            connectedHumans.length > 0 &&
            connectedHumans.every(
              (p) => ((p as Player & { isReady?: boolean }).isReady) === true
            )
          ) {
            console.log(
              `[WS] All connected humans ready in ${room.state.phase} for room ${payload.roomId} — short-circuiting fallback timeout`
            );
            gameOrchestrator.requestEarlyResultTransition(payload.roomId);
          }
        }

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

      io.to(`player:${payload.targetId}`).emit("voice_offer", {
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

      io.to(`player:${payload.targetId}`).emit("voice_answer", {
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

      io.to(`player:${payload.targetId}`).emit("voice_ice_candidate", {
        from: currentPlayerId,
        candidate: payload.candidate,
      });
    });

    socket.on("question_page_ready", (data: unknown) => {
      const result = validateEvent(QuestionPageReadySchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for question_page_ready:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid question_page_ready payload" });
        return;
      }
      const payload = result.data as QuestionPageReadyPayload;

      if (!currentPlayerId || !currentRoomId || currentRoomId !== payload.roomId) {
        socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
        return;
      }

      gameOrchestrator.handleQuestionPageReady(payload.roomId, currentPlayerId);
    });

    // Task #78 — Symmetric per-arrival barrier on /duo/result. The orchestrator
    // resets the visible 60 s countdown ONLY when ALL expected humans have
    // arrived; the first player to land sees a "waiting for opponent" overlay.
    socket.on("result_page_ready", (data: unknown) => {
      const result = validateEvent(ResultPageReadySchema, data);
      if (!result.success) {
        console.error("[WS] Validation error for result_page_ready:", result.error.issues);
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid result_page_ready payload" });
        return;
      }
      const payload = result.data as ResultPageReadyPayload;

      if (!currentPlayerId || !currentRoomId || currentRoomId !== payload.roomId) {
        socket.emit("error", { code: "NOT_IN_ROOM", message: "Not in a room" });
        return;
      }

      gameOrchestrator.handleResultPageReady(payload.roomId, currentPlayerId);
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

    socket.on("time_sync_ping", (data: unknown) => {
      const result = validateEvent(TimeSyncSchema, data);
      if (!result.success) {
        socket.emit("error", { code: "VALIDATION_ERROR", message: "Invalid time_sync_ping payload" });
        return;
      }
      const payload = result.data;
      const response = timeSyncService.handlePing(payload.clientSentAtMs);
      socket.emit("time_sync_pong", response);
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
