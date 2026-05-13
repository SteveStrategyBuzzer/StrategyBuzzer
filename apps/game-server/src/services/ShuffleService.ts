/**
 * ShuffleService — Node-authoritative answer shuffle for multi-mode gameplay.
 *
 * Architecture:
 *   - shuffleOnce()         : Fisher-Yates on choices[], returns new array + new correctIndex
 *   - resolveCorrectIndex() : reconciles client shuffleRevision with server history (race tolerance)
 *   - archiveRevision()     : pushes current state to history FIFO (max 3 entries)
 *
 * ShuffleState is room-level (room.shuffleState) — not player-level.
 * targetPlayerIds?: string[] — undefined = broadcast to whole room (Duo now).
 *                              Set = per-player/team targeting (League Team 5v5 later).
 *
 * No mode-specific logic. Works for Duo now, League Individual + Team 5v5 later.
 */

export interface ShuffleHistoryEntry {
  revision: number;
  correctIndex: number;
  choices: string[];
}

export interface ShuffleState {
  /** questionIndex this state belongs to — detects stale state across questions */
  questionIndex: number;
  /** Monotone-increasing revision counter (0 = initial broadcast shuffle) */
  revision: number;
  /** Current authoritative choices order */
  choices: string[];
  /** Index of the correct answer in choices[] — post-shuffle */
  correctIndex: number;
  /** FIFO history of past revisions for race-condition tolerance (max 3) */
  history: ShuffleHistoryEntry[];
  /** Node.js interval handle — undefined when no interval is running */
  intervalId?: NodeJS.Timeout;
  /**
   * Optional per-player targeting for answer_order_changed.
   * undefined   → io.to(roomId).emit() — broadcast to whole room (Duo).
   * string[]    → io.to(`player:<id>`).emit() per entry (League Team future).
   */
  targetPlayerIds?: string[];
}

export interface ResolvedShuffle {
  correctIndex: number;
  resolvedRevision: number;
}

const MAX_HISTORY = 3;

/**
 * Fisher-Yates shuffle of choices[].
 * Returns a new array (original is never mutated) and the updated correctIndex.
 * Safe to call on arrays of any length ≥ 1.
 */
export function shuffleOnce(
  choices: string[],
  currentCorrectIndex: number,
): { choices: string[]; correctIndex: number } {
  if (choices.length <= 1) {
    return { choices: [...choices], correctIndex: currentCorrectIndex };
  }

  const correctText = choices[currentCorrectIndex];
  const shuffled = [...choices];

  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }

  const newCorrectIndex = shuffled.indexOf(correctText);
  if (newCorrectIndex < 0) {
    // Should never happen: correctText came from choices[] so indexOf must find it.
    // If it does, log loudly so a bad DB entry (empty/duplicate answer) is visible.
    console.error(
      `[ShuffleService] indexOf returned -1 for correctText="${correctText}" ` +
      `choices=${JSON.stringify(shuffled)} — falling back to index 0. ` +
      `Check DB for empty or duplicate answer options.`,
    );
    return { choices: shuffled, correctIndex: 0 };
  }
  return {
    choices: shuffled,
    correctIndex: newCorrectIndex,
  };
}

/**
 * Resolve which correctIndex to use for scoring given the client's shuffleRevision.
 *
 * Rules (Guard 2):
 *   1. clientRevision === current revision → use current correctIndex.
 *   2. clientRevision found in history     → use that entry's correctIndex (race tolerance).
 *   3. clientRevision undefined / unknown  → fallback to current + console.warn telemetry.
 */
export function resolveCorrectIndex(
  state: ShuffleState,
  clientRevision?: number,
): ResolvedShuffle {
  if (clientRevision === undefined) {
    return { correctIndex: state.correctIndex, resolvedRevision: state.revision };
  }

  if (clientRevision === state.revision) {
    return { correctIndex: state.correctIndex, resolvedRevision: state.revision };
  }

  for (let i = state.history.length - 1; i >= 0; i--) {
    const entry = state.history[i];
    if (entry.revision === clientRevision) {
      return { correctIndex: entry.correctIndex, resolvedRevision: entry.revision };
    }
  }

  console.warn(
    `[ShuffleService] Guard 2: client revision ${clientRevision} not found ` +
      `(current=${state.revision}, ` +
      `history=[${state.history.map((e) => e.revision).join(",")}]). ` +
      `Falling back to current correctIndex=${state.correctIndex}.`,
  );
  return { correctIndex: state.correctIndex, resolvedRevision: state.revision };
}

/**
 * Archive the current revision to history FIFO before applying a new shuffle.
 * Trims to MAX_HISTORY. Must be called BEFORE updating state to the new revision.
 */
export function archiveRevision(state: ShuffleState): void {
  state.history.push({
    revision: state.revision,
    correctIndex: state.correctIndex,
    choices: [...state.choices],
  });
  if (state.history.length > MAX_HISTORY) {
    state.history.shift();
  }
}
