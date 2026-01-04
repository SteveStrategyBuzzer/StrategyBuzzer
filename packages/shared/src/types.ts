export type UUID = string;

export type Mode = 
  | "SOLO" 
  | "DUO" 
  | "LEAGUE_INDIVIDUAL" 
  | "LEAGUE_TEAM" 
  | "MASTER";

export type Phase =
  | "LOBBY"               
  | "INTRO"               
  | "QUESTION_ACTIVE"     
  | "ANSWER_SELECTION"    
  | "REVEAL"              
  | "WAITING"             
  | "ROUND_SCOREBOARD"    
  | "TIEBREAKER_CHOICE"   
  | "TIEBREAKER_QUESTION" 
  | "MATCH_END";          

export type SkillType = "PASSIVE" | "VISUAL" | "ACTIVE_PRE" | "ACTIVE_POST";
export type SkillTrigger = "auto" | "question" | "answer" | "reveal";

export type SkillId =
  | "double_points"
  | "time_freeze"
  | "answer_shield"
  | "second_chance"
  | "fifty_fifty"
  | "quick_peek"
  | "point_steal"
  | "buzz_block"
  | "score_boost"
  | "life_steal"
  | "mirror_answer"
  | "double_or_nothing"
  | "slow_opponent"
  | "bonus_question"
  | "immunity"
  | "answer_copy"
  | "time_bonus"
  | "score_drain"
  | "reveal_answer"
  | "buzz_priority"
  | "combo_master"
  | "critical_hit"
  | "defense_aura"
  | "attack_boost"
  | "final_stand";

export type SkillState = {
  cooldownUntilMs: number;
  usesLeft: number;
  maxUses: number;
};

export type Player = {
  id: UUID;
  odabaseId?: number;
  name: string;
  avatarId?: string;
  avatarUrl?: string;
  strategicAvatarId?: string;
  isBot?: boolean;
  isHost?: boolean;
  teamId?: UUID;
  division?: string;
  score: number;
  roundScore: number;
  roundsWon: number;
  lives: number;
  pingMs?: number;
  isConnected: boolean;
  lastSeenMs: number;
  skills: Partial<Record<SkillId, SkillState>>;
};

export type QuestionType = "MCQ" | "TRUE_FALSE" | "TEXT";

export type Question = {
  id: UUID;
  text: string;
  type: QuestionType;
  choices?: string[];
  correctIndex?: number;
  correctBool?: boolean;
  correctText?: string;
  difficulty: 1 | 2 | 3 | 4 | 5;
  category: string;
  subCategory?: string;
  funFact?: string;
  timeLimitMs: number;
};

export type RedactedQuestion = Omit<Question, 'correctIndex' | 'correctBool' | 'correctText'>;

export type ScoringConfig = {
  correctFast: number;
  correctMedium: number;
  correctSlow: number;
  wrongPenalty: number;
  wrongMaster: number;
  timeout: number;
  fastThresholdMs: number;
  mediumThresholdMs: number;
};

export type TimersConfig = {
  intro: number;
  questionActive: number;
  answerSelection: number;
  reveal: number;
  waiting: number;
  roundScoreboard: number;
  tiebreakerChoice: number;
  matchEnd: number;
};

export type GameConfig = {
  mode: Mode;
  maxPlayers: number;
  questionsPerRound: number;
  roundsToWin: number;
  maxRounds: number;
  buzzEnabled: boolean;
  voiceChatEnabled: boolean;
  scoring: ScoringConfig;
  timers: TimersConfig;
  entryFee?: number;
  prizePool?: number;
};

export type BuzzEntry = {
  playerId: UUID;
  atMs: number;
  latencyMs?: number;
};

export type RoundResult = {
  roundNumber: number;
  playerScores: Record<UUID, number>;
  winnerId?: UUID;
  isTie: boolean;
};

export type GameState = {
  sessionId: UUID;
  lobbyCode: string;
  createdAtMs: number;
  startedAtMs?: number;
  endedAtMs?: number;

  phase: Phase;
  config: GameConfig;

  players: Record<UUID, Player>;
  order: UUID[];

  currentRound: number;
  questionIndex: number;
  questions: Question[];
  currentQuestion?: Question;

  roundResults: RoundResult[];

  phaseStartedAtMs?: number;
  phaseEndsAtMs?: number;

  buzzQueue: BuzzEntry[];
  lockedAnswerPlayerId?: UUID;
  answeredPlayerIds: UUID[];
  
  lastAnswer?: {
    playerId: UUID;
    answer: number | string | boolean;
    isCorrect: boolean;
    pointsEarned: number;
    buzzTimeMs: number;
  };

  tiebreakerMode?: "quick_question" | "speed_round" | "sudden_death";

  voiceChannelId?: string;

  lastEventId: number;
  version: number;
};

export const DEFAULT_SCORING: ScoringConfig = {
  correctFast: 2,
  correctMedium: 1,
  correctSlow: 0,
  wrongPenalty: -2,
  wrongMaster: 0,
  timeout: 0,
  fastThresholdMs: 5000,
  mediumThresholdMs: 7000,
};

export const DEFAULT_TIMERS: TimersConfig = {
  intro: 9000,
  questionActive: 8000,
  answerSelection: 10000,
  reveal: 3000,
  waiting: 5000,
  roundScoreboard: 5000,
  tiebreakerChoice: 10000,
  matchEnd: 10000,
};

export const DEFAULT_DUO_CONFIG: GameConfig = {
  mode: "DUO",
  maxPlayers: 2,
  questionsPerRound: 10,
  roundsToWin: 2,
  maxRounds: 3,
  buzzEnabled: true,
  voiceChatEnabled: true,
  scoring: DEFAULT_SCORING,
  timers: DEFAULT_TIMERS,
};

export const DEFAULT_LEAGUE_INDIVIDUAL_CONFIG: GameConfig = {
  mode: "LEAGUE_INDIVIDUAL",
  maxPlayers: 2,
  questionsPerRound: 10,
  roundsToWin: 2,
  maxRounds: 3,
  buzzEnabled: true,
  voiceChatEnabled: true,
  scoring: DEFAULT_SCORING,
  timers: DEFAULT_TIMERS,
};

export const DEFAULT_LEAGUE_TEAM_CONFIG: GameConfig = {
  mode: "LEAGUE_TEAM",
  maxPlayers: 10,
  questionsPerRound: 10,
  roundsToWin: 2,
  maxRounds: 3,
  buzzEnabled: true,
  voiceChatEnabled: true,
  scoring: DEFAULT_SCORING,
  timers: DEFAULT_TIMERS,
};

export const DEFAULT_MASTER_CONFIG: GameConfig = {
  mode: "MASTER",
  maxPlayers: 40,
  questionsPerRound: 20,
  roundsToWin: 1,
  maxRounds: 1,
  buzzEnabled: true,
  voiceChatEnabled: false,
  scoring: {
    ...DEFAULT_SCORING,
    wrongPenalty: 0,
  },
  timers: DEFAULT_TIMERS,
};
