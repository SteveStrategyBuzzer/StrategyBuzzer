import type { UUID, Phase, SkillId, Mode } from "./types";

export type BaseEvent = {
  id: number;
  atMs: number;
  sessionId: UUID;
};

export type PlayerJoinedEvent = BaseEvent & {
  type: "PLAYER_JOINED";
  playerId: UUID;
  name: string;
  avatarId?: string;
  strategicAvatarId?: string;
  isBot?: boolean;
  isHost?: boolean;
  teamId?: UUID;
  division?: string;
};

export type PlayerLeftEvent = BaseEvent & {
  type: "PLAYER_LEFT";
  playerId: UUID;
  reason?: "disconnect" | "kick" | "abandon";
};

export type PlayerReadyEvent = BaseEvent & {
  type: "PLAYER_READY";
  playerId: UUID;
  isReady: boolean;
};

export type GameStartedEvent = BaseEvent & {
  type: "GAME_STARTED";
  config: {
    mode: Mode;
    questionsPerRound: number;
    roundsToWin: number;
  };
};

export type PhaseChangedEvent = BaseEvent & {
  type: "PHASE_CHANGED";
  fromPhase: Phase;
  toPhase: Phase;
  phaseEndsAtMs?: number;
  questionIndex?: number;
  roundNumber?: number;
};

export type QuestionPublishedEvent = BaseEvent & {
  type: "QUESTION_PUBLISHED";
  questionIndex: number;
  questionId: UUID;
  text: string;
  choices?: string[];
  category: string;
  subCategory?: string;
  difficulty: number;
  timeLimitMs: number;
};

export type BuzzReceivedEvent = BaseEvent & {
  type: "BUZZ_RECEIVED";
  playerId: UUID;
  buzzTimeMs: number;
  serverReceivedAtMs: number;
  latencyMs?: number;
  position: number;
};

export type AnswerSubmittedEvent = BaseEvent & {
  type: "ANSWER_SUBMITTED";
  playerId: UUID;
  answer: number | string | boolean;
  submittedAtMs: number;
};

export type AnswerRevealedEvent = BaseEvent & {
  type: "ANSWER_REVEALED";
  playerId: UUID;
  answer: number | string | boolean;
  isCorrect: boolean;
  correctAnswer: number | string | boolean;
  pointsEarned: number;
  buzzTimeMs: number;
  totalScore: number;
  roundScore: number;
  funFact?: string;
};

export type TimeoutEvent = BaseEvent & {
  type: "TIMEOUT";
  phase: Phase;
  affectedPlayerIds: UUID[];
};

export type SkillActivatedEvent = BaseEvent & {
  type: "SKILL_ACTIVATED";
  playerId: UUID;
  skillId: SkillId;
  targetPlayerId?: UUID;
  effect: string;
  duration?: number;
};

export type SkillEffectAppliedEvent = BaseEvent & {
  type: "SKILL_EFFECT_APPLIED";
  skillId: SkillId;
  sourcePlayerId: UUID;
  targetPlayerId?: UUID;
  effectType: "attack" | "defense" | "buff" | "debuff";
  value?: number;
};

export type ScoreUpdatedEvent = BaseEvent & {
  type: "SCORE_UPDATED";
  playerId: UUID;
  delta: number;
  reason: "correct_answer" | "wrong_answer" | "timeout" | "skill" | "bonus";
  newScore: number;
  newRoundScore: number;
};

export type RoundEndedEvent = BaseEvent & {
  type: "ROUND_ENDED";
  roundNumber: number;
  playerScores: Record<UUID, number>;
  winnerId?: UUID;
  isTie: boolean;
  playerRoundsWon: Record<UUID, number>;
};

export type TiebreakerStartedEvent = BaseEvent & {
  type: "TIEBREAKER_STARTED";
  mode: "quick_question" | "speed_round" | "sudden_death";
  participantIds: UUID[];
};

export type TiebreakerChoiceEvent = BaseEvent & {
  type: "TIEBREAKER_CHOICE";
  playerId: UUID;
  choice: "quick_question" | "speed_round" | "sudden_death";
};

export type MatchEndedEvent = BaseEvent & {
  type: "MATCH_ENDED";
  winnerId?: UUID;
  isTie: boolean;
  finalScores: Record<UUID, number>;
  roundsWon: Record<UUID, number>;
  rewards?: {
    playerId: UUID;
    coins: number;
    xp: number;
    trophies?: number;
  }[];
  duration: number;
};

export type VoiceChannelJoinedEvent = BaseEvent & {
  type: "VOICE_CHANNEL_JOINED";
  playerId: UUID;
  channelId: string;
};

export type VoiceChannelLeftEvent = BaseEvent & {
  type: "VOICE_CHANNEL_LEFT";
  playerId: UUID;
  channelId: string;
};

export type GameEvent =
  | PlayerJoinedEvent
  | PlayerLeftEvent
  | PlayerReadyEvent
  | GameStartedEvent
  | PhaseChangedEvent
  | QuestionPublishedEvent
  | BuzzReceivedEvent
  | AnswerSubmittedEvent
  | AnswerRevealedEvent
  | TimeoutEvent
  | SkillActivatedEvent
  | SkillEffectAppliedEvent
  | ScoreUpdatedEvent
  | RoundEndedEvent
  | TiebreakerStartedEvent
  | TiebreakerChoiceEvent
  | MatchEndedEvent
  | VoiceChannelJoinedEvent
  | VoiceChannelLeftEvent;

export type GameEventType = GameEvent["type"];
