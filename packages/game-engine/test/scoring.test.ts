import { describe, it, expect } from 'vitest';
import { calculateScore, calculateTimeoutScore, calculateEfficiency, determineRoundWinner, determineMatchWinner } from '../src/scoring.js';
import { DEFAULT_TEST_SCORING } from './fixtures.js';

describe('calculateScore', () => {
  const timeoutMs = 10000;
  
  describe('correct answers', () => {
    it('should return 2 points for correct answer with >3s remaining (fast)', () => {
      const buzzTimeMs = 5000;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(2);
      expect(result.reason).toBe('correct_fast');
    });
    
    it('should return 1 point for correct answer with 1-3s remaining (medium)', () => {
      const buzzTimeMs = 8000;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(1);
      expect(result.reason).toBe('correct_medium');
    });
    
    it('should return 0 points for correct answer with <1s remaining (slow)', () => {
      const buzzTimeMs = 9500;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('correct_slow');
    });
    
    it('should return 0 points at exactly 1s remaining boundary (slow)', () => {
      const buzzTimeMs = 9000;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('correct_slow');
    });
    
    it('should return 1 point at just over 1s remaining (medium)', () => {
      const buzzTimeMs = 8999;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(1);
      expect(result.reason).toBe('correct_medium');
    });
    
    it('should return 2 points at just over 3s remaining (fast)', () => {
      const buzzTimeMs = 6999;
      const result = calculateScore(true, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(2);
      expect(result.reason).toBe('correct_fast');
    });
  });
  
  describe('wrong answers', () => {
    it('should return -2 points penalty for wrong answer in DUO mode', () => {
      const buzzTimeMs = 3000;
      const result = calculateScore(false, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'DUO');
      
      expect(result.points).toBe(-2);
      expect(result.reason).toBe('wrong');
    });
    
    it('should return -2 points penalty for wrong answer in SOLO mode', () => {
      const buzzTimeMs = 3000;
      const result = calculateScore(false, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'SOLO');
      
      expect(result.points).toBe(-2);
      expect(result.reason).toBe('wrong');
    });
    
    it('should return 0 points for wrong answer in MASTER mode', () => {
      const buzzTimeMs = 3000;
      const result = calculateScore(false, buzzTimeMs, timeoutMs, DEFAULT_TEST_SCORING, 'MASTER');
      
      expect(result.points).toBe(0);
      expect(result.reason).toBe('wrong');
    });
  });
});

describe('calculateTimeoutScore', () => {
  it('should return 0 points for timeout', () => {
    const result = calculateTimeoutScore(DEFAULT_TEST_SCORING);
    
    expect(result.points).toBe(0);
    expect(result.reason).toBe('timeout');
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
