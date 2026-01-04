import type { Server as SocketIOServer } from "socket.io";
import type { RoomManager, Room } from "./RoomManager.js";
import type { Question, Mode, Phase } from "../../../../packages/shared/src/types.js";
import type { GameEvent, PhaseChangedEvent, QuestionPublishedEvent, AnswerRevealedEvent, AnswerSubmittedEvent, RoundEndedEvent, MatchEndedEvent } from "../../../../packages/shared/src/events.js";
import { applyEvent } from "../../../../packages/game-engine/src/reducer.js";
import { getNextPhase, getPhaseTimeout, isTerminalPhase } from "../../../../packages/game-engine/src/state-machine.js";

export class GameOrchestrator {
  private io: SocketIOServer;
  private roomManager: RoomManager;
  private phaseTimers: Map<string, NodeJS.Timeout> = new Map();
  private pendingAnswers: Map<string, { playerId: string; answer: number | string | boolean; submittedAtMs: number }> = new Map();

  constructor(io: SocketIOServer, roomManager: RoomManager) {
    this.io = io;
    this.roomManager = roomManager;
  }

  startGame(roomId: string): { success: boolean; error?: string } {
    const room = this.roomManager.getRoom(roomId);
    if (!room) {
      console.error(`[GameOrchestrator] Cannot start game: room ${roomId} not found`);
      return { success: false, error: "Room not found" };
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

    if (room.state.lockedAnswerPlayerId !== playerId) {
      console.log(`[GameOrchestrator] Answer rejected: ${playerId} is not the locked player`);
      return;
    }

    const submittedAtMs = Date.now();

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

    this.pendingAnswers.set(roomId, { playerId, answer, submittedAtMs });

    this.clearPhaseTimer(roomId);
    this.revealAnswer(roomId);
  }

  private revealAnswer(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const pendingAnswer = this.pendingAnswers.get(roomId);
    const currentQuestion = room.state.currentQuestion;
    
    if (!currentQuestion) {
      console.error(`[GameOrchestrator] No current question for room ${roomId}`);
      this.transitionToNextPhase(roomId);
      return;
    }

    const fullQuestion = room.state.questions[room.state.questionIndex];
    
    let isCorrect = false;
    let correctAnswer: number | string | boolean = 0;

    if (fullQuestion) {
      if (fullQuestion.type === "MCQ" && fullQuestion.correctIndex !== undefined) {
        correctAnswer = fullQuestion.correctIndex;
        isCorrect = pendingAnswer?.answer === fullQuestion.correctIndex;
      } else if (fullQuestion.type === "TRUE_FALSE" && fullQuestion.correctBool !== undefined) {
        correctAnswer = fullQuestion.correctBool;
        isCorrect = pendingAnswer?.answer === fullQuestion.correctBool;
      } else if (fullQuestion.type === "TEXT" && fullQuestion.correctText !== undefined) {
        correctAnswer = fullQuestion.correctText;
        isCorrect = String(pendingAnswer?.answer).toLowerCase() === fullQuestion.correctText.toLowerCase();
      }
    }

    const player = pendingAnswer ? room.state.players[pendingAnswer.playerId] : null;
    const playerId = pendingAnswer?.playerId || "";

    let timeRemainingMs = 0;
    if (room.state.phaseStartedAtMs && pendingAnswer) {
      const questionDuration = room.state.config.timers.questionActive;
      const buzzEntry = room.state.buzzQueue.find(b => b.playerId === playerId);
      if (buzzEntry && room.state.phaseStartedAtMs) {
        timeRemainingMs = questionDuration - (buzzEntry.atMs - room.state.phaseStartedAtMs);
      }
    }

    const pointsEarned = pendingAnswer 
      ? this.calculateScore(isCorrect, timeRemainingMs, room.state.config.mode)
      : 0;

    const newRoundScore = (player?.roundScore || 0) + pointsEarned;
    const newTotalScore = (player?.score || 0) + pointsEarned;

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
    this.emitPhaseChanged(roomId);

    if (pendingAnswer) {
      const revealEvent: AnswerRevealedEvent = {
        id: room.state.lastEventId + 1,
        type: "ANSWER_REVEALED",
        atMs: Date.now(),
        sessionId: roomId,
        playerId,
        answer: pendingAnswer.answer,
        isCorrect,
        correctAnswer,
        pointsEarned,
        buzzTimeMs: room.state.buzzQueue.find(b => b.playerId === playerId)?.atMs || 0,
        totalScore: newTotalScore,
        roundScore: newRoundScore,
        funFact: fullQuestion?.funFact,
      };

      room.state = applyEvent(room.state, revealEvent);
      room.events.push(revealEvent);

      this.io.to(roomId).emit("event", { event: revealEvent });
      this.io.to(roomId).emit("answer_revealed", {
        playerId,
        playerName: player?.name,
        answer: pendingAnswer.answer,
        isCorrect,
        correctAnswer,
        pointsEarned,
        totalScore: newTotalScore,
        roundScore: newRoundScore,
        funFact: fullQuestion?.funFact,
      });

      this.io.to(roomId).emit("score_update", {
        playerId,
        score: newTotalScore,
        roundScore: newRoundScore,
        delta: pointsEarned,
      });
    }

    this.pendingAnswers.delete(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private calculateScore(isCorrect: boolean, timeRemainingMs: number, mode: Mode): number {
    if (!isCorrect) {
      if (mode === "MASTER") {
        return 0;
      }
      return -2;
    }

    if (timeRemainingMs > 3000) {
      return 2;
    } else if (timeRemainingMs >= 1000) {
      return 1;
    } else {
      return 0;
    }
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
        this.handleWaitingTimeout(roomId);
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
    this.emitPhaseChanged(roomId);
    this.broadcastQuestion(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private broadcastQuestion(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const question = room.state.questions[room.state.questionIndex];
    if (!question) {
      console.error(`[GameOrchestrator] No question at index ${room.state.questionIndex}`);
      return;
    }

    const publishEvent: QuestionPublishedEvent = {
      id: room.state.lastEventId + 1,
      type: "QUESTION_PUBLISHED",
      atMs: Date.now(),
      sessionId: roomId,
      questionIndex: room.state.questionIndex,
      questionId: question.id,
      text: question.text,
      choices: question.choices,
      category: question.category,
      subCategory: question.subCategory,
      difficulty: question.difficulty,
      timeLimitMs: question.timeLimitMs || room.state.config.timers.questionActive,
    };

    room.state = applyEvent(room.state, publishEvent);
    room.events.push(publishEvent);

    this.io.to(roomId).emit("event", { event: publishEvent });
    this.io.to(roomId).emit("question_published", {
      questionIndex: room.state.questionIndex,
      questionId: question.id,
      text: question.text,
      choices: question.choices,
      category: question.category,
      subCategory: question.subCategory,
      difficulty: question.difficulty,
      timeLimitMs: question.timeLimitMs || room.state.config.timers.questionActive,
      totalQuestions: room.state.questions.length,
    });

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

    const lockedPlayerId = room.state.lockedAnswerPlayerId;
    if (lockedPlayerId) {
      this.pendingAnswers.set(roomId, {
        playerId: lockedPlayerId,
        answer: -1,
        submittedAtMs: Date.now(),
      });
    }

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

  private transitionToWaiting(roomId: string): void {
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
    this.emitPhaseChanged(roomId);

    this.io.to(roomId).emit("waiting_block", {
      nextBlockStart: blockInfo.nextBlockStart,
      nextBlockEnd: blockInfo.nextBlockEnd,
      waitingEndsAtMs,
    });

    console.log(`[GameOrchestrator] Waiting phase for block ${blockInfo.nextBlockStart}-${blockInfo.nextBlockEnd}`);

    this.schedulePhaseTimeout(roomId);
  }

  private handleWaitingTimeout(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

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
      this.transitionToWaiting(roomId);
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
    this.emitPhaseChanged(roomId);
    this.schedulePhaseTimeout(roomId);
  }

  private transitionAfterRoundScoreboard(roomId: string): void {
    const room = this.roomManager.getRoom(roomId);
    if (!room) return;

    const maxRoundsWon = Math.max(...Object.values(room.state.players).map(p => p.roundsWon));

    if (maxRoundsWon >= room.state.config.roundsToWin) {
      this.endMatch(roomId);
    } else if (room.state.currentRound >= room.state.config.maxRounds) {
      this.endMatch(roomId);
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
      this.emitPhaseChanged(roomId);
      this.schedulePhaseTimeout(roomId);
    }
  }

  private endMatch(roomId: string): void {
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

    this.io.to(roomId).emit("phase_changed", {
      phase: room.state.phase,
      phaseEndsAtMs: room.state.phaseEndsAtMs,
      questionIndex: room.state.questionIndex,
      roundNumber: room.state.currentRound,
      lockedPlayerId: room.state.lockedAnswerPlayerId,
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
}
