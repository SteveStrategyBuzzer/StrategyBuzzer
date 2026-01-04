import type { GameState, GameConfig, Player, Question, ScoringConfig, TimersConfig } from '../../../packages/shared/src/types.js';

export const DEFAULT_TEST_SCORING: ScoringConfig = {
  correctFast: 2,
  correctMedium: 1,
  correctSlow: 0,
  wrongPenalty: -2,
  wrongMaster: 0,
  timeout: 0,
  fastThresholdMs: 3000,
  mediumThresholdMs: 1000,
};

export const DEFAULT_TEST_TIMERS: TimersConfig = {
  intro: 5000,
  questionActive: 10000,
  answerSelection: 10000,
  reveal: 3000,
  waiting: 2000,
  roundScoreboard: 5000,
  tiebreakerChoice: 10000,
  matchEnd: 10000,
};

export function createTestConfig(overrides: Partial<GameConfig> = {}): GameConfig {
  return {
    mode: 'DUO',
    maxPlayers: 2,
    questionsPerRound: 5,
    roundsToWin: 2,
    maxRounds: 3,
    buzzEnabled: true,
    voiceChatEnabled: false,
    scoring: DEFAULT_TEST_SCORING,
    timers: DEFAULT_TEST_TIMERS,
    ...overrides,
  };
}

export function createTestPlayer(id: string, overrides: Partial<Player> = {}): Player {
  return {
    id,
    name: `Player ${id}`,
    score: 0,
    roundScore: 0,
    roundsWon: 0,
    lives: 3,
    isConnected: true,
    lastSeenMs: Date.now(),
    skills: {},
    ...overrides,
  };
}

export function createTestQuestion(id: string, overrides: Partial<Question> = {}): Question {
  return {
    id,
    text: `Test question ${id}?`,
    type: 'MCQ',
    choices: ['Option A', 'Option B', 'Option C', 'Option D'],
    correctIndex: 0,
    difficulty: 2,
    category: 'General',
    timeLimitMs: 10000,
    ...overrides,
  };
}

export function createTestGameState(overrides: Partial<GameState> = {}): GameState {
  const config = overrides.config || createTestConfig();
  return {
    sessionId: 'test-session-1',
    lobbyCode: 'ABCD',
    createdAtMs: Date.now(),
    phase: 'LOBBY',
    config,
    players: {},
    order: [],
    currentRound: 0,
    questionIndex: 0,
    questions: [],
    roundResults: [],
    buzzQueue: [],
    answeredPlayerIds: [],
    lastEventId: 0,
    version: 0,
    ...overrides,
  };
}

export function createGameStateWithPlayers(
  playerIds: string[],
  overrides: Partial<GameState> = {}
): GameState {
  const players: Record<string, Player> = {};
  for (const id of playerIds) {
    players[id] = createTestPlayer(id);
  }
  return createTestGameState({
    players,
    order: playerIds,
    ...overrides,
  });
}

export function createActiveGameState(
  playerIds: string[],
  questionCount: number = 5,
  overrides: Partial<GameState> = {}
): GameState {
  const questions: Question[] = [];
  for (let i = 0; i < questionCount; i++) {
    questions.push(createTestQuestion(`q${i + 1}`));
  }
  
  return createGameStateWithPlayers(playerIds, {
    phase: 'QUESTION_ACTIVE',
    currentRound: 1,
    questionIndex: 0,
    questions,
    currentQuestion: questions[0],
    startedAtMs: Date.now(),
    phaseStartedAtMs: Date.now(),
    phaseEndsAtMs: Date.now() + 10000,
    ...overrides,
  });
}

export function createBaseEvent(id: number, sessionId: string = 'test-session-1') {
  return {
    id,
    atMs: Date.now(),
    sessionId,
  };
}
