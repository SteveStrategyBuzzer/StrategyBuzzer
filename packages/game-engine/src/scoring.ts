import type { ScoringConfig, Mode } from "../../shared/src/types.js";

export type ScoreResult = {
  points: number;
  reason: "correct_fast" | "correct_medium" | "correct_slow" | "wrong" | "timeout";
};

export function calculateScore(
  isCorrect: boolean,
  buzzTimeMs: number,
  timeoutMs: number,
  config: ScoringConfig,
  mode: Mode
): ScoreResult {
  if (!isCorrect) {
    const penalty = mode === "MASTER" ? config.wrongMaster : config.wrongPenalty;
    return { points: penalty, reason: "wrong" };
  }

  const timeRemaining = timeoutMs - buzzTimeMs;

  if (timeRemaining > config.fastThresholdMs) {
    return { points: config.correctFast, reason: "correct_fast" };
  }

  if (timeRemaining > config.mediumThresholdMs) {
    return { points: config.correctMedium, reason: "correct_medium" };
  }

  return { points: config.correctSlow, reason: "correct_slow" };
}

export function calculateTimeoutScore(config: ScoringConfig): ScoreResult {
  return { points: config.timeout, reason: "timeout" };
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
