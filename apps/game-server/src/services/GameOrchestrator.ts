import type { Server as SocketIOServer } from "socket.io";
import type { RoomManager, Room } from "./RoomManager.js";
import type { Question, Mode, Phase } from "../../../../packages/shared/src/types.js";
import type { GameEvent, PhaseChangedEvent, QuestionPublishedEvent, AnswerRevealedEvent, AnswerSubmittedEvent, RoundEndedEvent, MatchEndedEvent, BuzzReceivedEvent, GameStartedEvent } from "../../../../packages/shared/src/events.js";
import { applyEvent } from "../../../../packages/game-engine/src/reducer.js";
import { getNextPhase, getPhaseTimeout, isTerminalPhase } from "../../../../packages/game-engine/src/state-machine.js";
import { initQuestionPipeline, fetchNextBlock, getPipelineStatus, cleanupPipeline } from "./QuestionService.js";
import { appendEventLog, setRoomState } from "./RedisService.js";
import { saveRoomSnapshot } from "./RoomRecovery.js";

export class GameOrchestrator {
  private io: SocketIOServer;
  private roomManager: RoomManager;
  private phaseTimers: Map<string, NodeJS.Timeout> = new Map();
  private pendingAnswers: Map<string, { playerId: string; answer: number | string | boolean; submittedAtMs: number }> = new Map();
  // Store answers from ALL buzzers (key = roomId, value = Map of playerId -> answer data)
  private allBuzzerAnswers: Map<string, Map<string, { answer: number | string | boolean; submittedAtMs: number; buzzOrder: number }>> = new Map();

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

