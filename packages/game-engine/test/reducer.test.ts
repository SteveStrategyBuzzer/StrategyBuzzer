import { describe, it, expect } from 'vitest';
import { applyEvent, createInitialState } from '../src/reducer.js';
import {
  createTestConfig,
  createTestGameState,
  createGameStateWithPlayers,
  createActiveGameState,
  createBaseEvent,
  createTestQuestion,
} from './fixtures.js';
import type { GameEvent } from '../../../packages/shared/src/events.js';

describe('createInitialState', () => {
  it('should create initial state in LOBBY phase', () => {
    const config = createTestConfig();
    const state = createInitialState('session-1', 'ABCD', config);
    
    expect(state.sessionId).toBe('session-1');
    expect(state.lobbyCode).toBe('ABCD');
    expect(state.phase).toBe('LOBBY');
    expect(state.players).toEqual({});
    expect(state.order).toEqual([]);
    expect(state.lastEventId).toBe(0);
    expect(state.version).toBe(0);
  });
});

describe('applyEvent - PLAYER_JOINED', () => {
  it('should add a new player to the game', () => {
    const state = createTestGameState();
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_JOINED',
      playerId: 'player1',
      name: 'Alice',
      avatarId: 'avatar1',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1']).toBeDefined();
    expect(newState.players['player1'].name).toBe('Alice');
    expect(newState.players['player1'].avatarId).toBe('avatar1');
    expect(newState.order).toContain('player1');
    expect(newState.lastEventId).toBe(1);
  });
  
  it('should reconnect an existing disconnected player', () => {
    const state = createGameStateWithPlayers(['player1']);
    state.players['player1'].isConnected = false;
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_JOINED',
      playerId: 'player1',
      name: 'Alice',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].isConnected).toBe(true);
  });
  
  it('should set host and bot flags correctly', () => {
    const state = createTestGameState();
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_JOINED',
      playerId: 'player1',
      name: 'HostPlayer',
      isHost: true,
      isBot: false,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].isHost).toBe(true);
    expect(newState.players['player1'].isBot).toBe(false);
  });
});

describe('applyEvent - PLAYER_LEFT', () => {
  it('should mark player as disconnected when reason is disconnect', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_LEFT',
      playerId: 'player1',
      reason: 'disconnect',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].isConnected).toBe(false);
    expect(newState.order).toContain('player1');
  });
  
  it('should remove player completely when reason is kick', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_LEFT',
      playerId: 'player1',
      reason: 'kick',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1']).toBeUndefined();
    expect(newState.order).not.toContain('player1');
  });
  
  it('should clear locked answer if leaving player was locked', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.lockedAnswerPlayerId = 'player1';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_LEFT',
      playerId: 'player1',
      reason: 'abandon',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.lockedAnswerPlayerId).toBeUndefined();
  });
});

describe('applyEvent - GAME_STARTED', () => {
  it('should transition from LOBBY to INTRO phase', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'GAME_STARTED',
      config: {
        mode: 'DUO',
        questionsPerRound: 5,
        roundsToWin: 2,
      },
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.phase).toBe('INTRO');
    expect(newState.currentRound).toBe(1);
    expect(newState.questionIndex).toBe(0);
    expect(newState.startedAtMs).toBeDefined();
  });
  
  it('should reset player scores to 0', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    state.players['player1'].score = 10;
    state.players['player2'].score = 5;
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'GAME_STARTED',
      config: {
        mode: 'DUO',
        questionsPerRound: 5,
        roundsToWin: 2,
      },
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].score).toBe(0);
    expect(newState.players['player2'].score).toBe(0);
  });
  
  it('should throw error if game already started', () => {
    const state = createActiveGameState(['player1', 'player2']);
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'GAME_STARTED',
      config: {
        mode: 'DUO',
        questionsPerRound: 5,
        roundsToWin: 2,
      },
    };
    
    expect(() => applyEvent(state, event)).toThrow('Game already started');
  });
});

