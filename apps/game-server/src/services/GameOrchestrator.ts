import type { Server as SocketIOServer } from "socket.io";
import type { RoomManager, Room } from "./RoomManager.js";
import type { Question, Mode, Phase } from "@strategybuzzer/shared";
import type { GameEvent, PhaseChangedEvent, QuestionPublishedEvent, AnswerRevealedEvent, AnswerSubmittedEvent, RoundEndedEvent, MatchEndedEvent, BuzzReceivedEvent, GameStartedEvent } from "@strategybuzzer/shared";
import { applyEvent, hasActiveEffect, expireEffects, applyScoreEffects, rechargeInventory } from "@strategybuzzer/game-engine";
import { getNextPhase, getPhaseTimeout, isTerminalPhase } from "@strategybuzzer/game-engine";
import { initQuestionPipeline, fetchNextBlock, getPipelineStatus, cleanupPipeline } from "./QuestionService.js";
import { appendEventLog, setRoomState, setMatchResult } from "./RedisService.js";
import { rateLimiter } from "../middleware/rateLimiter.js";
import { saveRoomSnapshot } from "./RoomRecovery.js";

export class GameOrchestrator {
  private io: SocketIOServer;
  private roomManager: RoomManager;
  private phaseTimers: Map<string, NodeJS.Timeout> = new Map();
  private pendingAnswers: Map<string, { playerId: string; answer: number | string | boolean; submittedAtMs: number }> = new Map();
  // Store answers from ALL buzzers (key = roomId, value = Map of playerId -> answer data)
  private allBuzzerAnswers: Map<string, Map<string, { answer: number | string | boolean; submittedAtMs: number; buzzOrder: number }>> = new Map();
  // Track last score delta per player for cancel_error retroactive correction.
  // Stores { questionIndex, delta } so cancel_error only applies to the
  // question that was just scored (prevents stale cross-question corrections).
  private lastScoreDeltas: Map<string, Map<string, { questionIndex: number; delta: number }>> = new Map();
  // Track which players have sent question_page_ready during SYNC phase
  private syncReadyMaps: Map<string, Set<string>> = new Map();

  constructor(io: SocketIOServer, roomManager: RoomManager) {
    this.io = io;
    this.roomManager = roomManager;
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

    const pipelineResult = await initQuestionPipeline({
      roomId,
      theme: room.pipelineConfig.theme,
      niveau: room.pipelineConfig.niveau,
      language: room.pipelineConfig.language,
      maxRounds: room.pipelineConfig.maxRounds,
    });

    if (!pipelineResult.success || !pipelineResult.firstQuestion) {
      console.error(`[GameOrchestrator] Failed to initialize question pipeline for room ${roomId}: ${pipelineResult.error}`);
      return { success: false, error: pipelineResult.error || "Failed to initialize questions" };
    }

    room.state.questions = [pipelineResult.firstQuestion];
    room.usedQuestionIds ??= new Set();
      room.usedQuestionIds.add(pipelineResult.firstQuestion.id);
    console.log(`[GameOrchestrator] Pipeline initialized with first question for room ${roomId}`);

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
  }

  handleAnswer(roomId: string, playerId: string, answer: number | string | boolean): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // V3: Accept answers during QUESTION_ACTIVE, ANSWER_COLLECTION, and ANSWER_SELECTION (legacy)
    const acceptablePhases = ["QUESTION_ACTIVE", "ANSWER_COLLECTION", "ANSWER_SELECTION"];
    if (!acceptablePhases.includes(room.state.phase)) {
      console.log(`[GameOrchestrator] Answer rejected: not in answer phase (${room.state.phase})`);
      return;
    }

    // Check if this player buzzed (is in the buzz queue)
    const buzzIndex = room.state.buzzQueue.findIndex(b => b.playerId === playerId);
    if (buzzIndex === -1) {
      console.log(`[GameOrchestrator] Answer rejected: ${playerId} did not buzz`);
      return;
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
    const buzzOrder = buzzIndex + 1; // 1-indexed buzz order

    // Store this buzzer's answer
    roomAnswers.set(playerId, { answer, submittedAtMs, buzzOrder });

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

    // V3: Do NOT reveal early — let QUESTION_ACTIVE run its full timer.
    // ANSWER_COLLECTION will catch any remaining answers after the timer expires.
  }

  private revealAnswer(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const currentQuestion = room.state.currentQuestion;
    
    if (!currentQuestion) {
      console.error(`[GameOrchestrator] No current question for room ${roomId}`);
      this.transitionToNextPhase(roomId);
      return;
    }

    const fullQuestion = room.state.questions[room.state.questionIndex];
    
    // Determine correct answer
    let correctAnswer: number | string | boolean = 0;
    if (fullQuestion) {
      if (fullQuestion.type === "MCQ" && fullQuestion.correctIndex !== undefined) {
        correctAnswer = fullQuestion.correctIndex;
      } else if (fullQuestion.type === "TRUE_FALSE" && fullQuestion.correctBool !== undefined) {
        correctAnswer = fullQuestion.correctBool;
      } else if (fullQuestion.type === "TEXT" && fullQuestion.correctText !== undefined) {
        correctAnswer = fullQuestion.correctText;
      }
    }

    // V3: Transition to RESULT phase (replaces REVEAL)
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

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);

    // Score ALL buzzers using allBuzzerAnswers as single source of truth
    this.scoreAllBuzzers(roomId, correctAnswer, fullQuestion);

    this.pendingAnswers.delete(roomId);
    this.allBuzzerAnswers.delete(roomId);
    this.schedulePhaseTimeout(roomId);
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