    if (room.state.lockedAnswerPlayerId === playerId) {
      this.clearPhaseTimer(roomId);

      const player = room.state.players[playerId];
      this.io.to(roomId).emit("buzz_winner", {
        playerId,
        playerName: player?.name,
        position: 1,
      });

      this.emitPhaseChanged(roomId);
      this.schedulePhaseTimeout(roomId);

      console.log(`[GameOrchestrator] Buzz winner: ${player?.name} (${playerId})`);
    }
  }

  handleAnswer(roomId: string, playerId: string, answer: number | string | boolean): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    if (room.state.phase !== "ANSWER_SELECTION") {
      console.log(`[GameOrchestrator] Answer rejected: not in ANSWER_SELECTION phase`);
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

    // Check if all buzzers have answered
    if (roomAnswers.size >= room.state.buzzQueue.length) {
      console.log(`[GameOrchestrator] All ${roomAnswers.size} buzzers answered - revealing now`);
      this.clearPhaseTimer(roomId);
      this.revealAnswer(roomId);
    }
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

    // Transition to REVEAL phase first
    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "REVEAL",
      phaseEndsAtMs: Date.now() + room.state.config.timers.reveal,
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
        // Player buzzed but did NOT answer (timeout) - penalized with -2
        pointsEarned = -2;
        console.log(`[GameOrchestrator] Buzzer ${buzzer.playerId} (order: ${buzzOrder}) timed out (no answer): ${pointsEarned} pts`);
      }

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
      });

      this.io.to(roomId).emit("score_update", {
        playerId: buzzer.playerId,
        score: newTotalScore,
        roundScore: newRoundScore,
        delta: pointsEarned,
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

      case "REVEAL":
        this.transitionAfterReveal(roomId);
        break;

      case "WAITING":
        this.handleWaitingTimeout(roomId).catch(err => {
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
    this.broadcastQuestion(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private sanitizeChoices(choices: unknown[] | undefined): string[] | undefined {
    if (!choices || !Array.isArray(choices)) {
      return undefined;
    }
    return choices.map((choice: unknown) => {
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
    });
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

    const baseTimeLimit = question.timeLimitMs || room.state.config.timers.questionActive;
    const reducedTimeLimit = baseTimeLimit - 2000;

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
      const isReduceTimeActive = this.roomManager.isReduceTimeActive(roomId, playerId);
      const playerTimeLimit = isReduceTimeActive ? reducedTimeLimit : baseTimeLimit;
      
      this.io.to(`player:${playerId}`).emit("question_published", {
        questionIndex: room.state.questionIndex,
        questionId: question.id,
        text: question.text,
        choices: sanitizedChoices,
        category: question.category,
        subCategory: question.subCategory,
        difficulty: question.difficulty,
        timeLimitMs: playerTimeLimit,
        totalQuestions: room.state.questions.length,
        reduceTimeActive: isReduceTimeActive,
      });
      
      if (isReduceTimeActive) {
        const stillActive = this.roomManager.decrementReduceTime(roomId, playerId);
        console.log(`[GameOrchestrator] Player ${playerId} has reduced time (${playerTimeLimit}ms), remaining: ${stillActive ? 'active' : 'expired'}`);
      }
    }

    console.log(`[GameOrchestrator] Broadcast question ${room.state.questionIndex + 1}/${room.state.questions.length}`);
  }

  private handleQuestionTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const phaseEvent: PhaseChangedEvent = {
      id: room.state.lastEventId + 1,
      type: "PHASE_CHANGED",
      atMs: Date.now(),
      sessionId: roomId,
      fromPhase: room.state.phase,
      toPhase: "REVEAL",
      phaseEndsAtMs: Date.now() + room.state.config.timers.reveal,
    };

    room.state = applyEvent(room.state, phaseEvent);
    room.events.push(phaseEvent);

    this.io.to(roomId).emit("event", { event: phaseEvent });
    this.logEventToRedis(roomId, phaseEvent);
    this.emitPhaseChanged(roomId);

    const fullQuestion = room.state.questions[room.state.questionIndex];
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

    this.io.to(roomId).emit("answer_revealed", {
      playerId: null,
      playerName: null,
      answer: null,
      isCorrect: false,
      correctAnswer,
      correctIndex: fullQuestion?.correctIndex,
      correctBool: fullQuestion?.correctBool,
      correctText: fullQuestion?.correctText,
      pointsEarned: 0,
      totalScore: 0,
      roundScore: 0,
      timeout: true,
      funFact: fullQuestion?.funFact,
    });

    this.schedulePhaseTimeout(roomId);
  }

  private handleAnswerTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    // All buzzers who didn't answer will be scored as timeout (-2 pts) in scoreAllBuzzers
    // We use allBuzzerAnswers as single source of truth - buzzers not in the map = timeout
    console.log(`[GameOrchestrator] Answer timeout in room ${roomId} - revealing answers`);
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
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const isLastQuestion = room.state.questionIndex >= room.state.config.questionsPerRound - 1;

    if (isLastQuestion) {
      this.endRound(roomId);
    } else if (this.shouldShowWaiting(room.state.questionIndex)) {
      this.transitionToWaiting(roomId).catch(err => {
        console.error(`[GameOrchestrator] Error in transitionToWaiting:`, err);
      });
    } else {
      room.state.questionIndex++;
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
      this.endMatch(roomId).catch(err => {
        console.error(`[GameOrchestrator] Error in endMatch:`, err);
      });
    } else if (room.state.currentRound >= room.state.config.maxRounds) {
      this.endMatch(roomId).catch(err => {
        console.error(`[GameOrchestrator] Error in endMatch:`, err);
      });
    } else {
      room.state.currentRound++;
      room.state.questionIndex = 0;

      for (const player of Object.values(room.state.players)) {
        player.roundScore = 0;
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
      phaseEndsAtMs: room.state.phaseEndsAtMs,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
      lockedPlayerId: room.state.lockedAnswerPlayerId,
    };

    this.io.to(roomId).emit("phase_changed", phaseEvent);

    appendEventLog(roomId, { type: "phase_changed", ...phaseEvent, atMs: Date.now() }).catch(err => {
      console.error(`[GameOrchestrator] Failed to append event log:`, err);
    });

    setRoomState(roomId, room.state).catch(err => {
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

  cleanup(roomId: string): void {
    this.clearPhaseTimer(roomId);
    this.pendingAnswers.delete(roomId);
    console.log(`[GameOrchestrator] Cleaned up room ${roomId}`);
  }

  private logEventToRedis(roomId: string, event: GameEvent): void {
    appendEventLog(roomId, event).catch(err => {
      console.error(`[GameOrchestrator] Failed to append event log for ${event.type}:`, err);
    });

    const room = this.roomManager.getRoom(roomId);
    if (room) {
      const metadata = {
        pipelineConfig: room.pipelineConfig,
        usedQuestionIds: room.usedQuestionIds ? Array.from(room.usedQuestionIds) : [],
      };
      saveRoomSnapshot(roomId, room.state, room.events, metadata).catch(err => {
        console.error(`[GameOrchestrator] Failed to save room snapshot:`, err);
      });
    }
  }
}
