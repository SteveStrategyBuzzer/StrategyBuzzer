import type { GameState, Player } from "../../shared/src/types.js";
import type { GameEvent } from "../../shared/src/events.js";

function assert(cond: unknown, msg: string): asserts cond {
  if (!cond) throw new Error(`GameEngine: ${msg}`);
}

function createDefaultPlayer(
  id: string,
  name: string,
  options: Partial<Player> = {}
): Player {
  return {
    id,
    name,
    score: 0,
    roundScore: 0,
    roundsWon: 0,
    lives: 3,
    isConnected: true,
    lastSeenMs: Date.now(),
    skills: {},
    ...options,
  };
}

export function applyEvent(state: GameState, event: GameEvent): GameState {
  assert(event.id === state.lastEventId + 1, `Bad event id order: expected ${state.lastEventId + 1}, got ${event.id}`);

  const s: GameState = structuredClone(state);
  s.lastEventId = event.id;
  s.version++;

  switch (event.type) {
    case "PLAYER_JOINED": {
      if (s.players[event.playerId]) {
        s.players[event.playerId].isConnected = true;
        s.players[event.playerId].lastSeenMs = event.atMs;
        return s;
      }
      
      s.players[event.playerId] = createDefaultPlayer(event.playerId, event.name, {
        avatarId: event.avatarId,
        strategicAvatarId: event.strategicAvatarId,
        isBot: event.isBot,
        isHost: event.isHost,
        teamId: event.teamId,
        division: event.division,
        lastSeenMs: event.atMs,
      });
      s.order.push(event.playerId);
      return s;
    }

    case "PLAYER_LEFT": {
      if (!s.players[event.playerId]) return s;
      
      if (event.reason === "disconnect") {
        s.players[event.playerId].isConnected = false;
      } else {
        delete s.players[event.playerId];
        s.order = s.order.filter((id) => id !== event.playerId);
      }
      
      s.buzzQueue = s.buzzQueue.filter((b) => b.playerId !== event.playerId);
      if (s.lockedAnswerPlayerId === event.playerId) {
        s.lockedAnswerPlayerId = undefined;
      }
      return s;
    }

    case "PLAYER_READY": {
      if (!s.players[event.playerId]) return s;
      return s;
    }

    case "GAME_STARTED": {
      assert(s.phase === "LOBBY", "Game already started");
      s.startedAtMs = event.atMs;
      s.phase = "INTRO";
      s.phaseStartedAtMs = event.atMs;
      s.phaseEndsAtMs = event.atMs + s.config.timers.intro;
      s.currentRound = 1;
      s.questionIndex = 0;
      s.buzzQueue = [];
      s.answeredPlayerIds = [];
      
      for (const playerId of Object.keys(s.players)) {
        s.players[playerId].score = 0;
        s.players[playerId].roundScore = 0;
        s.players[playerId].roundsWon = 0;
      }
      return s;
    }

    case "PHASE_CHANGED": {
      s.phase = event.toPhase;
      s.phaseStartedAtMs = event.atMs;
      s.phaseEndsAtMs = event.phaseEndsAtMs;
      
      if (event.toPhase === "QUESTION_ACTIVE") {
        s.buzzQueue = [];
        s.lockedAnswerPlayerId = undefined;
        s.answeredPlayerIds = [];
        s.lastAnswer = undefined;
        
        if (event.questionIndex !== undefined) {
          s.questionIndex = event.questionIndex;
          s.currentQuestion = s.questions[event.questionIndex];
        }
      }
      
      if (event.toPhase === "ROUND_SCOREBOARD" && event.roundNumber !== undefined) {
        s.currentRound = event.roundNumber;
      }
      
      return s;
    }

    case "QUESTION_PUBLISHED": {
      s.questionIndex = event.questionIndex;
      s.currentQuestion = {
        id: event.questionId,
        text: event.text,
        type: "MCQ",
        choices: event.choices,
        category: event.category,
        subCategory: event.subCategory,
        difficulty: event.difficulty as 1 | 2 | 3 | 4 | 5,
        timeLimitMs: event.timeLimitMs,
      };
      return s;
    }

    case "BUZZ_RECEIVED": {
      assert(s.phase === "QUESTION_ACTIVE", "Buzz not allowed in current phase");
      assert(!!s.players[event.playerId], "Unknown player");
      
      if (s.buzzQueue.some((b) => b.playerId === event.playerId)) return s;
      if (s.answeredPlayerIds.includes(event.playerId)) return s;
      
      s.buzzQueue.push({
        playerId: event.playerId,
        atMs: event.buzzTimeMs,
        latencyMs: event.latencyMs,
      });
      
      if (s.buzzQueue.length === 1) {
        s.lockedAnswerPlayerId = event.playerId;
        s.phase = "ANSWER_SELECTION";
        s.phaseStartedAtMs = event.atMs;
        s.phaseEndsAtMs = event.atMs + s.config.timers.answerSelection;
      }
      return s;
    }

    case "ANSWER_SUBMITTED": {
      assert(s.phase === "ANSWER_SELECTION", "Answer not allowed in current phase");
      // Allow any player who buzzed (is in buzzQueue) to answer, not just first buzzer
      const isInBuzzQueue = s.buzzQueue.some(b => b.playerId === event.playerId);
      assert(isInBuzzQueue, "Player did not buzz for this question");
      assert(!!s.players[event.playerId], "Unknown player");
      
      if (!s.answeredPlayerIds.includes(event.playerId)) {
        s.answeredPlayerIds.push(event.playerId);
      }
      return s;
    }

    case "ANSWER_REVEALED": {
      s.lastAnswer = {
        playerId: event.playerId,
        answer: event.answer,
        isCorrect: event.isCorrect,
        pointsEarned: event.pointsEarned,
        buzzTimeMs: event.buzzTimeMs,
      };
      
      if (s.players[event.playerId]) {
        s.players[event.playerId].score = event.totalScore;
        s.players[event.playerId].roundScore = event.roundScore;
      }
      
      if (s.currentQuestion) {
        s.currentQuestion.funFact = event.funFact;
      }
      return s;
    }

    case "TIMEOUT": {
      return s;
    }

    case "SKILL_ACTIVATED": {
      const player = s.players[event.playerId];
      if (!player) return s;
      
      const skill = player.skills[event.skillId];
      if (skill) {
        skill.usesLeft = Math.max(0, skill.usesLeft - 1);
        if (event.duration) {
          skill.cooldownUntilMs = event.atMs + event.duration;
        }
      }
      return s;
    }

    case "SKILL_EFFECT_APPLIED": {
      return s;
    }

    case "SCORE_UPDATED": {
      const player = s.players[event.playerId];
      if (!player) return s;
      
      player.score = event.newScore;
      player.roundScore = event.newRoundScore;
      return s;
    }

    case "ROUND_ENDED": {
      s.roundResults.push({
        roundNumber: event.roundNumber,
        playerScores: event.playerScores,
        winnerId: event.winnerId,
        isTie: event.isTie,
      });
      
      for (const [playerId, roundsWon] of Object.entries(event.playerRoundsWon)) {
        if (s.players[playerId]) {
          s.players[playerId].roundsWon = roundsWon;
          s.players[playerId].roundScore = 0;
        }
      }
      return s;
    }

    case "TIEBREAKER_STARTED": {
      s.tiebreakerMode = event.mode;
      return s;
    }

    case "TIEBREAKER_CHOICE": {
      return s;
    }

    case "MATCH_ENDED": {
      s.phase = "MATCH_END";
      s.endedAtMs = event.atMs;
      s.phaseEndsAtMs = undefined;
      
      for (const [playerId, score] of Object.entries(event.finalScores)) {
        if (s.players[playerId]) {
          s.players[playerId].score = score;
        }
      }
      
      for (const [playerId, roundsWon] of Object.entries(event.roundsWon)) {
        if (s.players[playerId]) {
          s.players[playerId].roundsWon = roundsWon;
        }
      }
      return s;
    }

    case "VOICE_CHANNEL_JOINED": {
      s.voiceChannelId = event.channelId;
      return s;
    }

    case "VOICE_CHANNEL_LEFT": {
      return s;
    }

    default: {
      const _exhaustiveCheck: never = event;
      return s;
    }
  }
}

export function createInitialState(
  sessionId: string,
  lobbyCode: string,
  config: GameState["config"]
): GameState {
  return {
    sessionId,
    lobbyCode,
    createdAtMs: Date.now(),
    phase: "LOBBY",
    config,
    players: {},
    order: [],
    currentRound: 0,
    questionIndex: 0,
    questions: [],
    roundResults: [],
    buzzQueue: [],
    answeredPlayerIds: [],
    lastEventId: 0,
    version: 0,
  };
}
