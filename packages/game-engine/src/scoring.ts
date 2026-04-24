import type { ScoringConfig, PlayerLiveStats } from "@strategybuzzer/shared";

export type ScoreResult = {
  points: number;
  reason: "first_buzzer_correct" | "other_buzzer_correct" | "no_buzz_correct" | "buzz_wrong" | "buzz_timeout" | "no_buzz_wrong" | "no_buzz_timeout";
};

export function calculateScore(
  isCorrect: boolean,
  didBuzz: boolean,
  buzzOrder: number,
  config: ScoringConfig
): ScoreResult {
  if (!didBuzz || buzzOrder === 0) {
    if (isCorrect) {
      return { points: config.noBuzzCorrect, reason: "no_buzz_correct" };
    }
    return { points: config.noBuzzWrong, reason: "no_buzz_wrong" };
  }

  if (isCorrect) {
    if (buzzOrder === 1) {
      return { points: config.firstBuzzerCorrect, reason: "first_buzzer_correct" };
    }
    return { points: config.otherBuzzersCorrect, reason: "other_buzzer_correct" };
  }

  return { points: config.buzzWrong, reason: "buzz_wrong" };
}

export function calculateTimeoutScore(didBuzz: boolean, config: ScoringConfig): ScoreResult {
  if (didBuzz) {
    return { points: config.buzzWrong, reason: "buzz_timeout" };
  }
  return { points: config.noBuzzWrong, reason: "no_buzz_timeout" };
}

/**
 * Calculate efficiency = points-based precision rate (taux_precision aligned
 * with task #37 persistence formula).
 *
 * Formula: correctAnswers / totalBuzzes * 100  (rounded, clamped 0..100)
 *
 * This is the SINGLE source of truth for "efficacité" displayed live during
 * gameplay AND persisted at match end. Replaces the legacy 0.7*accuracy +
 * 0.3*buzzRate hybrid (which was not consistent with the post-match formula).
 *
 * - 0 buzzes ⇒ 0% (you can't be efficient if you never buzzed)
 * - all correct ⇒ 100%
 * - never correct ⇒ 0%
 *
 * `totalAnswers` is kept as a parameter for backward signature compatibility
 * but is no longer used by the formula itself.
 */
export function calculateEfficiency(
  correctAnswers: number,
  totalAnswers: number,
  totalBuzzes: number
): number {
  if (totalBuzzes <= 0) return 0;
  const raw = (correctAnswers / totalBuzzes) * 100;
  return Math.max(0, Math.min(100, Math.round(raw)));
}

/**
 * Make an empty PlayerLiveStats record. Used when a player joins to seed
 * the live-stats slot in GameOrchestrator memory.
 */
export function emptyPlayerLiveStats(playerId: string): PlayerLiveStats {
  return {
    playerId,
    score: 0,
    roundScore: 0,
    roundsWon: 0,
    lives: 0,
    correctAnswers: 0,
    wrongAnswers: 0,
    totalAnswers: 0,
    accuracyPercent: 0,
    efficiencyPercent: 0,
    averageResponseMs: 0,
    buzzCount: 0,
    buzzWon: 0,
    buzzLost: 0,
    currentStreak: 0,
    bestStreak: 0,
  };
}

export type LiveStatsUpdate = {
  didBuzz: boolean;
  buzzOrder: number; // 1 = first to buzz, 0 = no buzz
  isCorrect: boolean;
  buzzTimeMs: number;
  newScore: number;
  newRoundScore: number;
};

/**
 * Pure function: returns an updated PlayerLiveStats given a previous snapshot
 * and the outcome of one question for this player.
 *
 * - correctAnswers / wrongAnswers / totalAnswers track what the player actually
 *   answered (a non-buzzer who didn't answer doesn't increment totalAnswers).
 * - buzzCount tracks attempts to buzz (regardless of order).
 * - buzzWon = first to buzz this question; buzzLost = buzzed but not first.
 * - currentStreak resets on a wrong answer; bestStreak retains the maximum.
 * - averageResponseMs is the rolling average over all buzzes.
 * - efficiencyPercent uses calculateEfficiency above (correct / totalBuzzes).
 * - accuracyPercent = correct / totalAnswers, for display only.
 *
 * No I/O. Safe to call inside reducers.
 */
export function updatePlayerLiveStats(
  prev: PlayerLiveStats,
  update: LiveStatsUpdate
): PlayerLiveStats {
  const next: PlayerLiveStats = { ...prev };

  next.score = update.newScore;
  next.roundScore = update.newRoundScore;

  if (update.didBuzz) {
    next.buzzCount = (prev.buzzCount || 0) + 1;
    if (update.buzzOrder === 1) {
      next.buzzWon = (prev.buzzWon || 0) + 1;
    } else if (update.buzzOrder > 1) {
      next.buzzLost = (prev.buzzLost || 0) + 1;
    }
    // averageResponseMs rolling avg over all buzzes
    if (update.buzzTimeMs > 0) {
      const total = (prev.averageResponseMs || 0) * (prev.buzzCount || 0) + update.buzzTimeMs;
      next.averageResponseMs = Math.round(total / next.buzzCount);
    }
  }

  // Only count "answered" if the player actually contributed an answer outcome.
  // We treat "didBuzz && answered" as the only way to register a correct/wrong;
  // a no-buzz player contributes nothing to totalAnswers.
  if (update.didBuzz) {
    next.totalAnswers = (prev.totalAnswers || 0) + 1;
    if (update.isCorrect) {
      next.correctAnswers = (prev.correctAnswers || 0) + 1;
      next.currentStreak = (prev.currentStreak || 0) + 1;
      next.bestStreak = Math.max(prev.bestStreak || 0, next.currentStreak);
    } else {
      next.wrongAnswers = (prev.wrongAnswers || 0) + 1;
      next.currentStreak = 0;
    }
  }

  next.accuracyPercent = next.totalAnswers > 0
    ? Math.round((next.correctAnswers / next.totalAnswers) * 100)
    : 0;

  next.efficiencyPercent = calculateEfficiency(
    next.correctAnswers,
    next.totalAnswers,
    next.buzzCount,
  );

  return next;
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
