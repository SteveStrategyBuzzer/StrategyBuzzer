import { describe, it, expect } from 'vitest';
import { canTransition, getNextPhase, getPhaseTimeout, isTerminalPhase, isPlayPhase } from '../src/state-machine.js';
import { createTestConfig, createActiveGameState, createGameStateWithPlayers } from './fixtures.js';

describe('canTransition', () => {
  describe('LOBBY phase', () => {
    it('should allow transition from LOBBY to INTRO', () => {
      const state = createGameStateWithPlayers(['player1', 'player2']);
      state.phase = 'LOBBY';
      
      expect(canTransition(state, 'INTRO')).toBe(true);
    });
    
    it('should not allow transition from LOBBY to QUESTION_ACTIVE', () => {
      const state = createGameStateWithPlayers(['player1', 'player2']);
      state.phase = 'LOBBY';
      
      expect(canTransition(state, 'QUESTION_ACTIVE')).toBe(false);
    });
  });
  
  describe('INTRO phase', () => {
    it('should allow transition from INTRO to QUESTION_ACTIVE', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'INTRO';
      
      expect(canTransition(state, 'QUESTION_ACTIVE')).toBe(true);
    });
  });
  
  describe('QUESTION_ACTIVE phase', () => {
    it('should allow transition to ANSWER_SELECTION', () => {
      const state = createActiveGameState(['player1', 'player2']);
      
      expect(canTransition(state, 'ANSWER_SELECTION')).toBe(true);
    });
    
    it('should allow transition to REVEAL', () => {
      const state = createActiveGameState(['player1', 'player2']);
      
      expect(canTransition(state, 'REVEAL')).toBe(true);
    });
  });
  
  describe('ANSWER_SELECTION phase', () => {
    it('should allow transition to REVEAL', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'ANSWER_SELECTION';
      
      expect(canTransition(state, 'REVEAL')).toBe(true);
    });
  });
  
  describe('REVEAL phase', () => {
    it('should allow transition to QUESTION_ACTIVE when more questions remain', () => {
      const state = createActiveGameState(['player1', 'player2'], 5);
      state.phase = 'REVEAL';
      state.questionIndex = 0;
      state.config.questionsPerRound = 5;
      
      expect(canTransition(state, 'QUESTION_ACTIVE')).toBe(true);
    });
    
    it('should allow transition to ROUND_SCOREBOARD when round is complete', () => {
      const state = createActiveGameState(['player1', 'player2'], 5);
      state.phase = 'REVEAL';
      state.questionIndex = 4;
      state.config.questionsPerRound = 5;
      
      expect(canTransition(state, 'ROUND_SCOREBOARD')).toBe(true);
    });
    
    it('should allow transition to WAITING', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'REVEAL';
      
      expect(canTransition(state, 'WAITING')).toBe(true);
    });
  });
  
  describe('ROUND_SCOREBOARD phase', () => {
    it('should allow transition to INTRO when match not over', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'ROUND_SCOREBOARD';
      state.currentRound = 1;
      state.config.maxRounds = 3;
      state.config.roundsToWin = 2;
      state.players['player1'].roundsWon = 1;
      state.players['player2'].roundsWon = 0;
      
      expect(canTransition(state, 'INTRO')).toBe(true);
    });
    
    it('should allow transition to MATCH_END when player wins', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'ROUND_SCOREBOARD';
      state.config.roundsToWin = 2;
      state.players['player1'].roundsWon = 2;
      state.players['player2'].roundsWon = 1;
      
      expect(canTransition(state, 'MATCH_END')).toBe(true);
    });
    
    it('should allow transition to TIEBREAKER_CHOICE when tied at max rounds', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'ROUND_SCOREBOARD';
      state.currentRound = 3;
      state.config.maxRounds = 3;
      state.config.roundsToWin = 3;
      state.players['player1'].roundsWon = 1;
      state.players['player2'].roundsWon = 1;
      
      expect(canTransition(state, 'TIEBREAKER_CHOICE')).toBe(true);
    });
  });
  
  describe('TIEBREAKER phases', () => {
    it('should allow transition from TIEBREAKER_CHOICE to TIEBREAKER_QUESTION', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'TIEBREAKER_CHOICE';
      
      expect(canTransition(state, 'TIEBREAKER_QUESTION')).toBe(true);
    });
    
    it('should allow transition from TIEBREAKER_QUESTION to MATCH_END', () => {
      const state = createActiveGameState(['player1', 'player2']);
      state.phase = 'TIEBREAKER_QUESTION';
      
      expect(canTransition(state, 'MATCH_END')).toBe(true);
    });
  });
});

describe('getNextPhase', () => {
  it('should return INTRO from LOBBY', () => {
    const state = createGameStateWithPlayers(['player1', 'player2']);
    state.phase = 'LOBBY';
    
    expect(getNextPhase(state)).toBe('INTRO');
  });
  
  it('should return QUESTION_ACTIVE from INTRO', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'INTRO';
    
    expect(getNextPhase(state)).toBe('QUESTION_ACTIVE');
  });
  
  it('should return null from MATCH_END', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'MATCH_END';
    
    expect(getNextPhase(state)).toBeNull();
  });
});

describe('getPhaseTimeout', () => {
  it('should return correct timeout for LOBBY', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'LOBBY';
    
    expect(getPhaseTimeout(state)).toBe(0);
  });
  
  it('should return configured intro timeout', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'INTRO';
    
    expect(getPhaseTimeout(state)).toBe(state.config.timers.intro);
  });
  
  it('should return configured questionActive timeout', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'QUESTION_ACTIVE';
    
    expect(getPhaseTimeout(state)).toBe(state.config.timers.questionActive);
  });
  
  it('should return configured answerSelection timeout', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'ANSWER_SELECTION';
    
    expect(getPhaseTimeout(state)).toBe(state.config.timers.answerSelection);
  });
  
  it('should return configured reveal timeout', () => {
    const state = createActiveGameState(['player1', 'player2']);
    state.phase = 'REVEAL';
    
    expect(getPhaseTimeout(state)).toBe(state.config.timers.reveal);
  });
});

describe('isTerminalPhase', () => {
  it('should return true for MATCH_END', () => {
    expect(isTerminalPhase('MATCH_END')).toBe(true);
  });
  
  it('should return false for LOBBY', () => {
    expect(isTerminalPhase('LOBBY')).toBe(false);
  });
  
  it('should return false for QUESTION_ACTIVE', () => {
    expect(isTerminalPhase('QUESTION_ACTIVE')).toBe(false);
  });
  
  it('should return false for ROUND_SCOREBOARD', () => {
    expect(isTerminalPhase('ROUND_SCOREBOARD')).toBe(false);
  });
});

describe('isPlayPhase', () => {
  it('should return true for QUESTION_ACTIVE', () => {
    expect(isPlayPhase('QUESTION_ACTIVE')).toBe(true);
  });
  
  it('should return true for ANSWER_SELECTION', () => {
    expect(isPlayPhase('ANSWER_SELECTION')).toBe(true);
  });
  
  it('should return true for TIEBREAKER_QUESTION', () => {
    expect(isPlayPhase('TIEBREAKER_QUESTION')).toBe(true);
  });
  
  it('should return false for LOBBY', () => {
    expect(isPlayPhase('LOBBY')).toBe(false);
  });
  
  it('should return false for REVEAL', () => {
    expect(isPlayPhase('REVEAL')).toBe(false);
  });
  
  it('should return false for ROUND_SCOREBOARD', () => {
    expect(isPlayPhase('ROUND_SCOREBOARD')).toBe(false);
  });
});