      if (buzzerAnswer) {
        // Player buzzed AND answered - score based on correctness
        playerAnswer = buzzerAnswer.answer;
        
        if (question) {
          if (question.type === "MCQ" && question.correctIndex !== undefined) {
            isCorrect = buzzerAnswer.answer === question.correctIndex;
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
      this.io.to(roomId).emit("answer_revealed", {
        playerId: buzzer.playerId,
        playerName: player?.name,
        answer: playerAnswer,
        isCorrect,
        correctAnswer,
        correctIndex: question?.correctIndex,
        correctBool: question?.correctBool,
        correctText: question?.correctText,
        pointsEarned,
        totalScore: newTotalScore,
        roundScore: newRoundScore,
        funFact: question?.funFact,
        didYouKnow: question?.funFact,
        skillsTriggered: scoreEffectResult.skillsTriggered,
      });

      this.io.to(roomId).emit("score_update", {
        playerId: buzzer.playerId,
        score: newTotalScore,
        roundScore: newRoundScore,
        delta: pointsEarned,
        skillsTriggered: scoreEffectResult.skillsTriggered,
      });
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
        this.handleAnswerTimeout(roomId);
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

    const question = room.state.questions[room.state.questionIndex];
    if (!question) {
      console.error(`[GameOrchestrator] No question at index ${room.state.questionIndex}`);
      return;
    }

    const rawChoices = (question as Record<string, unknown>).choices || (question as Record<string, unknown>).answers;
    const sanitizedChoices = this.sanitizeChoices(rawChoices as unknown[] | undefined);

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
      choices: sanitizedChoices,
      category: question.category,
      subCategory: question.subCategory,
      difficulty: question.difficulty,
      timeLimitMs: baseTimeLimit,
    };

    room.state = applyEvent(room.state, publishEvent);
    room.events.push(publishEvent);

    this.io.to(roomId).emit("event", { event: publishEvent });
    this.logEventToRedis(roomId, publishEvent);
    
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
        choices: sanitizedChoices,
        category: question.category,
        subCategory: question.subCategory,
        difficulty: question.difficulty,
        timeLimitMs: playerTimeLimit,
        phaseEndsAtMs: playerPhaseEndsAtMs,
        totalQuestions: room.state.questions.length,
        reduceTimeActive: isReduceTimeActive,
        activeEffects: room.state.activeEffects.filter(e => e.targetPlayerId === playerId),
      });
      
      if (isReduceTimeActive) {
        console.log(`[GameOrchestrator] Player ${playerId} has reduce_time active (−${reductionMs}ms → ${playerTimeLimit}ms)`);
      }
    }

    console.log(`[GameOrchestrator] Broadcast question ${room.state.questionIndex + 1}/${room.state.questions.length}`);
  }

  private handleQuestionTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // V3: QUESTION_ACTIVE timeout → ANSWER_COLLECTION (2-3s grace) then RESULT
    // If no buzzers at all, skip ANSWER_COLLECTION and go straight to RESULT
    if (room.state.buzzQueue.length === 0) {
      console.log(`[GameOrchestrator] No buzzers in room ${roomId} — skipping ANSWER_COLLECTION`);
      this.revealAnswer(roomId);
      return;
    }

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

    console.log(`[GameOrchestrator] ANSWER_COLLECTION phase (${answerCollectionTimer}ms) for room ${roomId} with ${room.state.buzzQueue.length} buzzer(s)`);
  }

  private handleAnswerTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // All buzzers who didn't answer will be scored as timeout (-2 pts) in scoreAllBuzzers
    // We use allBuzzerAnswers as single source of truth - buzzers not in the map = timeout
    console.log(`[GameOrchestrator] ANSWER_COLLECTION timeout in room ${roomId} - revealing answers`);
    this.revealAnswer(roomId);
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

    // Initialize the ready map for this SYNC phase
    this.syncReadyMaps.set(roomId, new Set<string>());

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
    if (!readyMap) return;

    readyMap.add(playerId);
    console.log(`[GameOrchestrator] question_page_ready from ${playerId} in room ${roomId} (${readyMap.size} ready)`);

    // Count human players (non-bot) that are connected
    const humanPlayers = Object.values(room.state.players).filter(
      p => !p.isBot && p.isConnected
    );

    if (humanPlayers.length === 0) {
      this.clearPhaseTimer(roomId);
      this.syncReadyMaps.delete(roomId);
      this.transitionToQuestionActive(roomId);
      return;
    }

    const allHumansReady = humanPlayers.every(p => readyMap.has(p.id));
    if (allHumansReady) {
      console.log(`[GameOrchestrator] All ${humanPlayers.length} human player(s) ready — early exit SYNC for room ${roomId}`);
      this.clearPhaseTimer(roomId);
      this.syncReadyMaps.delete(roomId);
      this.transitionToQuestionActive(roomId);
    }
  }

  private endRound(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

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
    };

    room.state = applyEvent(room.state, roundEndedEvent);
    room.events.push(roundEndedEvent);

    this.io.to(roomId).emit("event", { event: roundEndedEvent });
    this.logEventToRedis(roomId, roundEndedEvent);
    this.io.to(roomId).emit("round_ended", {
      roundNumber: room.state.currentRound,
      playerScores,
      winnerId,
      winnerName: winnerId ? room.state.players[winnerId]?.name : null,
      isTie,
      playerRoundsWon,
    });

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
    });

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
    this.clearPhaseTimer(roomId);
    this.pendingAnswers.delete(roomId);
    this.lastScoreDeltas.delete(roomId);
    this.syncReadyMaps.delete(roomId);
    this.allBuzzerAnswers.delete(roomId);
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