describe('applyEvent - PHASE_CHANGED', () => {
  it('should transition from INTRO to QUESTION_ACTIVE', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    state.phase = 'INTRO';
    state.questions = [createTestQuestion('q1')];
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PHASE_CHANGED',
      fromPhase: 'INTRO',
      toPhase: 'QUESTION_ACTIVE',
      phaseEndsAtMs: Date.now() + 10000,
      questionIndex: 0,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.phase).toBe('QUESTION_ACTIVE');
    expect(newState.questionIndex).toBe(0);
    expect(newState.buzzQueue).toEqual([]);
    expect(newState.lockedAnswerPlayerId).toBeUndefined();
  });
  
  it('should clear buzz queue and answers when entering QUESTION_ACTIVE', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.buzzQueue = [{ playerId: 'player1', atMs: Date.now(), latencyMs: 50 }];
    state.answeredPlayerIds = ['player1'];
    state.lockedAnswerPlayerId = 'player1';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PHASE_CHANGED',
      fromPhase: 'REVEAL',
      toPhase: 'QUESTION_ACTIVE',
      phaseEndsAtMs: Date.now() + 10000,
      questionIndex: 1,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.buzzQueue).toEqual([]);
    expect(newState.answeredPlayerIds).toEqual([]);
    expect(newState.lockedAnswerPlayerId).toBeUndefined();
  });
  
  it('should update round number when transitioning to ROUND_SCOREBOARD', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'REVEAL';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PHASE_CHANGED',
      fromPhase: 'REVEAL',
      toPhase: 'ROUND_SCOREBOARD',
      roundNumber: 2,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.phase).toBe('ROUND_SCOREBOARD');
    expect(newState.currentRound).toBe(2);
  });
});

describe('applyEvent - BUZZ_RECEIVED', () => {
  it('should add player to buzz queue', () => {
    const state = createActiveGameState(['player1', 'player2']);
    const buzzTimeMs = Date.now();
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'BUZZ_RECEIVED',
      playerId: 'player1',
      buzzTimeMs,
      serverReceivedAtMs: buzzTimeMs + 10,
      latencyMs: 10,
      position: 1,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.buzzQueue).toHaveLength(1);
    expect(newState.buzzQueue[0].playerId).toBe('player1');
    expect(newState.lockedAnswerPlayerId).toBe('player1');
    expect(newState.phase).toBe('ANSWER_SELECTION');
  });
  
  it('should not add duplicate buzz from same player', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.buzzQueue = [{ playerId: 'player1', atMs: Date.now(), latencyMs: 10 }];
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'BUZZ_RECEIVED',
      playerId: 'player1',
      buzzTimeMs: Date.now(),
      serverReceivedAtMs: Date.now(),
      latencyMs: 10,
      position: 1,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.buzzQueue).toHaveLength(1);
  });
  
  it('should not allow buzz from player who already answered', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.answeredPlayerIds = ['player1'];
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'BUZZ_RECEIVED',
      playerId: 'player1',
      buzzTimeMs: Date.now(),
      serverReceivedAtMs: Date.now(),
      latencyMs: 10,
      position: 1,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.buzzQueue).toHaveLength(0);
  });
  
  it('should throw error if buzz received in wrong phase', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    state.phase = 'REVEAL';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'BUZZ_RECEIVED',
      playerId: 'player1',
      buzzTimeMs: Date.now(),
      serverReceivedAtMs: Date.now(),
      latencyMs: 10,
      position: 1,
    };
    
    expect(() => applyEvent(state, event)).toThrow('Buzz not allowed in current phase');
  });
});

describe('applyEvent - ANSWER_SUBMITTED', () => {
  it('should add player to answered list', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    state.lockedAnswerPlayerId = 'player1';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_SUBMITTED',
      playerId: 'player1',
      answer: 0,
      submittedAtMs: Date.now(),
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.answeredPlayerIds).toContain('player1');
  });
  
  it('should throw error if not the locked player', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    state.lockedAnswerPlayerId = 'player1';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_SUBMITTED',
      playerId: 'player2',
      answer: 0,
      submittedAtMs: Date.now(),
    };
    
    expect(() => applyEvent(state, event)).toThrow('Not your turn to answer');
  });
  
  it('should throw error if not in ANSWER_SELECTION phase', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.lockedAnswerPlayerId = 'player1';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_SUBMITTED',
      playerId: 'player1',
      answer: 0,
      submittedAtMs: Date.now(),
    };
    
    expect(() => applyEvent(state, event)).toThrow('Answer not allowed in current phase');
  });
});

