import { describe, it, expect } from 'vitest';
import { calculateScore, calculateTimeoutScore, calculateEfficiency, determineRoundWinner, determineMatchWinner } from '../src/scoring.js';
import { DEFAULT_TEST_SCORING } from './fixtures.js';

describe('calculateScore', () => {
  describe('first buzzer scenarios', () => {
    it('should return +2 points for 1st to buzz + correct answer', () => {
      const result = calculateScore(true, true, 1, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(2);
      expect(result.reason).toBe('first_buzzer_correct');
    });
    
    it('should return -2 points for 1st to buzz + wrong answer', () => {
      const result = calculateScore(false, true, 1, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(-2);
      expect(result.reason).toBe('buzz_wrong');
    });
  });
  
  describe('other buzzer scenarios (2nd+)', () => {
    it('should return +1 point for 2nd to buzz + correct answer', () => {
      const result = calculateScore(true, true, 2, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(1);
      expect(result.reason).toBe('other_buzzer_correct');
    });
    
    it('should return +1 point for 3rd to buzz + correct answer', () => {
      const result = calculateScore(true, true, 3, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(1);
      expect(result.reason).toBe('other_buzzer_correct');
    });
    
    it('should return -2 points for 2nd to buzz + wrong answer', () => {
      const result = calculateScore(false, true, 2, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(-2);
      expect(result.reason).toBe('buzz_wrong');
    });
  });
  
  describe('no buzz scenarios', () => {
    it('should return 0 points for no buzz + correct answer', () => {
      const result = calculateScore(true, false, 0, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('no_buzz_correct');
    });
    
    it('should return 0 points for no buzz + wrong answer (no penalty)', () => {
      const result = calculateScore(false, false, 0, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('no_buzz_wrong');
    });
    
    it('should treat buzzOrder=0 as no buzz even if didBuzz=true', () => {
      const result = calculateScore(true, true, 0, DEFAULT_TEST_SCORING);
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('no_buzz_correct');
    });
  });
});

describe('calculateTimeoutScore', () => {
  it('should return -2 points for timeout after buzz', () => {
    const result = calculateTimeoutScore(true, DEFAULT_TEST_SCORING);
    
    expect(result.points).toBe(-2);
    expect(result.reason).toBe('buzz_timeout');
  });
  
  it('should return 0 points for timeout without buzz (no penalty)', () => {
    const result = calculateTimeoutScore(false, DEFAULT_TEST_SCORING);
    
    expect(result.points).toBe(0);
    expect(result.reason).toBe('no_buzz_timeout');
  });
});

describe('calculateEfficiency', () => {
  it('should return 0 for no answers', () => {
    const result = calculateEfficiency(0, 0, 0);
    expect(result).toBe(0);
  });
  
  it('should calculate efficiency based on accuracy and buzz rate', () => {
    const result = calculateEfficiency(8, 10, 12);
    expect(result).toBeGreaterThan(0);
    expect(result).toBeLessThanOrEqual(100);
  });
  
  it('should return higher efficiency for perfect accuracy', () => {
    const perfectResult = calculateEfficiency(10, 10, 10);
    const imperfectResult = calculateEfficiency(5, 10, 10);
    
    expect(perfectResult).toBeGreaterThan(imperfectResult);
  });
});

describe('determineRoundWinner', () => {
  it('should return undefined for empty scores', () => {
    const result = determineRoundWinner({});
    
    expect(result.winnerId).toBeUndefined();
    expect(result.isTie).toBe(false);
  });
  
  it('should return the only player as winner when single player', () => {
    const result = determineRoundWinner({ player1: 10 });
    
    expect(result.winnerId).toBe('player1');
    expect(result.isTie).toBe(false);
  });
  
  it('should return the player with highest score as winner', () => {
    const result = determineRoundWinner({
      player1: 10,
      player2: 15,
      player3: 8,
    });
    
    expect(result.winnerId).toBe('player2');
    expect(result.isTie).toBe(false);
  });
  
  it('should return tie when multiple players have same highest score', () => {
    const result = determineRoundWinner({
      player1: 15,
      player2: 15,
      player3: 8,
    });
    
    expect(result.winnerId).toBeUndefined();
    expect(result.isTie).toBe(true);
  });
});

describe('determineMatchWinner', () => {
  it('should return winner when a player reaches rounds to win', () => {
    const result = determineMatchWinner({ player1: 2, player2: 1 }, 2);
    
    expect(result.winnerId).toBe('player1');
    expect(result.isTie).toBe(false);
  });
  
  it('should return tie when no player has reached rounds to win and scores are tied', () => {
    const result = determineMatchWinner({ player1: 1, player2: 1 }, 2);
    
    expect(result.winnerId).toBeUndefined();
    expect(result.isTie).toBe(true);
  });
  
  it('should return the leader when no player has reached rounds to win', () => {
    const result = determineMatchWinner({ player1: 1, player2: 0 }, 3);
    
    expect(result.winnerId).toBe('player1');
    expect(result.isTie).toBe(false);
  });
});
