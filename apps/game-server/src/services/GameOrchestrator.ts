import type { Server as SocketIOServer } from "socket.io";
import type { RoomManager, Room } from "./RoomManager.js";
import type { Question, Mode, Phase, PlayerLiveStats, MatchStats, Player } from "@strategybuzzer/shared";
import type { GameEvent, PhaseChangedEvent, QuestionPublishedEvent, AnswerRevealedEvent, AnswerSubmittedEvent, RoundEndedEvent, MatchEndedEvent, BuzzReceivedEvent, GameStartedEvent } from "@strategybuzzer/shared";
import { applyEvent, hasActiveEffect, expireEffects, applyScoreEffects, rechargeInventory } from "@strategybuzzer/game-engine";
import { shuffleOnce, resolveCorrectIndex, archiveRevision } from "./ShuffleService.js";
import { getNextPhase, getPhaseTimeout, isTerminalPhase } from "@strategybuzzer/game-engine";
import { updatePlayerLiveStats, emptyPlayerLiveStats } from "@strategybuzzer/game-engine";
import { initQuestionPipeline, fetchNextBlock, getPipelineStatus, cleanupPipeline } from "./QuestionService.js";
import { appendEventLog, setRoomState, setMatchResult, redisClient } from "./RedisService.js";
import { rateLimiter } from "../middleware/rateLimiter.js";
import { saveRoomSnapshot } from "./RoomRecovery.js";
import { notifyMatchFinalized, saveMatchSnapshot, recordPlayerMemory } from "./InternalLaravelClient.js";

export class GameOrchestrator {
  private io: SocketIOServer;
  private roomManager: RoomManager;
  private phaseTimers: Map<string, NodeJS.Timeout> = new Map();
  private pendingAnswers: Map<string, { playerId: string; answer: number | string | boolean; submittedAtMs: number }> = new Map();
  // Store answers from ALL buzzers (key = roomId, value = Map of playerId -> answer data)
  // Task #78 — value type now carries `didBuzz` so scoreAllBuzzers can treat
  // non-buzzer participatif answers (Duo) separately: same UI feedback as
  // buzzers, but ALWAYS scored 0 pts and NOT counted as a buzz in live stats.
  // shuffleRevision: client-submitted revision at answer time, used by
  // resolveCorrectIndex() for race-condition tolerance (Guard 2).
  private allBuzzerAnswers: Map<string, Map<string, { answer: number | string | boolean; submittedAtMs: number; buzzOrder: number; didBuzz: boolean; shuffleRevision?: number }>> = new Map();
  // Task #78 — Symmetric per-arrival barrier on RESULT, mirroring SYNC. The
  // 60s countdown is reset on the SECOND human's `result_page_ready`. The
  // expected-set is snapshotted at REVEAL/RESULT entry; transient drops do
  // not shrink it.
  private resultReadyMaps: Map<string, Set<string>> = new Map();
  private resultExpectedMaps: Map<string, Set<string>> = new Map();
  // H1 — Pre-RESULT arrival buffer. Players who navigate to /duo/result
  // during ANSWER_SELECTION or ANSWER_COLLECTION (V3 individual navigation)
  // are stored here. revealAnswer() flushes them into resultReadyMaps so
  // they count toward the "all humans present" re-stamp check. Idempotent
  // (Set): reconnect duplicates are ignored automatically.
  private resultEarlyArrivals: Map<string, Set<string>> = new Map();
  // Track last score delta per player for cancel_error retroactive correction.
  // Stores { questionIndex, delta } so cancel_error only applies to the
  // question that was just scored (prevents stale cross-question corrections).
  private lastScoreDeltas: Map<string, Map<string, { questionIndex: number; delta: number }>> = new Map();
  // Track which players have sent question_page_ready during SYNC phase
  private syncReadyMaps: Map<string, Set<string>> = new Map();
  // Snapshot of expected human players at SYNC entry (used for early-exit check)
  private syncExpectedMaps: Map<string, Set<string>> = new Map();
  // ─── Live stats per room/player (server-authoritative) ───────────────────
  // Keyed by roomId → playerId → PlayerLiveStats. Updated after each scoring
  // pass in scoreAllBuzzers, broadcast on player_stats_updated / round_stats /
  // match_stats, and persisted to Laravel via the finalize endpoint.
  private playerStats: Map<string, Map<string, PlayerLiveStats>> = new Map();
  // Wall-clock timestamp at which the current QUESTION_ACTIVE phase was
  // published per room. Used to derive *relative* buzz latency for
  // averageResponseMs (never use absolute epoch timestamps).
  private currentQuestionPublishedAtMs: Map<string, number> = new Map();

  constructor(io: SocketIOServer, roomManager: RoomManager) {
    this.io = io;
    this.roomManager = roomManager;
  }

  // ── Live stats helpers ──────────────────────────────────────────────────
  private getOrInitPlayerStats(roomId: string, playerId: string): PlayerLiveStats {
    let roomMap = this.playerStats.get(roomId);
    if (!roomMap) {
      roomMap = new Map();
      this.playerStats.set(roomId, roomMap);
    }
    let stats = roomMap.get(playerId);
    if (!stats) {
      stats = emptyPlayerLiveStats(playerId);
      roomMap.set(playerId, stats);
    }
    return stats;
  }

  private writePlayerStats(roomId: string, playerId: string, stats: PlayerLiveStats): void {
    let roomMap = this.playerStats.get(roomId);
    if (!roomMap) {
      roomMap = new Map();
      this.playerStats.set(roomId, roomMap);
    }
    roomMap.set(playerId, stats);
    // Mirror the live-stat fields back onto the canonical Player so that any
    // state hydration / Redis snapshot already carries them.
    const room = this.roomManager.getRoom(roomId);
    const p = room?.state.players[playerId];
    if (p) {
      p.correctAnswers     = stats.correctAnswers;
      p.wrongAnswers       = stats.wrongAnswers;
      p.totalAnswers       = stats.totalAnswers;
      p.accuracyPercent    = stats.accuracyPercent;
      p.efficiencyPercent  = stats.efficiencyPercent;
      p.averageResponseMs  = stats.averageResponseMs;
      p.buzzCount          = stats.buzzCount;
      p.buzzWon            = stats.buzzWon;
      p.buzzLost           = stats.buzzLost;
      p.currentStreak      = stats.currentStreak;
      p.bestStreak         = stats.bestStreak;
    }
  }

  /**
   * Snapshot all player stats for a room as a plain record, ready to broadcast
   * or persist. Pulls from the in-memory map and tops up roundsWon / lives
   * from canonical Player state (those are reducer-managed, not stat-managed).
   */
  private snapshotAllPlayerStats(roomId: string): Record<string, PlayerLiveStats> {
    const room = this.roomManager.getRoom(roomId);
    const out: Record<string, PlayerLiveStats> = {};
    if (!room) return out;
    for (const [playerId, p] of Object.entries(room.state.players)) {
      const stats = this.getOrInitPlayerStats(roomId, playerId);
      out[playerId] = {
        ...stats,
        score: p.score,
        roundScore: p.roundScore,
        roundsWon: p.roundsWon,
        lives: p.lives,
      };
    }
    return out;
  }

  async startGame(roomId: string): Promise<{ success: boolean; error?: string }> {
    const room = this.roomManager.getRoom(roomId);
    if (!room) {
      console.error(`[GameOrchestrator] Cannot start game: room ${roomId} not found`);
      return { success: false, error: "Room not found" };
    }

    if (!room.pipelineConfig) {
      console.error(`[GameOrchestrator] No pipeline config for room ${roomId}`);
      return { success: false, error: "Pipeline config not set. Please set theme, niveau, language when creating room." };
    }

    room.usedQuestionIds = new Set<string>();
    this.roomManager.resetSkillEffects(roomId);

    // If questions were pre-loaded via POST /rooms/:roomId/questions
    // (e.g. by deterministic E2E tests, or any future caller that owns
    // its own question source), skip the LLM pipeline entirely. This
    // keeps the production behaviour identical for callers that don't
    // pre-load questions, and makes the API symmetric.
    if (room.state.questions.length > 0) {
      for (const q of room.state.questions) {
        room.usedQuestionIds.add(q.id);
      }
      console.log(`[GameOrchestrator] Using ${room.state.questions.length} pre-loaded question(s) for room ${roomId} (pipeline skipped)`);
    } else {
      const pipelineResult = await initQuestionPipeline({
        roomId,
        theme: room.pipelineConfig.theme,
        niveau: room.pipelineConfig.niveau,
        language: room.pipelineConfig.language,
        maxRounds: room.pipelineConfig.maxRounds,
        questionsPerRound: room.pipelineConfig.questionsPerRound,
      });

      if (!pipelineResult.success || !pipelineResult.firstQuestion) {
        console.error(`[GameOrchestrator] Failed to initialize question pipeline for room ${roomId}: ${pipelineResult.error}`);
        return { success: false, error: pipelineResult.error || "Failed to initialize questions" };
      }

      room.state.questions = [pipelineResult.firstQuestion];
      room.usedQuestionIds.add(pipelineResult.firstQuestion.id);
      console.log(`[GameOrchestrator] Pipeline initialized with first question for room ${roomId}`);
    }

    const event = this.roomManager.startGame(roomId);
    if (!event) {
      console.error(`[GameOrchestrator] Failed to start game in room ${roomId}`);
      return { success: false, error: "Failed to start game" };
    }

    this.io.to(roomId).emit("event", { event });
    this.io.to(roomId).emit("game_started", {
      config: room.state.config,
    });

    await this.logEventToRedis(roomId, event);

    console.log(`[GameOrchestrator] Game started in room ${roomId}, phase: ${room.state.phase}`);

    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);
    