describe('applyEvent - ANSWER_REVEALED', () => {
  it('should update lastAnswer with answer details', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_REVEALED',
      playerId: 'player1',
      answer: 0,
      isCorrect: true,
      correctAnswer: 0,
      pointsEarned: 2,
      buzzTimeMs: 3000,
      totalScore: 2,
      roundScore: 2,
      funFact: 'Interesting fact!',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.lastAnswer).toBeDefined();
    expect(newState.lastAnswer?.playerId).toBe('player1');
    expect(newState.lastAnswer?.isCorrect).toBe(true);
    expect(newState.lastAnswer?.pointsEarned).toBe(2);
  });
  
  it('should update player score', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_REVEALED',
      playerId: 'player1',
      answer: 0,
      isCorrect: true,
      correctAnswer: 0,
      pointsEarned: 2,
      buzzTimeMs: 3000,
      totalScore: 10,
      roundScore: 6,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].score).toBe(10);
    expect(newState.players['player1'].roundScore).toBe(6);
  });
  
  it('should add fun fact to current question', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ANSWER_REVEALED',
      playerId: 'player1',
      answer: 0,
      isCorrect: true,
      correctAnswer: 0,
      pointsEarned: 2,
      buzzTimeMs: 3000,
      totalScore: 2,
      roundScore: 2,
      funFact: 'Did you know?',
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.currentQuestion?.funFact).toBe('Did you know?');
  });
});

describe('applyEvent - SCORE_UPDATED', () => {
  it('should update player scores', () => {
    const state = createActiveGameState(['player1', 'player2']);
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'SCORE_UPDATED',
      playerId: 'player1',
      delta: 5,
      reason: 'correct_answer',
      newScore: 15,
      newRoundScore: 10,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.players['player1'].score).toBe(15);
    expect(newState.players['player1'].roundScore).toBe(10);
  });
});

describe('applyEvent - ROUND_ENDED', () => {
  it('should record round results', () => {
    const state = createActiveGameState(['player1', 'player2']);
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'ROUND_ENDED',
      roundNumber: 1,
      playerScores: { player1: 10, player2: 8 },
      winnerId: 'player1',
      isTie: false,
      playerRoundsWon: { player1: 1, player2: 0 },
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.roundResults).toHaveLength(1);
    expect(newState.roundResults[0].winnerId).toBe('player1');
    expect(newState.players['player1'].roundsWon).toBe(1);
    expect(newState.players['player1'].roundScore).toBe(0);
  });
});

describe('applyEvent - MATCH_ENDED', () => {
  it('should set phase to MATCH_END and update final scores', () => {
    const state = createActiveGameState(['player1', 'player2']);
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'MATCH_ENDED',
      winnerId: 'player1',
      isTie: false,
      finalScores: { player1: 25, player2: 18 },
      roundsWon: { player1: 2, player2: 1 },
      duration: 120000,
    };
    
    const newState = applyEvent(state, event);
    
    expect(newState.phase).toBe('MATCH_END');
    expect(newState.players['player1'].score).toBe(25);
    expect(newState.players['player1'].roundsWon).toBe(2);
    expect(newState.endedAtMs).toBeDefined();
  });
});

describe('event ordering', () => {
  it('should throw error if event id is out of order', () => {
    const state = createTestGameState();
    state.lastEventId = 5;
    
    const event: GameEvent = {
      ...createBaseEvent(3),
      type: 'PLAYER_JOINED',
      playerId: 'player1',
      name: 'Alice',
    };
    
    expect(() => applyEvent(state, event)).toThrow('Bad event id order');
  });
  
  it('should increment version on each event', () => {
    const state = createTestGameState();
    expect(state.version).toBe(0);
    
    const event: GameEvent = {
      ...createBaseEvent(1),
      type: 'PLAYER_JOINED',
      playerId: 'player1',
      name: 'Alice',
    };
    
    const newState = applyEvent(state, event);
    expect(newState.version).toBe(1);
  });
});
