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
  | "SYNC"
  | "QUESTION_ACTIVE"
  | "ANSWER_COLLECTION"
  | "RESULT"
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
  color?: string;
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

export type PlayerStats = {
  correctAnswers: number;
  wrongAnswers: number;
  answersSubmitted: number;

  buzzCount: number;
  buzzWinCount: number;
  buzzReactionTotalMs: number;

  skillsUsed: number;
  skillsSuccessful: number;
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
  firstBuzzerCorrect: number;   // +2 pts for 1st to buzz + correct
  otherBuzzersCorrect: number;  // +1 pt for 2nd+ to buzz + correct
  noBuzzCorrect: number;        // 0 pt for no buzz + correct
  buzzWrong: number;            // -2 pts for buzz + wrong/timeout
  noBuzzWrong: number;          // 0 pt for no buzz + wrong/timeout
};

export type TimersConfig = {
  intro: number;
  sync: number;
  questionActive: number;
  answerCollection: number;
  result: number;
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

// ─── Skill System ─────────────────────────────────────────────────────────────

export type SkillTargetType =
  | "self"
  | "opponent"
  | "all_opponents"
  | "room";

export type SkillUiSlot =
  | "pre_question"
  | "during_question"
  | "post_question"
  | "passive";

export type SkillActivationCondition =
  | "always"
  | "if_behind"
  | "if_first_buzz"
  | "if_question_index_gt"
  | "if_opponent_score_gt";

export type SkillEffectType =
  | "reduce_time"
  | "shuffle_answers"
  | "faster_buzz"
  | "score_shield"
  | "reveal_correct"
  | "double_points"
  | "skill_recharge";

export type SkillDefinition = {
  id: SkillEffectType;
  name: string;
  description: string;
  targetType: SkillTargetType;
  allowedPhases: Phase[];
  uiSlot: SkillUiSlot;
  activationConditions: SkillActivationCondition[];
  maxUses: number;
  passive: boolean;
  effectParams?: Record<string, unknown>;
};

export type ActiveEffect = {
  effectId: SkillEffectType;
  sourcePlayerId: UUID;
  targetPlayerId: UUID;
  appliedAtPhase: Phase;
  appliedAtQuestionIndex: number;
  expiresAtQuestionIndex: number;
  params: Record<string, unknown>;
};

export type SkillInventoryEntry = {
  skillId: SkillEffectType;
  usesLeft: number;
  lastUsedPhase: Phase | null;
  lastUsedQuestionIndex: number | null;
};

// ─── Game State ───────────────────────────────────────────────────────────────

export type GameState = {
  sessionId: UUID;
  lobbyCode: string;
  createdAtMs: number;
  startedAtMs?: number;
  endedAtMs?: number;

  phase: Phase;
  config: GameConfig;

  players: Record<UUID, Player>;
  playerStats: Record<UUID, PlayerStats>;
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

  activeEffects: ActiveEffect[];
  skillInventory: Record<UUID, SkillInventoryEntry[]>;

  lastEventId: number;
  version: number;
};

export const DEFAULT_SCORING: ScoringConfig = {
  firstBuzzerCorrect: 2,    // +2 pts for 1st to buzz + correct
  otherBuzzersCorrect: 1,   // +1 pt for 2nd+ to buzz + correct
  noBuzzCorrect: 0,         // 0 pt for no buzz + correct
  buzzWrong: -2,            // -2 pts for buzz + wrong/timeout
  noBuzzWrong: 0,           // 0 pt for no buzz + wrong/timeout
};

export const DEFAULT_TIMERS: TimersConfig = {
  intro: 9000,
  questionActive: 8000,
  answerCollection: 10000,
  result: 3000,
  sync: 5000,
  roundScoreboard: 5000,
  tiebreakerChoice: 10000,
  matchEnd: 10000,
};

// Duo mode uses 8 seconds for question phase
export const DEFAULT_DUO_TIMERS: TimersConfig = {
  intro: 3000,
  questionActive: 8000,
  answerCollection: 10000,
  result: 5000,
  sync: 5000,
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
  timers: DEFAULT_DUO_TIMERS,
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
  scoring: DEFAULT_SCORING,
  timers: DEFAULT_TIMERS,
};