    return { success: true };
  }

  setQuestions(roomId: string, questions: Question[]): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) {
      console.error(`[GameOrchestrator] Cannot set questions: room ${roomId} not found`);
      return;
    }

    room.state.questions = questions;
    console.log(`[GameOrchestrator] Set ${questions.length} questions for room ${roomId}`);
  }

  appendQuestions(roomId: string, questions: Question[]): { success: boolean; totalCount: number } {
    const room = this.roomManager.getRoom(roomId);
    if (!room) {
      console.error(`[GameOrchestrator] Cannot append questions: room ${roomId} not found`);
      return { success: false, totalCount: 0 };
    }

    room.state.questions = [...room.state.questions, ...questions];
    
    for (const q of questions) {
      room.usedQuestionIds ??= new Set();
      room.usedQuestionIds.add(q.id);
    }
    
    console.log(`[GameOrchestrator] Appended ${questions.length} questions for room ${roomId}, total: ${room.state.questions.length}`);
    return { success: true, totalCount: room.state.questions.length };
  }

  handleBuzz(roomId: string, playerId: string, clientTimeMs: number): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (room.state.phase !== "QUESTION_ACTIVE") {
      console.log(`[GameOrchestrator] Buzz rejected: not in QUESTION_ACTIVE phase`);
      return;
    }

    const event = this.roomManager.registerBuzz(roomId, playerId, clientTimeMs);
    if (!event) return;

    this.io.to(roomId).emit("event", { event });
    this.logEventToRedis(roomId, event);

    // V3 NON-BLOCKING: phase stays QUESTION_ACTIVE after buzz
    // Emit buzz_winner with position but do NOT change phase or clear timer
    const position = room.state.buzzQueue.length; // already includes this buzz
    const player = room.state.players[playerId];
    this.io.to(roomId).emit("buzz_winner", {
      playerId,
      playerName: player?.name,
      position,
    });

    console.log(`[GameOrchestrator] Buzz winner: ${player?.name} (${playerId}), position: ${position}`);

    // NOTE: An "all-buzzed → transition immediately to ANSWER_SELECTION"
    // optimisation was intentionally deferred. Stabilise the official flow
    // (QUESTION_ACTIVE → ANSWER_SELECTION → ANSWER_COLLECTION → RESULT) on
    // its full timers first; an early-exit can be added in a dedicated
    // follow-up task once the timer contract is fully validated.
  }

  handleAnswer(roomId: string, playerId: string, answer: number | string | boolean, shuffleRevision?: number): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // V3: Accept answers during QUESTION_ACTIVE, ANSWER_COLLECTION, and ANSWER_SELECTION (legacy)
    const acceptablePhases = ["QUESTION_ACTIVE", "ANSWER_COLLECTION", "ANSWER_SELECTION"];
    if (!acceptablePhases.includes(room.state.phase)) {
      console.log(`[GameOrchestrator] Answer rejected: not in answer phase (${room.state.phase})`);
      return;
    }

    // Task #78 — Tier 1: real buzzer (in queue) → buzzOrder>=1, didBuzz=true.
    //          Tier 2: non-buzzer "participatif" (Duo only path that hits here)
    //                  → buzzOrder=0, didBuzz=false. Eligibility was already
    //                  validated in handlers.ts (room must be in answer phase
    //                  AND have at least one buzzer in the queue). The
    //                  participatif answer feeds the same UI feedback to the
    //                  non-buzzer (correct sound / wrong sound) but is
    //                  ALWAYS scored 0 pts in scoreAllBuzzers below.
    const buzzIndex = room.state.buzzQueue.findIndex(b => b.playerId === playerId);
    const isBuzzer = buzzIndex !== -1;
    if (!isBuzzer) {
      // Task #78 — participatif path: allow any non-buzzer during ANSWER_SELECTION /
      // ANSWER_COLLECTION. The phase is authoritative (handleQuestionTimeout only enters
      // ANSWER_SELECTION when buzzQueue.length >= 1), so we do NOT re-check
      // buzzQueue.length here. The old hasBuzzers guard was the last line of the same
      // race-condition that caused the "Player did not buzz" crash in the reducer.
      const isAnswerPhase = room.state.phase === "ANSWER_SELECTION" || room.state.phase === "ANSWER_COLLECTION";
      if (!isAnswerPhase) {
        console.log(`[GameOrchestrator] Answer rejected: ${playerId} did not buzz and phase is ${room.state.phase}`);
        return;
      }
    }

    // Initialize room's buzzer answers map if needed
    if (!this.allBuzzerAnswers.has(roomId)) {
      this.allBuzzerAnswers.set(roomId, new Map());
    }
    
    const roomAnswers = this.allBuzzerAnswers.get(roomId)!;
    
    // Check if this player already answered
    if (roomAnswers.has(playerId)) {
      console.log(`[GameOrchestrator] Answer rejected: ${playerId} already answered`);
      return;
    }

    const submittedAtMs = Date.now();
    const buzzOrder = isBuzzer ? buzzIndex + 1 : 0; // 1-indexed for buzzers, 0 for participatif

    // Store this player's answer (with didBuzz flag for scoreAllBuzzers).
    // shuffleRevision: Guard 2 — stored for per-buzzer correctIndex resolution in scoreAllBuzzers().
    roomAnswers.set(playerId, { answer, submittedAtMs, buzzOrder, didBuzz: isBuzzer, shuffleRevision });

    // Task #78 — Visionnaire-side broadcast. Emit a targeted "opponent_choice_submitted"
    // socket event to all OTHER players in the room. The client filters the event
    // based on whether they currently have the see_opponent_choice skill marked
    // active in their inventory; the SERVER does NOT gate by skill here so we
    // avoid coupling Node to the PHP catalog. Skill activation itself remains
    // Node-authoritative via the existing `skill` socket event flow.
    //
    // SECURITY TRADE-OFF (acknowledged): the `answer` index is sent over the
    // wire to every player in the room, so a malicious client could read the
    // opponent's choice via DevTools without owning Visionnaire. Closing this
    // leak requires routing see_opponent_choice through the Node skill engine
    // (it is currently absent from packages/shared/src/types.ts → SkillEffectType,
    // by design — the PHP AvatarSkillService is the canonical catalog) and
    // filtering this emit by `room.state.activeEffects` membership. Tracked as
    // a follow-up — out of scope for the per-player progression contract.
    this.io.to(roomId).emit("opponent_choice_submitted", {
      playerId,
      answer,
      questionIndex: room.state.questionIndex,
      submittedAtMs,
    });

    const submitEvent: AnswerSubmittedEvent = {
      id: room.state.lastEventId + 1,
      type: "ANSWER_SUBMITTED",
      atMs: submittedAtMs,
      sessionId: roomId,
      playerId,
      answer,
      submittedAtMs,
    };

    room.state = applyEvent(room.state, submitEvent);
    room.events.push(submitEvent);

    this.io.to(roomId).emit("event", { event: submitEvent });
    this.logEventToRedis(roomId, submitEvent);

    console.log(`[GameOrchestrator] Player ${playerId} answered (buzz order: ${buzzOrder}). ${roomAnswers.size}/${room.state.buzzQueue.length} buzzers answered`);

    // Early-reveal: once every player in the room has an entry in roomAnswers
    // (buzzers + participatif non-buzzers), there is nothing left to wait for —
    // cancel the running ANSWER_SELECTION / ANSWER_COLLECTION timer and go straight
    // to RESULT. Guard on isAnswerWindow so answers that arrive early during
    // QUESTION_ACTIVE (e.g. a bot that buzzes and answers before the phase
    // transition) do not trigger a premature reveal.
    const isAnswerWindow = room.state.phase === "ANSWER_SELECTION" || room.state.phase === "ANSWER_COLLECTION";
    const totalPlayers = room.state.config.maxPlayers;
    if (isAnswerWindow && roomAnswers.size >= totalPlayers) {
      console.log(`[GameOrchestrator] All ${roomAnswers.size}/${totalPlayers} players answered — early reveal for room ${roomId}`);
      this.clearPhaseTimer(roomId);
      this.revealAnswer(roomId);
    }
  }

  private revealAnswer(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // P6 — Stop shuffle interval: the question is over, no more reordering.
    this.stopShuffleInterval(roomId);

    const currentQuestion = room.state.currentQuestion;
    
    if (!currentQuestion) {
      console.error(`[GameOrchestrator] No current question for room ${roomId}`);
      this.transitionToNextPhase(roomId);
      return;
    }

    const fullQuestion = room.state.questions[room.state.questionIndex];
    
    // P2/P3 — Correct answer resolution.
    // For MCQ: fullQuestion.correctIndex was mutated in-memory in broadcastQuestion()
    // to the post-shuffle value (revision 0). It stays updated on each re-shuffle.
    // Per-buzzer resolution (race conditions) happens in scoreAllBuzzers() via
    // resolveCorrectIndex(shuffleState, buzzerAnswer.shuffleRevision).
    let correctAnswer: number | string | boolean = 0;
    if (fullQuestion) {
      if (fullQuestion.type === "MCQ" && fullQuestion.correctIndex !== undefined) {
        correctAnswer = fullQuestion.correctIndex; // post-shuffle (mutated in broadcastQuestion)
      } else if (fullQuestion.type === "TRUE_FALSE" && fullQuestion.correctBool !== undefined) {
        correctAnswer = fullQuestion.correctBool;
      } else if (fullQuestion.type === "TEXT" && fullQuestion.correctText !== undefined) {
        correctAnswer = fullQuestion.correctText;
      }
    }

    // V3: Transition to RESULT phase (replaces REVEAL).
    //
    // Task #78 — RESULT timer is now a SOFT ceiling. We arm it with the full
    // configured `result` window (60 s in DUO) so any room with no second
    // human ever arriving still advances eventually, but the canonical
    // "Prochaine question dans 60 s" countdown only STARTS at the moment
    // the second human signals `result_page_ready` (handleResultPageReady
    // re-stamps phaseEndsAtMs and re-emits phase_changed). The first human
    // to land sees a "waiting for opponent" overlay until that re-stamp.
    const resultTimer = room.state.config.timers.result;
    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "RESULT",
      phaseEndsAtMs: Date.now() + resultTimer,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    // Snapshot the expected human set at RESULT entry. handleResultPageReady
    // re-stamps the deadline only when ALL expected humans have arrived
    // (first arrival keeps the soft ceiling running; second arrival rearms
    // a fresh 60 s window). Bot-only or single-human rooms naturally
    // advance via the soft ceiling or via a single arrival → fresh stamp.
    const expectedHumans = new Set<string>(
      Object.values(room.state.players)
        .filter(p => !p.isBot)
        .map(p => p.id)
    );
    this.resultReadyMaps.set(roomId, new Set<string>());
    this.resultExpectedMaps.set(roomId, expectedHumans);

    // H1 — Flush pre-RESULT early arrivals (players who navigated to
    // /duo/result during ANSWER_SELECTION/ANSWER_COLLECTION). They are
    // counted toward the "all humans present" re-stamp check. Only IDs
    // that are in expectedHumans are accepted (bots never emit this).
    const earlyArrivals = this.resultEarlyArrivals.get(roomId);
    if (earlyArrivals && earlyArrivals.size > 0) {
      const readyMap = this.resultReadyMaps.get(roomId)!;
      for (const pid of earlyArrivals) {
        if (expectedHumans.has(pid)) {
          readyMap.add(pid);
          console.log(`[GameOrchestrator] Flushed early result arrival: ${pid} in room ${roomId}`);
        }
      }
      this.resultEarlyArrivals.delete(roomId);
    }

    // Reset per-player ready flag so the next "GO" press on /duo/result is
    // required from each connected player before the early-transition
    // short-circuit (requestEarlyResultTransition) can fire. Without this
    // reset the flag would still be true from the LOBBY auto-start, and
    // every RESULT entry would auto-advance instantly the moment both
    // players are connected — defeating the whole point of the GO button.
    for (const p of Object.values(room.state.players)) {
      (p as Player & { isReady?: boolean }).isReady = false;
    }

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);

    // Score ALL buzzers using allBuzzerAnswers as single source of truth
    this.scoreAllBuzzers(roomId, correctAnswer, fullQuestion);

    this.pendingAnswers.delete(roomId);
    this.allBuzzerAnswers.delete(roomId);
    this.schedulePhaseTimeout(roomId);

    // H1 — If all expected humans were already on /duo/result before the
    // global RESULT phase (early arrivals flushed above), re-stamp the
    // canonical 60s deadline immediately instead of waiting for the second
    // arrival via handleResultPageReady. This is a no-op when fewer than
    // all expected humans are present — the normal path takes over then.
    this._tryRestamp(roomId);
  }

  private scoreAllBuzzers(
    roomId: string,
    correctAnswer: number | string | boolean,
    question: Question | undefined
  ): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const roomAnswers = this.allBuzzerAnswers.get(roomId) || new Map();
    const buzzQueue = room.state.buzzQueue;

    if (buzzQueue.length === 0) {
      console.log(`[GameOrchestrator] No buzzers to score in room ${roomId}`);
      return;
    }

    console.log(`[GameOrchestrator] Scoring ${buzzQueue.length} buzzers in room ${roomId}`);

    for (let i = 0; i < buzzQueue.length; i++) {
      const buzzer = buzzQueue[i];
      const buzzOrder = i + 1; // 1-indexed
      const buzzerAnswer = roomAnswers.get(buzzer.playerId);
      const player = room.state.players[buzzer.playerId];
      
      if (!player) continue;

      let isCorrect = false;
      let playerAnswer: number | string | boolean | null = null;
      let pointsEarned = 0;
      // P3 — Per-buzzer shuffle resolution (Guard 2).
      // resolveCorrectIndex reconciles the client's submitted shuffleRevision with
      // the server's shuffleState history (race-condition tolerance: client may have
      // sent the answer using revision N while Node already advanced to N+1).
      let resolvedCorrectIndex: number = correctAnswer as number;
      let resolvedRevision: number | undefined;

      if (buzzerAnswer) {
        // Player buzzed AND answered - score based on correctness
        playerAnswer = buzzerAnswer.answer;
        
        if (question) {
          if (question.type === "MCQ" && question.correctIndex !== undefined) {
            // P3: use resolveCorrectIndex for per-buzzer race-condition tolerance.
            if (room.shuffleState) {
              const resolved = resolveCorrectIndex(room.shuffleState, buzzerAnswer.shuffleRevision);
              resolvedCorrectIndex = resolved.correctIndex;
              resolvedRevision = resolved.resolvedRevision;
            } else {
              resolvedCorrectIndex = question.correctIndex;
            }
            isCorrect = buzzerAnswer.answer === resolvedCorrectIndex;
          } else if (question.type === "TRUE_FALSE" && question.correctBool !== undefined) {
            isCorrect = buzzerAnswer.answer === question.correctBool;
          } else if (question.type === "TEXT" && question.correctText !== undefined) {
            isCorrect = String(buzzerAnswer.answer).toLowerCase() === question.correctText.toLowerCase();
          }
        }
        
        // Score based on buzz order: 1st = +2/-2, 2nd+ = +1/-2
        pointsEarned = this.calculateScore(isCorrect, true, buzzOrder);
        
        console.log(`[GameOrchestrator] Buzzer ${buzzer.playerId} (order: ${buzzOrder}) answered ${isCorrect ? 'correctly' : 'incorrectly'}: ${pointsEarned} pts`);
      } else {
        // Player buzzed but did NOT answer (timeout) - default penalty is -2
        pointsEarned = -2;
        // timeout_forgiveness passive: if the player has this skill, timeout = 0 pts
        const playerInventory = room.state.skillInventory[buzzer.playerId] ?? [];
        const hasTimeoutForgiveness = playerInventory.some(e => e.skillId === "timeout_forgiveness");
        if (hasTimeoutForgiveness) {
          pointsEarned = 0;
          console.log(`[GameOrchestrator] Buzzer ${buzzer.playerId} (order: ${buzzOrder}) timed out — timeout_forgiveness applied: 0 pts`);
        } else {
          console.log(`[GameOrchestrator] Buzzer ${buzzer.playerId} (order: ${buzzOrder}) timed out (no answer): ${pointsEarned} pts`);
        }
      }

      // Apply active skill effects (score_shield, double_points) — server is sole arbiter
      const scoreEffectResult = applyScoreEffects(room.state, buzzer.playerId, pointsEarned);
      pointsEarned = scoreEffectResult.pointsEarned;
      room.state = scoreEffectResult.newState;
      if (scoreEffectResult.skillsTriggered.length > 0) {
        console.log(`[GameOrchestrator] Skill effects on ${buzzer.playerId}: ${scoreEffectResult.skillsTriggered.map(s => s.skillId).join(", ")}`);
      }

      // Track last score delta per player (used by cancel_error retroactive correction).
      // Keyed by questionIndex so cancel_error can only correct the current question.
      if (!this.lastScoreDeltas.has(roomId)) {
        this.lastScoreDeltas.set(roomId, new Map());
      }
      this.lastScoreDeltas.get(roomId)!.set(buzzer.playerId, {
        questionIndex: room.state.questionIndex,
        delta: pointsEarned,
      });

      // Calculate expected scores for event payload (reducer will apply the actual update)
      const newRoundScore = (player.roundScore || 0) + pointsEarned;
      const newTotalScore = (player.score || 0) + pointsEarned;

      // Emit proper AnswerRevealedEvent for each buzzer
      // NOTE: The reducer (applyEvent) handles the actual score update
      const revealEvent: AnswerRevealedEvent = {
        id: room.state.lastEventId + 1,
        type: "ANSWER_REVEALED",
        atMs: Date.now(),
        sessionId: roomId,
        playerId: buzzer.playerId,
        answer: playerAnswer ?? -1,
        isCorrect,
        correctAnswer,
        pointsEarned,
        buzzTimeMs: buzzer.atMs || 0,
        totalScore: newTotalScore,
        roundScore: newRoundScore,
        funFact: question?.funFact,
        didYouKnow: question?.funFact,
      };

      room.state = applyEvent(room.state, revealEvent);
      room.events.push(revealEvent);

      this.io.to(roomId).emit("event", { event: revealEvent });
      this.logEventToRedis(roomId, revealEvent);

      // Emit socket events for UI updates
      // P3: correctIndex = resolvedCorrectIndex (post-shuffle, revision-aligned).
      // shuffleRevision = resolvedRevision (Guard 2: the revision actually used for scoring).
      this.io.to(roomId).emit("answer_revealed", {
        playerId: buzzer.playerId,
        playerName: player?.name,
        answer: playerAnswer,
        isCorrect,
        correctAnswer,
        correctIndex: question?.type === "MCQ" ? resolvedCorrectIndex : question?.correctIndex,
        shuffleRevision: resolvedRevision,
        correctBool: question?.correctBool,
        correctText: question?.correctText,
        pointsEarned,
        totalScore: newTotalScore,
        roundScore: newRoundScore,
        funFact: question?.funFact,
        didYouKnow: question?.funFact,
        skillsTriggered: scoreEffectResult.skillsTriggered,
      });

      // PATCH-4b — Write per-player reveal data to Redis so PHP's renderResultView()
      // can render the correct header/points/answers without relying on the stale
      // PHP session (which is never updated in Node-authoritative Duo mode).
      {
        // PATCH-6a (buzzer path): use shuffleState.choices (already shuffled) for
        // text lookups — question.choices is the ORIGINAL array while playerAnswer
        // and resolvedCorrectIndex are indices in SHUFFLED space.
        const _buzzLookupChoices = room.shuffleState?.choices ?? question.choices;
        const playerAnswerText: string = (() => {
          if (!question) return "";
          if (question.type === "MCQ" && typeof playerAnswer === "number") {
            return String(_buzzLookupChoices?.[playerAnswer] ?? "");
          }
          if (question.type === "TRUE_FALSE") {
            return playerAnswer ? "Vrai" : "Faux";
          }
          return String(playerAnswer ?? "");
        })();
        const correctAnswerText: string = (() => {
          if (!question) return "";
          if (question.type === "MCQ") {
            return String(_buzzLookupChoices?.[resolvedCorrectIndex] ?? "");
          }
          if (question.type === "TRUE_FALSE") {
            return question.correctBool ? "Vrai" : "Faux";
          }
          return String(question.correctText ?? "");
        })();
        const lastRevealKey = `room:${roomId}:last_reveal:${buzzer.playerId}`;
        redisClient.set(lastRevealKey, JSON.stringify({
          isCorrect,
          pointsEarned,
          playerBuzzed: true,
          playerAnswerText,
          correctAnswerText,
          funFact: question?.funFact ?? null,
          questionIndex: room.state.questionIndex,
        }), "EX", 300).catch((err: unknown) => {
          console.warn(`[GameOrchestrator] last_reveal write failed for buzzer ${buzzer.playerId}:`, err instanceof Error ? err.message : err);
        });
      }

      this.io.to(roomId).emit("score_update", {
        playerId: buzzer.playerId,
        score: newTotalScore,
        roundScore: newRoundScore,
        delta: pointsEarned,
        skillsTriggered: scoreEffectResult.skillsTriggered,
      });

      // ── Live-stats update + broadcast ──────────────────────────────────
      // Server-authoritative: front-ends must NEVER recompute these locally.
      const prevStats = this.getOrInitPlayerStats(roomId, buzzer.playerId);
      // Convert absolute buzz timestamp into *relative* latency from the
      // moment the question was published. This keeps averageResponseMs
      // bounded (typically 0–QUESTION_TIMEOUT_MS) and meaningful.
      const publishedAt = this.currentQuestionPublishedAtMs.get(roomId) || 0;
      const relativeBuzzMs = publishedAt && buzzer.atMs
        ? Math.max(0, buzzer.atMs - publishedAt)
        : 0;
      const nextStats = updatePlayerLiveStats(prevStats, {
        didBuzz: true,
        buzzOrder,
        isCorrect,
        buzzTimeMs: relativeBuzzMs,
        newScore: newTotalScore,
        newRoundScore,
      });
      this.writePlayerStats(roomId, buzzer.playerId, nextStats);
      const broadcastStats: PlayerLiveStats = {
        ...nextStats,
        roundsWon: player.roundsWon,
        lives: player.lives,
      };
      this.io.to(roomId).emit("player_stats_updated", {
        playerId: buzzer.playerId,
        playerName: player?.name,
        stats: broadcastStats,
      });
    }

    // Task #78 — Non-buzzer "participatif" reveal pass.
    //
    // Any non-buzzer who submitted an answer during ANSWER_SELECTION /
    // ANSWER_COLLECTION (Duo) gets the same correct/wrong feedback the
    // buzzers received — but ALWAYS scored 0 pts (no score event, no live
    // stats buzz counters touched). Without this loop the non-buzzer's
    // /duo/answer page would never see `answer_revealed` and stay stuck
    // on its waiting/empty state.
    const buzzedIds = new Set(buzzQueue.map(b => b.playerId));
    for (const [pid, ans] of roomAnswers) {
      if (buzzedIds.has(pid)) continue; // already scored above
      if (ans.didBuzz !== false) continue; // safety: skip anything not flagged participatif
      const player = room.state.players[pid];
      if (!player) continue;

      let isCorrect = false;
      let partResolvedCorrectIndex: number = correctAnswer as number;
      let partResolvedRevision: number | undefined;
      if (question) {
        if (question.type === "MCQ" && question.correctIndex !== undefined) {
          // P3: participatif non-buzzers also get per-revision resolution.
          if (room.shuffleState) {
            const resolved = resolveCorrectIndex(room.shuffleState, ans.shuffleRevision);
            partResolvedCorrectIndex = resolved.correctIndex;
            partResolvedRevision = resolved.resolvedRevision;
          } else {
            partResolvedCorrectIndex = question.correctIndex;
          }
          isCorrect = ans.answer === partResolvedCorrectIndex;
        } else if (question.type === "TRUE_FALSE" && question.correctBool !== undefined) {
          isCorrect = ans.answer === question.correctBool;
        } else if (question.type === "TEXT" && question.correctText !== undefined) {
          isCorrect = String(ans.answer).toLowerCase() === question.correctText.toLowerCase();
        }
      }

      const partRevealEvent: AnswerRevealedEvent = {
        id: room.state.lastEventId + 1,
        type: "ANSWER_REVEALED",
        atMs: Date.now(),
        sessionId: roomId,
        playerId: pid,
        answer: ans.answer ?? -1,
        isCorrect,
        correctAnswer,
        pointsEarned: 0, // ALWAYS 0 for participatif
        buzzTimeMs: 0,
        totalScore: player.score,
        roundScore: player.roundScore,
        funFact: question?.funFact,
        didYouKnow: question?.funFact,
      };
      room.state = applyEvent(room.state, partRevealEvent);
      room.events.push(partRevealEvent);
      this.io.to(roomId).emit("event", { event: partRevealEvent });
      this.logEventToRedis(roomId, partRevealEvent);

      this.io.to(roomId).emit("answer_revealed", {
        playerId: pid,
        playerName: player?.name,
        answer: ans.answer,
        isCorrect,
        correctAnswer,
        correctIndex: question?.type === "MCQ" ? partResolvedCorrectIndex : question?.correctIndex,
        shuffleRevision: partResolvedRevision,
        correctBool: question?.correctBool,
        correctText: question?.correctText,
        pointsEarned: 0,
        totalScore: player.score,
        roundScore: player.roundScore,
        funFact: question?.funFact,
        didYouKnow: question?.funFact,
        skillsTriggered: [],
        participatif: true,
      });

      // PATCH-4b (participatif path) — Write per-player reveal data for non-buzzers.
      {
        // PATCH-6a (participatif path): same shuffle-choices fix as buzzer path.
        const _partLookupChoices = room.shuffleState?.choices ?? question.choices;
        const partPlayerAnswerText: string = (() => {
          if (!question) return "";
          if (question.type === "MCQ" && typeof ans.answer === "number") {
            return String(_partLookupChoices?.[ans.answer] ?? "");
          }
          if (question.type === "TRUE_FALSE") {
            return ans.answer ? "Vrai" : "Faux";
          }
          return String(ans.answer ?? "");
        })();
        const partCorrectAnswerText: string = (() => {
          if (!question) return "";
          if (question.type === "MCQ") {
            return String(_partLookupChoices?.[partResolvedCorrectIndex] ?? "");
          }
          if (question.type === "TRUE_FALSE") {
            return question.correctBool ? "Vrai" : "Faux";
          }
          return String(question.correctText ?? "");
        })();
        const partRevealKey = `room:${roomId}:last_reveal:${pid}`;
        redisClient.set(partRevealKey, JSON.stringify({
          isCorrect,
          pointsEarned: 0,
          playerBuzzed: false,
          playerAnswerText: partPlayerAnswerText,
          correctAnswerText: partCorrectAnswerText,
          funFact: question?.funFact ?? null,
          questionIndex: room.state.questionIndex,
        }), "EX", 300).catch((err: unknown) => {
          console.warn(`[GameOrchestrator] last_reveal write failed for participatif ${pid}:`, err instanceof Error ? err.message : err);
        });
      }

      console.log(`[GameOrchestrator] Non-buzzer participatif ${pid} answered ${isCorrect ? 'correctly' : 'incorrectly'}: 0 pts (always)`);
    }

    // Also emit a no-buzz no-op stat refresh for non-buzzers so their efficiency
    // (which depends on totalBuzzes) stays consistent across the front-ends.
    // The pure fn handles "no buzz" by returning unchanged counters but updates score.
    const roomForBroadcast = this.roomManager.getRoom(roomId);
    if (roomForBroadcast) {
      for (const [pid, p] of Object.entries(roomForBroadcast.state.players)) {
        if (buzzedIds.has(pid)) continue;
        const prev = this.getOrInitPlayerStats(roomId, pid);
        const next = updatePlayerLiveStats(prev, {
          didBuzz: false,
          buzzOrder: 0,
          isCorrect: false,
          buzzTimeMs: 0,
          newScore: p.score,
          newRoundScore: p.roundScore,
        });
        this.writePlayerStats(roomId, pid, next);
        this.io.to(roomId).emit("player_stats_updated", {
          playerId: pid,
          playerName: p.name,
          stats: { ...next, roundsWon: p.roundsWon, lives: p.lives },
        });
      }
    }
  }

  private calculateScore(isCorrect: boolean, didBuzz: boolean, buzzOrder: number): number {
    // Universal scoring rules for all modes:
    // - 1st to buzz + correct = +2 pts
    // - 2nd+ to buzz + correct = +1 pt
    // - Buzz + wrong/timeout = -2 pts
    // - No buzz = 0 pts (no penalty ever)
    
    if (!didBuzz || buzzOrder === 0) {
      return 0; // No buzz = 0 pts, no penalty
    }

    if (!isCorrect) {
      return -2; // Buzz + wrong = -2 pts
    }

    if (buzzOrder === 1) {
      return 2; // 1st to buzz + correct = +2 pts
    }
    
    return 1; // 2nd+ to buzz + correct = +1 pt
  }

  private onPhaseTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    console.log(`[GameOrchestrator] Phase timeout in room ${roomId}, phase: ${room.state.phase}`);

    switch (room.state.phase) {
      case "INTRO":
        this.transitionToQuestionActive(roomId);
        break;

      case "QUESTION_ACTIVE":
        this.handleQuestionTimeout(roomId);
        break;

      case "ANSWER_SELECTION":
        this.handleAnswerSelectionTimeout(roomId);
        break;

      case "ANSWER_COLLECTION":
        this.handleAnswerTimeout(roomId);
        break;

      case "REVEAL":
        this.transitionAfterReveal(roomId);
        break;

      case "RESULT":
        this.transitionAfterResult(roomId);
        break;

      case "SYNC":
        this.handleSyncTimeout(roomId);
        break;

      case "WAITING":
        this.handleWaitingTimeout(roomId).catch((err: unknown) => {
          console.error(`[GameOrchestrator] Error in handleWaitingTimeout:`, err);
        });
        break;

      case "ROUND_SCOREBOARD":
        this.transitionAfterRoundScoreboard(roomId);
        break;

      case "MATCH_END":
        this.clearPhaseTimer(roomId);
        break;

      default:
        this.transitionToNextPhase(roomId);
    }
  }

  private transitionToQuestionActive(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (room.state.questions.length === 0) {
      console.error(`[GameOrchestrator] No questions available for room ${roomId}`);
      return;
    }

    const currentQuestion = room.state.questions[room.state.questionIndex];
    if (!currentQuestion) {
      console.warn(
        `[GameOrchestrator] No question at index ${room.state.questionIndex} for room ${roomId} — ending round early`
      );
      this.endRound(roomId);
      return;
    }

    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "QUESTION_ACTIVE",
      phaseEndsAtMs: Date.now() + room.state.config.timers.questionActive,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);
    rateLimiter.resetForQuestion(roomId).catch(e => console.warn(`[RateLimiter] Reset failed for ${roomId}:`, e));
    this.broadcastQuestion(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private sanitizeChoices(choices: unknown[] | undefined): string[] | undefined {
    if (!choices || !Array.isArray(choices)) {
      return undefined;
    }
    return choices
      .map((choice: unknown) => {
        if (typeof choice === 'string') {
          return choice;
        }
        if (choice && typeof choice === 'object') {
          const obj = choice as Record<string, unknown>;
          if (typeof obj.text === 'string') {
            return obj.text;
          }
          if (typeof obj.answer === 'string') {
            return obj.answer;
          }
        }
        return String(choice);
      })
      .filter((c: string) => c !== 'null' && c !== 'undefined' && c.trim() !== '');
  }

  private broadcastQuestion(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // P6 — Stop any shuffle interval from the previous question before starting a new one.
    this.stopShuffleInterval(roomId);

    const question = room.state.questions[room.state.questionIndex];
    if (!question) {
      console.error(`[GameOrchestrator] No question at index ${room.state.questionIndex}`);
      return;
    }

    const rawChoices = (question as Record<string, unknown>).choices || (question as Record<string, unknown>).answers;
    const sanitizedChoices = this.sanitizeChoices(rawChoices as unknown[] | undefined);

    // Shuffle Réponse initial — Option A.
    // Node shuffles choices at rev=0 and writes the result to Redis before emitting
    // any socket events. PHP reads this key in renderAnswerView() so both PHP and Node
    // serve the identical shuffled order from page load — no flash, no misalignment.
    //
    // rev=0 = stable initial random order (Shuffle Réponse)
    // rev=1+ = reserved for Skill Shuffle dynamic re-shuffles (answer_order_changed)
    //
    // Guard 2 resolveCorrectIndex() works identically for all revisions ≥ 0.
    let broadcastChoices = sanitizedChoices;
    if (question.type === "MCQ" && sanitizedChoices !== undefined && sanitizedChoices.length > 1) {
      const originalCorrectIndex = (question as Record<string, unknown>).correctIndex as number ?? 0;

      // Fisher-Yates shuffle at rev=0 — Node-authoritative initial order.
      const { choices: shuffledChoices, correctIndex: shuffledCorrectIndex } =
        shuffleOnce(sanitizedChoices, originalCorrectIndex);

      // Mutate question.correctIndex in-memory so revealAnswer() reads the correct
      // post-shuffle value when building correctAnswer for the ANSWER_REVEALED event.
      (question as Record<string, unknown>).correctIndex = shuffledCorrectIndex;

      room.shuffleState = {
        questionIndex:   room.state.questionIndex,
        revision:        0,
        choices:         shuffledChoices,
        correctIndex:    shuffledCorrectIndex,
        history:         [],
        intervalId:      undefined,
        targetPlayerIds: undefined,
      };

      // Write shuffled order to Redis so PHP can render the same order on page load.
      // Fire-and-forget: failure is logged but must not block the synchronous game flow.
      const initKey = `room:${roomId}:q${room.state.questionIndex}:init_shuffle`;
      redisClient.set(initKey, JSON.stringify({
        choices:      shuffledChoices,
        correctIndex: shuffledCorrectIndex,
        revision:     0,
      }), "EX", 300).catch((err: unknown) => {
        console.warn(
          `[GameOrchestrator] init_shuffle Redis write failed q=${room.state.questionIndex} room=${roomId}:`,
          err instanceof Error ? err.message : err,
        );
      });

      broadcastChoices = shuffledChoices;

      console.log(
        `[GameOrchestrator] MCQ shuffleState rev=0 SHUFFLED q=${room.state.questionIndex} ` +
        `correctIndex=${shuffledCorrectIndex} (was ${originalCorrectIndex}) room=${roomId}`,
      );
    } else {
      // Non-MCQ (TRUE_FALSE, TEXT) or single-choice: no shuffle state.
      room.shuffleState = undefined;
    }

    // Expire any effects that have reached their question limit (formal skill-engine)
    room.state = expireEffects(room.state);

    const baseTimeLimit = question.timeLimitMs || room.state.config.timers.questionActive;

    const publishEvent: QuestionPublishedEvent = {
      id: room.state.lastEventId + 1,
      type: "QUESTION_PUBLISHED",
      atMs: Date.now(),
      sessionId: roomId,
      questionIndex: room.state.questionIndex,
      questionId: question.id,
      text: question.text,
      choices: broadcastChoices,
      category: question.category,
      subCategory: question.subCategory,
      difficulty: question.difficulty,
      timeLimitMs: baseTimeLimit,
    };

    room.state = applyEvent(room.state, publishEvent);
    room.events.push(publishEvent);

    this.io.to(roomId).emit("event", { event: publishEvent });
    this.logEventToRedis(roomId, publishEvent);

    // Mark the publish wall-clock for this room — used by scoreAllBuzzers to
    // compute *relative* buzz latency for averageResponseMs.
    this.currentQuestionPublishedAtMs.set(roomId, Date.now());

    for (const playerId of Object.keys(room.state.players)) {
      // Check active reduce_time effect via formal skill-engine
      const isReduceTimeActive = hasActiveEffect(room.state, playerId, "reduce_time");
      const reductionMs = isReduceTimeActive ? 2000 : 0;
      const playerTimeLimit = Math.max(1000, baseTimeLimit - reductionMs);
      
      // phaseEndsAtMs: derive from the canonical room timestamp so client timer is accurate.
      // For reduce_time players, subtract the reduction from the room's deadline.
      const playerPhaseEndsAtMs = room.state.phaseEndsAtMs
        ? room.state.phaseEndsAtMs - reductionMs
        : Date.now() + playerTimeLimit;

      this.io.to(`player:${playerId}`).emit("question_published", {
        questionIndex: room.state.questionIndex,
        questionId: question.id,
        text: question.text,
        choices: broadcastChoices,
        category: question.category,
        subCategory: question.subCategory,
        difficulty: question.difficulty,
        timeLimitMs: playerTimeLimit,
        phaseEndsAtMs: playerPhaseEndsAtMs,
        totalQuestions: room.state.questions.length,
        reduceTimeActive: isReduceTimeActive,
        // P2: emit initial shuffle revision so clients can initialise their shuffleRevision.
        shuffleRevision: room.shuffleState ? 0 : undefined,
        activeEffects: room.state.activeEffects.filter(e => e.targetPlayerId === playerId),
      });
      
      if (isReduceTimeActive) {
        console.log(`[GameOrchestrator] Player ${playerId} has reduce_time active (−${reductionMs}ms → ${playerTimeLimit}ms)`);
      }
    }

    // P6 — Start the re-shuffle interval if shuffle_answers is active for any player.
    // The skill is activated between questions (REVEAL/ROUND_SCOREBOARD) and stored
    // as an activeEffect in room.state. broadcastQuestion() is the canonical trigger.
    // targetPlayerIds = players who have shuffle_answers active targeting them
    // (for Duo: the opponent; for League Team future: a whole team).
    if (room.shuffleState) {
      const shuffleTargets: string[] = [];
      for (const pid of Object.keys(room.state.players)) {
        if (hasActiveEffect(room.state, pid, "shuffle_answers")) {
          shuffleTargets.push(pid);
        }
      }
      if (shuffleTargets.length > 0) {
        this.startShuffleInterval(roomId, shuffleTargets);
        console.log(
          `[GameOrchestrator] shuffle_answers interval started for targets=[${shuffleTargets.join(",")}] room=${roomId}`,
        );
      }
    }

    console.log(`[GameOrchestrator] Broadcast question ${room.state.questionIndex + 1}/${room.state.questions.length}`);
  }

  private handleQuestionTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // P6 — Stop shuffle interval: question timer has expired, no more reordering.
    this.stopShuffleInterval(roomId);

    // Patch 2 — QUESTION_ACTIVE timeout routing:
    //   • No buzzer at all  → skip ANSWER_SELECTION, go straight to RESULT.
    //   • At least 1 buzzer → enter the official ANSWER_SELECTION window
    //                         (10s) where buzzed players have a guaranteed,
    //                         fair amount of time to actually pick an answer.
    if (room.state.buzzQueue.length === 0) {
      console.log(`[GameOrchestrator] No buzzers in room ${roomId} — skipping ANSWER_SELECTION`);
      this.revealAnswer(roomId);
      return;
    }

    this.transitionToAnswerSelection(roomId);
  }

  /**
   * Patch 2 — Officially open the ANSWER_SELECTION window.
   * Called either:
   *   (a) from handleQuestionTimeout when QUESTION_ACTIVE expires with ≥ 1 buzzer
   *   (b) from handleBuzz early-exit when ALL connected players have buzzed,
   *       so we don't make them wait the full 8s buzz window for nothing.
   * Carries lockedAnswerPlayerId in the broadcast so duo_question.blade.php
   * can redirect the correct buzzer(s) to the answer page.
   */
  private transitionToAnswerSelection(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (room.state.phase !== "QUESTION_ACTIVE") {
      console.warn(`[GameOrchestrator] transitionToAnswerSelection skipped: not in QUESTION_ACTIVE (${room.state.phase})`);
      return;
    }

    // Cancel any in-flight QUESTION_ACTIVE timer — we're advancing early or on timeout.
    this.clearPhaseTimer(roomId);

    const answerSelectionTimer = room.state.config.timers.answerSelection;
    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "ANSWER_SELECTION",
      phaseEndsAtMs: Date.now() + answerSelectionTimer,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    // Defensive guard — lockedAnswerPlayerId must match the first buzzer when we
    // enter ANSWER_SELECTION. Under normal operation the BUZZ_RECEIVED reducer
    // sets it to buzzQueue[0].playerId when the first buzz arrives. However a
    // rapid disconnect + reconnect during the async rateLimiter window can clear
    // it (PLAYER_LEFT non-disconnect path removes the locked player). If we land
    // here with buzzers but no locked player, restore it from the queue head so
    // the bot, clients, and emitPhaseChanged all see the correct locked player.
    if (!room.state.lockedAnswerPlayerId && room.state.buzzQueue.length > 0) {
      room.state.lockedAnswerPlayerId = room.state.buzzQueue[0].playerId;
      console.warn(
        `[GameOrchestrator] Restored lockedAnswerPlayerId → ${room.state.lockedAnswerPlayerId} (was undefined with ${room.state.buzzQueue.length} buzzer(s) in queue)`
      );
    }

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);

    console.log(`[GameOrchestrator] ANSWER_SELECTION phase (${answerSelectionTimer}ms) for room ${roomId} with ${room.state.buzzQueue.length} buzzer(s), locked: ${room.state.lockedAnswerPlayerId}`);
  }

  private handleAnswerTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // All buzzers who didn't answer will be scored as timeout (-2 pts) in scoreAllBuzzers
    // We use allBuzzerAnswers as single source of truth - buzzers not in the map = timeout
    console.log(`[GameOrchestrator] ANSWER_COLLECTION timeout in room ${roomId} - revealing answers`);
    this.revealAnswer(roomId);
  }

  /**
   * Patch 1 — ANSWER_SELECTION timeout handler.
   * When the official 10s answer window expires, transition to the short
   * ANSWER_COLLECTION grace period (catches answers in flight at the
   * boundary). ANSWER_COLLECTION's own timeout will then trigger
   * revealAnswer → RESULT.
   */
  private handleAnswerSelectionTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const answerCollectionTimer = room.state.config.timers.answerCollection;
    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "ANSWER_COLLECTION",
      phaseEndsAtMs: Date.now() + answerCollectionTimer,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);

    console.log(`[GameOrchestrator] ANSWER_SELECTION timeout in room ${roomId} → ANSWER_COLLECTION grace (${answerCollectionTimer}ms)`);
  }

  private shouldShowWaiting(questionIndex: number): boolean {
    return questionIndex === 0 || questionIndex === 4;
  }

  private getWaitingBlockInfo(questionIndex: number): { nextBlockStart: number; nextBlockEnd: number } | null {
    if (questionIndex === 0) {
      return { nextBlockStart: 2, nextBlockEnd: 5 };
    } else if (questionIndex === 4) {
      return { nextBlockStart: 6, nextBlockEnd: 9 };
    }
    return null;
  }

  private async transitionToWaiting(roomId: string): Promise<void> {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const blockInfo = this.getWaitingBlockInfo(room.state.questionIndex);
    if (!blockInfo) {
      room.state.questionIndex++;
      this.transitionToQuestionActive(roomId);
      return;
    }

    const waitingDuration = room.state.config.timers.waiting;
    const waitingEndsAtMs = Date.now() + waitingDuration;

    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "WAITING",
      phaseEndsAtMs: waitingEndsAtMs,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);

    this.io.to(roomId).emit("waiting_block", {
      nextBlockStart: blockInfo.nextBlockStart,
      nextBlockEnd: blockInfo.nextBlockEnd,
      waitingEndsAtMs,
    });

    console.log(`[GameOrchestrator] Waiting phase for block ${blockInfo.nextBlockStart}-${blockInfo.nextBlockEnd}`);

    const blockResult = await fetchNextBlock(roomId, 4);
    if (blockResult.questions.length > 0) {
      const newQuestions = blockResult.questions.filter(q => {
        if (room.usedQuestionIds?.has(q.id)) {
          console.log(`[GameOrchestrator] Skipping duplicate question ${q.id}`);
          return false;
        }
        return true;
      });

      for (const q of newQuestions) {
        room.usedQuestionIds?.add(q.id);
        room.state.questions.push(q);
      }

      console.log(`[GameOrchestrator] Added ${newQuestions.length} new questions for room ${roomId}, total: ${room.state.questions.length}`);
    }

    this.schedulePhaseTimeout(roomId);
  }

  private async handleWaitingTimeout(roomId: string): Promise<void> {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const nextQuestionIndex = room.state.questionIndex + 1;
    if (nextQuestionIndex >= room.state.questions.length) {
      console.log(`[GameOrchestrator] Waiting for questions, checking pipeline status...`);
      const status = await getPipelineStatus(roomId);
      if (!status.ready && status.available < nextQuestionIndex + 1) {
        console.log(`[GameOrchestrator] Questions not ready yet, fetching more...`);
        const blockResult = await fetchNextBlock(roomId, 4);
        if (blockResult.questions.length > 0) {
          const newQuestions = blockResult.questions.filter(q => {
            if (room.usedQuestionIds?.has(q.id)) {
              return false;
            }
            return true;
          });

          for (const q of newQuestions) {
            room.usedQuestionIds?.add(q.id);
            room.state.questions.push(q);
          }
        }
      }
    }

    room.state.questionIndex++;
    this.transitionToQuestionActive(roomId);
  }

  private transitionAfterReveal(roomId: string): void {
    this.transitionAfterResult(roomId);
  }

  /**
   * Public short-circuit for the RESULT-phase fallback timeout.
   * Called by ws/handlers.ts socket.on("ready") when ALL connected players
   * have signalled ready while the room is in RESULT (or REVEAL). Without
   * this, the GO button on /duo/result was a no-op and the round only
   * advanced after the 60 s wall-clock timeout. We clear the pending
   * phase timer first so transitionAfterResult is not called twice
   * (early + delayed). Safe to call from anywhere because it bails out
   * if the room is not actually in a RESULT-like phase.
   */
  public requestEarlyResultTransition(roomId: string): boolean {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return false;
    if (room.state.phase !== "RESULT" && room.state.phase !== "REVEAL") {
      return false;
    }
    this.clearPhaseTimer(roomId);
    this.transitionAfterResult(roomId);
    return true;
  }

  private transitionAfterResult(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const isLastQuestion = room.state.questionIndex >= room.state.config.questionsPerRound - 1;

    if (isLastQuestion) {
      this.endRound(roomId);
    } else {
      // V3: always go through SYNC for inter-question sync.
      // If this is a block-boundary question, prefetch the next block in the background
      // so it is ready by the time QUESTION_ACTIVE starts (non-blocking).
      if (this.shouldShowWaiting(room.state.questionIndex)) {
        this.prefetchQuestionBlock(roomId).catch((err: unknown) => {
          console.error(`[GameOrchestrator] Background question prefetch failed:`, err);
        });
      }
      this.transitionToSync(roomId);
    }
  }

  private async prefetchQuestionBlock(roomId: string): Promise<void> {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    console.log(`[GameOrchestrator] Background prefetch starting for room ${roomId}`);
    const blockResult = await fetchNextBlock(roomId, 4);

    const freshRoom = this.roomManager.getRoom(roomId);
    if (!freshRoom) return;

    if (blockResult.questions.length > 0) {
      const newQuestions = blockResult.questions.filter(q => {
        if (freshRoom.usedQuestionIds?.has(q.id)) {
          console.log(`[GameOrchestrator] Skipping duplicate question ${q.id}`);
          return false;
        }
        return true;
      });
      for (const q of newQuestions) {
        freshRoom.usedQuestionIds ??= new Set();
        freshRoom.usedQuestionIds.add(q.id);
        freshRoom.state.questions.push(q);
      }
      console.log(`[GameOrchestrator] Background prefetched ${newQuestions.length} questions for room ${roomId}, total: ${freshRoom.state.questions.length}`);
    }
  }

  private transitionToSync(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // Increment questionIndex before SYNC so the next question is ready on entry to QUESTION_ACTIVE
    room.state.questionIndex++;

    // Snapshot the set of expected human players at SYNC entry.
    // This snapshot is used for early-exit: we wait for ALL expected humans before advancing.
    // Transient disconnects during page navigation do NOT update this snapshot.
    const expectedHumans = new Set<string>(
      Object.values(room.state.players)
        .filter(p => !p.isBot)
        .map(p => p.id)
    );
    this.syncReadyMaps.set(roomId, new Set<string>());
    this.syncExpectedMaps.set(roomId, expectedHumans);

    const syncTimer = room.state.config.timers.sync;
    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "SYNC",
      phaseEndsAtMs: Date.now() + syncTimer,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);

    console.log(`[GameOrchestrator] SYNC phase started for room ${roomId} (max ${syncTimer}ms), next question index: ${room.state.questionIndex}`);
  }

  private handleSyncTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    console.log(`[GameOrchestrator] SYNC timeout for room ${roomId} — advancing to QUESTION_ACTIVE`);
    this.syncReadyMaps.delete(roomId);
    this.syncExpectedMaps.delete(roomId);
    this.transitionToQuestionActive(roomId);
  }

  handleQuestionPageReady(roomId: string, playerId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (room.state.phase !== "SYNC") {
      console.log(`[GameOrchestrator] question_page_ready ignored: not in SYNC phase (${room.state.phase})`);
      return;
    }

    const readyMap = this.syncReadyMaps.get(roomId);
    const expectedSet = this.syncExpectedMaps.get(roomId);
    if (!readyMap || !expectedSet) return;

    readyMap.add(playerId);
    console.log(`[GameOrchestrator] question_page_ready from ${playerId} in room ${roomId} (${readyMap.size}/${expectedSet.size} expected humans ready)`);

    // Early exit only when ALL expected humans (snapshotted at SYNC entry) have signalled ready.
    // Disconnected or slow humans are handled by the 8s fallback timer — do NOT fast-path here.
    if (expectedSet.size > 0 && [...expectedSet].every(id => readyMap.has(id))) {
      console.log(`[GameOrchestrator] All ${expectedSet.size} expected human(s) ready — early exit SYNC for room ${roomId}`);
      this.clearPhaseTimer(roomId);
      this.syncReadyMaps.delete(roomId);
      this.syncExpectedMaps.delete(roomId);
      this.transitionToQuestionActive(roomId);
    }
  }

  /**
   * H1 — Shared re-stamp helper. Called both from handleResultPageReady
   * (normal RESULT-phase arrival) and from revealAnswer() (all humans
   * already present via early-arrival flush). Cancels the soft-ceiling
   * timer and arms a fresh canonical 60s countdown. No-op if the
   * all-arrived condition is not yet met.
   */
  private _tryRestamp(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const readyMap = this.resultReadyMaps.get(roomId);
    const expectedSet = this.resultExpectedMaps.get(roomId);
    if (!readyMap || !expectedSet) return;

    if (expectedSet.size === 0 || ![...expectedSet].every(id => readyMap.has(id))) {
      return;
    }

    // Cancel the in-flight soft ceiling and re-stamp a fresh `result` window.
    this.clearPhaseTimer(roomId);
    const resultTimer = room.state.config.timers.result;
    const restampEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "RESULT",
      phaseEndsAtMs: Date.now() + resultTimer,
    };
    room.state = applyEvent(room.state, restampEvent);
    room.events.push(restampEvent);
    this.io.to(roomId).emit("event", { event: restampEvent });
    this.logEventToRedis(roomId, restampEvent);
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);

    this.resultReadyMaps.delete(roomId);
    this.resultExpectedMaps.delete(roomId);

    console.log(`[GameOrchestrator] All ${expectedSet.size} human(s) on /duo/result — fresh ${resultTimer}ms countdown armed for room ${roomId}`);
  }

  /**
   * Task #78 / H1 — Per-arrival barrier on /duo/result.
   *
   * V3 product rule: players navigate to /duo/result individually right
   * after answering (800 ms post-click), BEFORE the global RESULT phase.
   * This means result_page_ready can legitimately arrive during
   * ANSWER_SELECTION or ANSWER_COLLECTION. Those early signals are buffered
   * in resultEarlyArrivals; revealAnswer() flushes them into resultReadyMaps
   * and calls _tryRestamp() to re-arm immediately when all humans are present.
   *
   * Behaviour summary:
   *   • Early arrival (ANSWER_SELECTION / ANSWER_COLLECTION)
   *                         → buffer in resultEarlyArrivals, return.
   *   • 1st arrival in RESULT → register, emit progress, do NOT re-stamp yet.
   *                             Player sees "waiting for opponent" overlay.
   *                             Soft ceiling keeps a hard upper bound.
   *   • Last arrival in RESULT → _tryRestamp() fires: cancel soft ceiling,
   *                             emit fresh phase_changed(RESULT) with
   *                             Date.now() + 60s. Clients remove overlay.
   * Bot-only / single-human rooms: expectedSet.size === 1, so the
   * all-arrived condition is met on the first real arrival (or immediately
   * via the early-arrival flush in revealAnswer).
   */
  handleResultPageReady(roomId: string, playerId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const phase = room.state.phase;

    // H1 — Accept early arrivals: player navigated to /duo/result before
    // the global phase reached RESULT. Buffer idempotently and return.
    // revealAnswer() will flush these into resultReadyMaps.
    if (phase === "ANSWER_SELECTION" || phase === "ANSWER_COLLECTION") {
      let earlySet = this.resultEarlyArrivals.get(roomId);
      if (!earlySet) {
        earlySet = new Set<string>();
        this.resultEarlyArrivals.set(roomId, earlySet);
      }
      if (!earlySet.has(playerId)) {
        earlySet.add(playerId);
        console.log(`[GameOrchestrator] result_page_ready EARLY from ${playerId} in room ${roomId} (phase=${phase}) — buffered`);
      }
      return;
    }

    if (phase !== "RESULT" && phase !== "REVEAL") {
      console.log(`[GameOrchestrator] result_page_ready ignored: not in RESULT/REVEAL/ANSWER phase (${phase})`);
      return;
    }

    const readyMap = this.resultReadyMaps.get(roomId);
    const expectedSet = this.resultExpectedMaps.get(roomId);
    if (!readyMap || !expectedSet) {
      // Maps were cleared (e.g. transition already happened). Safe no-op.
      return;
    }

    if (readyMap.has(playerId)) {
      // Idempotent: ignore duplicate signals from the same player.
      return;
    }
    readyMap.add(playerId);
    console.log(`[GameOrchestrator] result_page_ready from ${playerId} in room ${roomId} (${readyMap.size}/${expectedSet.size} expected humans ready)`);

    // Notify clients about per-player arrival progress (used by /duo/result
    // to flip the per-side "Vous / Adversaire — En attente" status row).
    this.io.to(roomId).emit("result_page_ready_progress", {
      ready: [...readyMap],
      expected: [...expectedSet],
    });

    // Re-arm the canonical countdown when all expected humans have arrived.
    this._tryRestamp(roomId);
  }

  // ── P6 — Node-authoritative shuffle interval management ─────────────────────
  //
  // startShuffleInterval: starts a re-shuffle interval that emits answer_order_changed
  // to either the whole room (Duo now) or specific players (League Team future).
  // MUST be called only when room.shuffleState exists (MCQ question active).
  //
  // stopShuffleInterval: clears the interval. Called at every phase exit point:
  //   broadcastQuestion() start, revealAnswer(), handleQuestionTimeout(),
  //   endRound(), cleanup(). Idempotent — safe to call when no interval runs.
  //
  // Multi-mode: targetPlayerIds drives targeting.
  //   undefined   → io.to(roomId).emit() — full room broadcast (Duo).
  //   string[]    → io.to(`player:<id>`).emit() per entry (League Team future).
  private startShuffleInterval(roomId: string, targetPlayerIds?: string[]): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room?.shuffleState) return;
    if (room.shuffleState.intervalId) return; // already running

    if (targetPlayerIds && targetPlayerIds.length > 0) {
      room.shuffleState.targetPlayerIds = targetPlayerIds;
    }

    room.shuffleState.intervalId = setInterval(() => {
      const r = this.roomManager.getRoom(roomId);
      if (!r?.shuffleState) return;

      const question = r.state.questions[r.state.questionIndex] as Record<string, unknown>;
      if (!question) return;

      const currentChoices = (question.choices as string[] | undefined) ?? r.shuffleState.choices;

      // Archive current revision before advancing
      archiveRevision(r.shuffleState);

      // Fisher-Yates re-shuffle
      const { choices: newChoices, correctIndex: newCorrectIndex } =
        shuffleOnce(currentChoices, r.shuffleState.correctIndex);

      r.shuffleState.revision++;
      r.shuffleState.choices     = newChoices;
      r.shuffleState.correctIndex = newCorrectIndex;

      // Mutate in-memory question so reconnect game_state sends latest order
      question.choices      = newChoices;
      question.answers      = newChoices;
      question.correctIndex = newCorrectIndex;

      const payload = {
        questionIndex:   r.state.questionIndex,
        choices:         newChoices,
        shuffleRevision: r.shuffleState.revision,
        phaseEndsAtMs:   r.state.phaseEndsAtMs,
      };

      const targets = r.shuffleState.targetPlayerIds;
      if (targets && targets.length > 0) {
        for (const pid of targets) {
          this.io.to(`player:${pid}`).emit("answer_order_changed", payload);
        }
      } else {
        this.io.to(roomId).emit("answer_order_changed", payload);
      }

      console.log(
        `[GameOrchestrator] answer_order_changed rev=${r.shuffleState.revision} room=${roomId}`,
      );
    }, 2000);
  }

  private stopShuffleInterval(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room?.shuffleState?.intervalId) return;
    clearInterval(room.shuffleState.intervalId);
    room.shuffleState.intervalId = undefined;
  }

  private endRound(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // P6 — Stop any running shuffle interval at round boundary.
    this.stopShuffleInterval(roomId);

    const playerScores: Record<string, number> = {};
    const playerRoundsWon: Record<string, number> = {};
    let maxRoundScore = -Infinity;
    let winnerId: string | undefined;
    let isTie = false;
    const topScorers: string[] = [];

    for (const [playerId, player] of Object.entries(room.state.players)) {
      playerScores[playerId] = player.roundScore;
      
      if (player.roundScore > maxRoundScore) {
        maxRoundScore = player.roundScore;
        topScorers.length = 0;
        topScorers.push(playerId);
      } else if (player.roundScore === maxRoundScore) {
        topScorers.push(playerId);
      }
    }

    if (topScorers.length === 1) {
      winnerId = topScorers[0];
    } else {
      isTie = true;
    }

    for (const [playerId, player] of Object.entries(room.state.players)) {
      playerRoundsWon[playerId] = player.roundsWon + (playerId === winnerId ? 1 : 0);
    }

    // Snapshot live stats BEFORE we apply the reducer so the rollup reflects
    // the round we just finished (roundsWon below is already incremented for the winner).
    const roundPlayerStats = this.snapshotAllPlayerStats(roomId);
    // Patch in the post-round roundsWon for the winner so consumers see consistent values.
    for (const [pid, stats] of Object.entries(roundPlayerStats)) {
      stats.roundsWon = playerRoundsWon[pid] ?? stats.roundsWon;
    }

    const roundEndedEvent: RoundEndedEvent = {
      id: room.state.lastEventId + 1,
      type: "ROUND_ENDED",
      atMs: Date.now(),
      sessionId: roomId,
      roundNumber: room.state.currentRound,
      playerScores,
      winnerId,
      isTie,
      playerRoundsWon,
      playerStats: roundPlayerStats,
    };

    room.state = applyEvent(room.state, roundEndedEvent);
    room.events.push(roundEndedEvent);

    this.io.to(roomId).emit("event", { event: roundEndedEvent });
    this.logEventToRedis(roomId, roundEndedEvent);

    // Checkpoint mid-match state to Postgres so it can survive a Redis restart.
    saveMatchSnapshot(
      roomId,
      room.state.config.mode ?? "DUO",
      room.state.currentRound,
      playerScores,
      playerRoundsWon,
      roundPlayerStats as unknown as Record<string, unknown>
    ).catch(() => { /* fire-and-forget, already logged inside saveMatchSnapshot */ });

    this.io.to(roomId).emit("round_ended", {
      roundNumber: room.state.currentRound,
      playerScores,
      winnerId,
      winnerName: winnerId ? room.state.players[winnerId]?.name : null,
      isTie,
      playerRoundsWon,
      playerStats: roundPlayerStats,
    });

    // Dedicated stats event for runtime UI consumers.
    const roundStatsRollup: MatchStats = {
      roomId,
      mode: room.state.config.mode,
      roundNumber: room.state.currentRound,
      questionIndex: room.state.questionIndex,
      players: roundPlayerStats,
      winnerId,
      isTie,
      endedAtMs: Date.now(),
    };
    this.io.to(roomId).emit("round_stats", roundStatsRollup);

    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "ROUND_SCOREBOARD",
      phaseEndsAtMs: Date.now() + room.state.config.timers.roundScoreboard,
      roundNumber: room.state.currentRound,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private transitionAfterRoundScoreboard(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const maxRoundsWon = Math.max(...Object.values(room.state.players).map(p => p.roundsWon));

    if (maxRoundsWon >= room.state.config.roundsToWin) {
      this.endMatch(roomId).catch((err: unknown) => {
        console.error(`[GameOrchestrator] Error in endMatch:`, err);
      });
    } else if (room.state.currentRound >= room.state.config.maxRounds) {
      this.endMatch(roomId).catch((err: unknown) => {
        console.error(`[GameOrchestrator] Error in endMatch:`, err);
      });
    } else {
      room.state.currentRound++;
      room.state.questionIndex = 0;

      for (const player of Object.values(room.state.players)) {
        player.roundScore = 0;
      }

      // skill_recharge passive: restore full inventory for players who have this skill
      for (const playerId of Object.keys(room.state.players)) {
        const inventory = room.state.skillInventory[playerId] ?? [];
        const hasRecharge = inventory.some(e => e.skillId === "skill_recharge");
        if (hasRecharge) {
          room.state = rechargeInventory(room.state, playerId);
          console.log(`[GameOrchestrator] skill_recharge applied for ${playerId} at round ${room.state.currentRound}`);
        }
      }

      const phaseEvent: PhaseChangedEvent = {
        id: room.state.lastEventId + 1,
        type: "PHASE_CHANGED",
        atMs: Date.now(),
        sessionId: roomId,
        fromPhase: room.state.phase,
        toPhase: "INTRO",
        phaseEndsAtMs: Date.now() + room.state.config.timers.intro,
        roundNumber: room.state.currentRound,
      };

      room.state = applyEvent(room.state, phaseEvent);
      room.events.push(phaseEvent);

      this.io.to(roomId).emit("event", { event: phaseEvent });
      this.logEventToRedis(roomId, phaseEvent);
      this.emitPhaseChanged(roomId);
      this.schedulePhaseTimeout(roomId);
    }
  }

  private async endMatch(roomId: string): Promise<void> {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const finalScores: Record<string, number> = {};
    const roundsWon: Record<string, number> = {};
    let maxRoundsWon = 0;
    let winnerId: string | undefined;
    let isTie = false;
    const topPlayers: string[] = [];

    for (const [playerId, player] of Object.entries(room.state.players)) {
      finalScores[playerId] = player.score;
      roundsWon[playerId] = player.roundsWon;

      if (player.roundsWon > maxRoundsWon) {
        maxRoundsWon = player.roundsWon;
        topPlayers.length = 0;
        topPlayers.push(playerId);
      } else if (player.roundsWon === maxRoundsWon) {
        topPlayers.push(playerId);
      }
    }

    if (topPlayers.length === 1) {
      winnerId = topPlayers[0];
    } else {
      isTie = true;
      let maxScore = -Infinity;
      for (const playerId of topPlayers) {
        if (finalScores[playerId] > maxScore) {
          maxScore = finalScores[playerId];
          winnerId = playerId;
        }
      }
    }

    const duration = room.state.startedAtMs ? Date.now() - room.state.startedAtMs : 0;

    // Final live-stats snapshot — authoritative source for Laravel persistence
    const finalPlayerStats = this.snapshotAllPlayerStats(roomId);

    const matchEndedEvent: MatchEndedEvent = {
      id: room.state.lastEventId + 1,
      type: "MATCH_ENDED",
      atMs: Date.now(),
      sessionId: roomId,
      winnerId,
      isTie,
      finalScores,
      roundsWon,
      duration,
      playerStats: finalPlayerStats,
    };

    room.state = applyEvent(room.state, matchEndedEvent);
    room.events.push(matchEndedEvent);

    this.io.to(roomId).emit("event", { event: matchEndedEvent });
    this.logEventToRedis(roomId, matchEndedEvent);

    // Persist authoritative result to Redis BEFORE notifying clients
    // Laravel reads this to finalize stats without trusting client-provided data
    await setMatchResult(roomId, {
      winnerId: winnerId ?? null,
      finalScores,
      isTie,
      decidedBy: isTie ? "total_score" : "rounds",
      roundsWon,
      duration,
      playerStats: finalPlayerStats,
    });

    // Dedicated stats event for runtime UI consumers.
    const matchStatsRollup: MatchStats = {
      roomId,
      mode: room.state.config.mode,
      roundNumber: room.state.currentRound,
      questionIndex: room.state.questionIndex,
      players: finalPlayerStats,
      winnerId,
      isTie,
      endedAtMs: Date.now(),
    };
    this.io.to(roomId).emit("match_stats", matchStatsRollup);

    // Server-to-server safety net: notify Laravel right away so the match is
    // finalized even if no front actually POSTs the per-mode finish endpoint
    // (disconnect, timeout, closed browser). Idempotent — ignored if Laravel
    // already finished the match. Fire-and-forget. Mode is forwarded so the
    // client routes to the correct controller (Task #50 added LEAGUE_TEAM).
    if (room.state.config.mode === "DUO" || room.state.config.mode === "LEAGUE_TEAM") {
      notifyMatchFinalized(roomId, room.state.config.mode).catch((err) => {
        console.error(`[GameOrchestrator] notifyMatchFinalized failed for ${roomId}:`, err);
      });
    }

    // Player memory — all modes, fire-and-forget, never blocks endMatch()
    recordPlayerMemory(roomId, room.state.config.mode).catch(() => {});

    this.io.to(roomId).emit("match_ended", {
      winnerId,
      winnerName: winnerId ? room.state.players[winnerId]?.name : null,
      isTie,
      finalScores,
      roundsWon,
      duration,
    });

    this.emitPhaseChanged(roomId);
    this.clearPhaseTimer(roomId);

    await cleanupPipeline(roomId);

    console.log(`[GameOrchestrator] Match ended in room ${roomId}, winner: ${winnerId}`);
  }

  private transitionToNextPhase(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const nextPhase = getNextPhase(room.state);
    if (!nextPhase) {
      console.log(`[GameOrchestrator] No next phase from ${room.state.phase}`);
      return;
    }

    const event = this.roomManager.transitionPhase(roomId, nextPhase);
    if (event) {
      this.io.to(roomId).emit("event", { event });
      this.emitPhaseChanged(roomId);
      this.schedulePhaseTimeout(roomId);
    }
  }

  private emitPhaseChanged(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const phaseEvent = {
      phase: room.state.phase,
      phaseStartedAtMs: room.state.phaseStartedAtMs ?? Date.now(),
      phaseEndsAtMs: room.state.phaseEndsAtMs,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
      lockedPlayerId: room.state.lockedAnswerPlayerId,
      activeEffects: room.state.activeEffects,
    };

    this.io.to(roomId).emit("phase_changed", phaseEvent);

    appendEventLog(roomId, { type: "phase_changed", ...phaseEvent, atMs: Date.now() }).catch((err: unknown) => {
      console.error(`[GameOrchestrator] Failed to append event log:`, err);
    });

    setRoomState(roomId, room.state).catch((err: unknown) => {
      console.error(`[GameOrchestrator] Failed to persist room state:`, err);
    });
  }

  private schedulePhaseTimeout(roomId: string): void {
    this.clearPhaseTimer(roomId);

    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (isTerminalPhase(room.state.phase)) {
      return;
    }

    const timeout = getPhaseTimeout(room.state);
    if (timeout <= 0) return;

    const timer = setTimeout(() => {
      this.onPhaseTimeout(roomId);
    }, timeout);

    this.phaseTimers.set(roomId, timer);
    room.phaseTimer = timer;

    console.log(`[GameOrchestrator] Scheduled phase timeout for room ${roomId}: ${timeout}ms`);
  }

  private clearPhaseTimer(roomId: string): void {
    const timer = this.phaseTimers.get(roomId);
    if (timer) {
      clearTimeout(timer);
      this.phaseTimers.delete(roomId);
    }

    const room = this.roomManager.getRoom(roomId);
    if (room?.phaseTimer) {
      clearTimeout(room.phaseTimer);
      room.phaseTimer = undefined;
    }
  }

  /**
   * cancel_error: retroactively converts the player's last -2 score delta to 0.
   * Called from the skill handler when the player activates cancel_error.
   * Returns true if the correction was applied, false if last delta was not negative.
   */
  handleCancelError(roomId: string, playerId: string): boolean {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return false;

    const roomDeltas = this.lastScoreDeltas.get(roomId);
    const entry = roomDeltas?.get(playerId);

    // Guard: entry must exist, delta must be negative, AND must be from the
    // current question (prevents stale corrections from earlier rounds)
    if (!entry || entry.delta >= 0 || entry.questionIndex !== room.state.questionIndex) {
      console.log(
        `[GameOrchestrator] cancel_error: no valid negative delta for ${playerId} ` +
        `(entry=${JSON.stringify(entry)}, currentQ=${room.state.questionIndex})`
      );
      return false;
    }

    // Revert the negative delta (add back the absolute value)
    const correction = Math.abs(entry.delta);
    const player = room.state.players[playerId];
    if (!player) return false;

    player.score += correction;
    player.roundScore += correction;

    // Mark delta as corrected so it can't be used again
    roomDeltas!.set(playerId, { questionIndex: entry.questionIndex, delta: 0 });

    const newTotalScore = player.score;
    const newRoundScore = player.roundScore;

    this.io.to(roomId).emit("score_update", {
      playerId,
      score: newTotalScore,
      roundScore: newRoundScore,
      delta: correction,
      skillsTriggered: [{ skillId: "cancel_error", playerId }],
    });

    console.log(`[GameOrchestrator] cancel_error applied for ${playerId}: +${correction} pts (was ${entry.delta})`);
    return true;
  }

  /**
   * premonition: returns the category of the next question for the given room.
   * Called from the skill handler to emit only to the activating player.
   */
  getNextQuestionCategory(roomId: string): string | null {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return null;

    const nextIndex = room.state.questionIndex + 1;
    const nextQuestion = room.state.questions[nextIndex];
    if (!nextQuestion) return null;

    return nextQuestion.category || nextQuestion.subCategory || null;
  }

  cleanup(roomId: string): void {
    // P6 — Stop shuffle interval before clearing room state.
    this.stopShuffleInterval(roomId);
    const room = this.roomManager.getRoom(roomId);
    if (room) room.shuffleState = undefined;

    this.clearPhaseTimer(roomId);
    this.pendingAnswers.delete(roomId);
    this.lastScoreDeltas.delete(roomId);
    this.syncReadyMaps.delete(roomId);
    this.syncExpectedMaps.delete(roomId);
    this.allBuzzerAnswers.delete(roomId);
    this.playerStats.delete(roomId);
    this.currentQuestionPublishedAtMs.delete(roomId);
    this.resultReadyMaps.delete(roomId);
    this.resultExpectedMaps.delete(roomId);
    this.resultEarlyArrivals.delete(roomId);
    console.log(`[GameOrchestrator] Cleaned up room ${roomId}`);
  }

  private logEventToRedis(roomId: string, event: GameEvent): void {
    appendEventLog(roomId, event).catch((err: unknown) => {
      console.error(`[GameOrchestrator] Failed to append event log for ${event.type}:`, err);
    });

    const room = this.roomManager.getRoom(roomId);
    if (room) {
      const metadata = {
        pipelineConfig: room.pipelineConfig,
        usedQuestionIds: room.usedQuestionIds ? Array.from(room.usedQuestionIds) : [],
      };
      saveRoomSnapshot(roomId, room.state, room.events, metadata).catch((err: unknown) => {
        console.error(`[GameOrchestrator] Failed to save room snapshot:`, err);
      });
    }
  }
}
