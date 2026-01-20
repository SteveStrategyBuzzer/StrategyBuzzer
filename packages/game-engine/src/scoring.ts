import type { ScoringConfig } from "../../shared/src/types.js";

export type ScoreResult = {
  points: number;
  reason: "first_buzzer_correct" | "other_buzzer_correct" | "no_buzz_correct" | "buzz_wrong" | "buzz_timeout" | "no_buzz_wrong" | "no_buzz_timeout";
};

export function calculateScore(
  isCorrect: boolean,
  didBuzz: boolean,
  buzzOrder: number, // 1 = first to buzz, 2+ = other buzzers, 0 = didn't buzz
  config: ScoringConfig
): ScoreResult {
  // Case 1: Player didn't buzz
  if (!didBuzz || buzzOrder === 0) {
    if (isCorrect) {
      return { points: config.noBuzzCorrect, reason: "no_buzz_correct" };
    }
    return { points: config.noBuzzWrong, reason: "no_buzz_wrong" };
  }

  // Case 2: Player buzzed
  if (isCorrect) {
    if (buzzOrder === 1) {
      return { points: config.firstBuzzerCorrect, reason: "first_buzzer_correct" };
    }
    return { points: config.otherBuzzersCorrect, reason: "other_buzzer_correct" };
  }

  // Case 3: Player buzzed but wrong answer
  return { points: config.buzzWrong, reason: "buzz_wrong" };
}

export function calculateTimeoutScore(didBuzz: boolean, config: ScoringConfig): ScoreResult {
  if (didBuzz) {
    return { points: config.buzzWrong, reason: "buzz_timeout" };
  }
  return { points: config.noBuzzWrong, reason: "no_buzz_timeout" };
}

export function calculateEfficiency(
  correctAnswers: number,
  totalAnswers: number,
  totalBuzzes: number
): number {
  if (totalAnswers === 0) return 0;
  
  const accuracy = correctAnswers / totalAnswers;
  const buzzRate = totalBuzzes > 0 ? totalAnswers / totalBuzzes : 0;
  
  return Math.round((accuracy * 0.7 + buzzRate * 0.3) * 100);
}

export function determineRoundWinner(
  playerScores: Record<string, number>
): { winnerId: string | undefined; isTie: boolean } {
  const entries = Object.entries(playerScores);
  
  if (entries.length === 0) {
    return { winnerId: undefined, isTie: false };
  }
  
  if (entries.length === 1) {
    return { winnerId: entries[0][0], isTie: false };
  }
  
  const maxScore = Math.max(...entries.map(([, score]) => score));
  const winners = entries.filter(([, score]) => score === maxScore);
  
  if (winners.length > 1) {
    return { winnerId: undefined, isTie: true };
  }
  
  return { winnerId: winners[0][0], isTie: false };
}

export function determineMatchWinner(
  playerRoundsWon: Record<string, number>,
  roundsToWin: number
): { winnerId: string | undefined; isTie: boolean } {
  const entries = Object.entries(playerRoundsWon);
  
  const winner = entries.find(([, roundsWon]) => roundsWon >= roundsToWin);
  if (winner) {
    return { winnerId: winner[0], isTie: false };
  }
  
  const maxRoundsWon = Math.max(...entries.map(([, roundsWon]) => roundsWon));
  const topPlayers = entries.filter(([, roundsWon]) => roundsWon === maxRoundsWon);
  
  if (topPlayers.length > 1) {
    return { winnerId: undefined, isTie: true };
  }
  
  return { winnerId: topPlayers[0]?.[0], isTie: false };
}
