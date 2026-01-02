import type { Phase, GameState } from "../../shared/src/types.js";

export type PhaseTransition = {
  from: Phase;
  to: Phase;
  condition?: (state: GameState) => boolean;
  onEnter?: (state: GameState) => Partial<GameState>;
};

const PHASE_TRANSITIONS: PhaseTransition[] = [
  { from: "LOBBY", to: "INTRO" },
  { from: "INTRO", to: "QUESTION_ACTIVE" },
  { from: "QUESTION_ACTIVE", to: "ANSWER_SELECTION" },
  { from: "QUESTION_ACTIVE", to: "REVEAL" },
  { from: "ANSWER_SELECTION", to: "REVEAL" },
  { 
    from: "REVEAL", 
    to: "QUESTION_ACTIVE",
    condition: (state) => state.questionIndex < state.config.questionsPerRound - 1
  },
  { 
    from: "REVEAL", 
    to: "ROUND_SCOREBOARD",
    condition: (state) => state.questionIndex >= state.config.questionsPerRound - 1
  },
  { 
    from: "ROUND_SCOREBOARD", 
    to: "INTRO",
    condition: (state) => {
      const maxRoundsWon = Math.max(...Object.values(state.players).map(p => p.roundsWon));
      return maxRoundsWon < state.config.roundsToWin && state.currentRound < state.config.maxRounds;
    }
  },
  { 
    from: "ROUND_SCOREBOARD", 
    to: "TIEBREAKER_CHOICE",
    condition: (state) => {
      const roundsWonValues = Object.values(state.players).map(p => p.roundsWon);
      const maxRoundsWon = Math.max(...roundsWonValues);
      const playersWithMax = roundsWonValues.filter(r => r === maxRoundsWon).length;
      return playersWithMax > 1 && state.currentRound >= state.config.maxRounds;
    }
  },
  { 
    from: "ROUND_SCOREBOARD", 
    to: "MATCH_END",
    condition: (state) => {
      const maxRoundsWon = Math.max(...Object.values(state.players).map(p => p.roundsWon));
      return maxRoundsWon >= state.config.roundsToWin;
    }
  },
  { from: "TIEBREAKER_CHOICE", to: "TIEBREAKER_QUESTION" },
  { from: "TIEBREAKER_QUESTION", to: "MATCH_END" },
];

export function canTransition(state: GameState, to: Phase): boolean {
  const validTransitions = PHASE_TRANSITIONS.filter(t => t.from === state.phase && t.to === to);
  
  if (validTransitions.length === 0) return false;
  
  return validTransitions.some(t => !t.condition || t.condition(state));
}

export function getNextPhase(state: GameState): Phase | null {
  const possibleTransitions = PHASE_TRANSITIONS.filter(t => t.from === state.phase);
  
  for (const transition of possibleTransitions) {
    if (!transition.condition || transition.condition(state)) {
      return transition.to;
    }
  }
  
  return null;
}

export function getPhaseTimeout(state: GameState): number {
  switch (state.phase) {
    case "LOBBY":
      return 0;
    case "INTRO":
      return state.config.timers.intro;
    case "QUESTION_ACTIVE":
      return state.config.timers.questionActive;
    case "ANSWER_SELECTION":
      return state.config.timers.answerSelection;
    case "REVEAL":
      return state.config.timers.reveal;
    case "ROUND_SCOREBOARD":
      return state.config.timers.roundScoreboard;
    case "TIEBREAKER_CHOICE":
      return state.config.timers.tiebreakerChoice;
    case "TIEBREAKER_QUESTION":
      return state.config.timers.questionActive;
    case "MATCH_END":
      return state.config.timers.matchEnd;
    default:
      return 0;
  }
}

export function isTerminalPhase(phase: Phase): boolean {
  return phase === "MATCH_END";
}

export function isPlayPhase(phase: Phase): boolean {
  return ["QUESTION_ACTIVE", "ANSWER_SELECTION", "TIEBREAKER_QUESTION"].includes(phase);
}
