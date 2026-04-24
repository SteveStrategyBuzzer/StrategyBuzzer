import { describe, it, expect } from 'vitest';
import { calculateScore, calculateTimeoutScore, calculateEfficiency, determineRoundWinner, determineMatchWinner, updatePlayerLiveStats, emptyPlayerLiveStats } from '../src/scoring.js';
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

describe('calculateEfficiency (taux_precision aligned with #37)', () => {
  it('should return 0 when no buzzes', () => {
    expect(calculateEfficiency(0, 0, 0)).toBe(0);
    expect(calculateEfficiency(0, 5, 0)).toBe(0);
  });

  it('should be 100% when every buzz is correct', () => {
    expect(calculateEfficiency(8, 8, 8)).toBe(100);
    expect(calculateEfficiency(1, 1, 1)).toBe(100);
  });

  it('should be 0% when every buzz is wrong', () => {
    expect(calculateEfficiency(0, 5, 5)).toBe(0);
  });

  it('should compute correctAnswers / totalBuzzes ratio (rounded)', () => {
    expect(calculateEfficiency(3, 5, 5)).toBe(60);
    expect(calculateEfficiency(8, 10, 10)).toBe(80);
    expect(calculateEfficiency(1, 3, 3)).toBe(33);
  });

  it('should clamp to 0..100', () => {
    expect(calculateEfficiency(10, 5, 5)).toBe(100);
  });
});

describe('updatePlayerLiveStats', () => {
  it('should track first-buzz correct: +score, +correct, +buzzWon, streak=1', () => {
    const initial = emptyPlayerLiveStats('p1');
    const next = updatePlayerLiveStats(initial, {
      didBuzz: true,
      buzzOrder: 1,
      isCorrect: true,
      buzzTimeMs: 1500,
      newScore: 2,
      newRoundScore: 2,
    });
    expect(next.score).toBe(2);
    expect(next.correctAnswers).toBe(1);
    expect(next.wrongAnswers).toBe(0);
    expect(next.totalAnswers).toBe(1);
    expect(next.buzzCount).toBe(1);
    expect(next.buzzWon).toBe(1);
    expect(next.buzzLost).toBe(0);
    expect(next.currentStreak).toBe(1);
    expect(next.bestStreak).toBe(1);
    expect(next.efficiencyPercent).toBe(100);
    expect(next.accuracyPercent).toBe(100);
    expect(next.averageResponseMs).toBe(1500);
  });

  it('should reset currentStreak on wrong answer but preserve bestStreak', () => {
    let s = emptyPlayerLiveStats('p1');
    s = updatePlayerLiveStats(s, { didBuzz: true, buzzOrder: 1, isCorrect: true, buzzTimeMs: 1000, newScore: 2, newRoundScore: 2 });
    s = updatePlayerLiveStats(s, { didBuzz: true, buzzOrder: 1, isCorrect: true, buzzTimeMs: 1000, newScore: 4, newRoundScore: 4 });
    s = updatePlayerLiveStats(s, { didBuzz: true, buzzOrder: 1, isCorrect: false, buzzTimeMs: 2000, newScore: 2, newRoundScore: 2 });
    expect(s.currentStreak).toBe(0);
    expect(s.bestStreak).toBe(2);
    expect(s.correctAnswers).toBe(2);
    expect(s.wrongAnswers).toBe(1);
    expect(s.efficiencyPercent).toBe(67); // 2/3 ≈ 66.67 → 67
  });

  it('should compute rolling averageResponseMs over buzzes', () => {
    let s = emptyPlayerLiveStats('p1');
    s = updatePlayerLiveStats(s, { didBuzz: true, buzzOrder: 1, isCorrect: true, buzzTimeMs: 1000, newScore: 2, newRoundScore: 2 });
    s = updatePlayerLiveStats(s, { didBuzz: true, buzzOrder: 1, isCorrect: true, buzzTimeMs: 3000, newScore: 4, newRoundScore: 4 });
    expect(s.averageResponseMs).toBe(2000);
  });

  it('should not increment counters for no-buzz players', () => {
    const initial = emptyPlayerLiveStats('p1');
    const next = updatePlayerLiveStats(initial, {
      didBuzz: false,
      buzzOrder: 0,
      isCorrect: false,
      buzzTimeMs: 0,
      newScore: 0,
      newRoundScore: 0,
    });
    expect(next.buzzCount).toBe(0);
    expect(next.totalAnswers).toBe(0);
    expect(next.correctAnswers).toBe(0);
    expect(next.wrongAnswers).toBe(0);
    expect(next.efficiencyPercent).toBe(0);
  });

  it('should track buzzLost for non-first-buzz players', () => {
    const initial = emptyPlayerLiveStats('p1');
    const next = updatePlayerLiveStats(initial, {
      didBuzz: true,
      buzzOrder: 2,
      isCorrect: true,
      buzzTimeMs: 2000,
      newScore: 1,
      newRoundScore: 1,
    });
    expect(next.buzzWon).toBe(0);
    expect(next.buzzLost).toBe(1);
    expect(next.buzzCount).toBe(1);
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
