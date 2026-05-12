import { z } from "zod";
import type { SkillEffectType } from "@strategybuzzer/shared";

const SKILL_EFFECT_IDS = [
  "reduce_time",
  "shuffle_answers",
  "faster_buzz",
  "score_shield",
  "reveal_correct",
  "double_points",
  "skill_recharge",
  "illuminate_numbers",
  "acidify_error",
  "ai_suggestion",
  "cancel_error",
  "premonition",
  "timeout_forgiveness",
] as const satisfies readonly SkillEffectType[];

export const JoinRoomSchema = z.object({
  roomId: z.string().optional(),
  lobbyCode: z.string().optional(),
  playerId: z.string(),
  playerName: z.string(),
  avatarId: z.string().nullish(),
  strategicAvatarId: z.string().nullish(),
  color: z.string().optional(),
  division: z.string().nullish(),
  token: z.string().optional(),
});

export const BuzzSchema = z.object({
  roomId: z.string(),
  clientTimeMs: z.number(),
});

export const AnswerSchema = z.object({
  roomId: z.string(),
  answer: z.union([z.number(), z.string(), z.boolean()]),
  /**
   * Client-submitted shuffle revision at the time of answer selection.
   * Used by Node to resolve the correct correctIndex for scoring under
   * race conditions (e.g. answer_order_changed arrives after answer emit).
   * Optional: absent = no shuffle active or pre-migration client.
   */
  shuffleRevision: z.number().int().nonnegative().optional(),
});

export const SkillSchema = z.object({
  roomId: z.string(),
  skillId: z.enum(SKILL_EFFECT_IDS),
  targetPlayerId: z.string().optional(),
});

export const ReadySchema = z.object({
  roomId: z.string(),
  isReady: z.boolean(),
});

export const VoiceOfferSchema = z.object({
  roomId: z.string(),
  targetId: z.string(),
  offer: z.unknown(),
});

export const VoiceAnswerSchema = z.object({
  roomId: z.string(),
  targetId: z.string(),
  answer: z.unknown(),
});

export const VoiceCandidateSchema = z.object({
  roomId: z.string(),
  targetId: z.string(),
  candidate: z.unknown(),
});

export const PingCheckSchema = z.object({
  clientTime: z.number(),
});

export const TimeSyncSchema = z.object({
  clientSentAtMs: z.number(),
});

export const QuestionPageReadySchema = z.object({
  roomId: z.string(),
});

// Sent by /duo/result on mount. Mirrors question_page_ready: the orchestrator
// resets the 60s RESULT countdown only when the SECOND player arrives, so the
// player who lands first sees a "waiting for opponent" overlay until the peer
// is ready. Solo and bot-only rooms naturally skip this barrier (a single
// human still triggers the second-arrival path immediately).
export const ResultPageReadySchema = z.object({
  roomId: z.string(),
});

export type JoinRoomPayload = z.infer<typeof JoinRoomSchema>;
export type BuzzPayload = z.infer<typeof BuzzSchema>;
export type AnswerPayload = z.infer<typeof AnswerSchema>;
export type SkillPayload = z.infer<typeof SkillSchema>;
export type ReadyPayload = z.infer<typeof ReadySchema>;
export type VoiceOfferPayload = z.infer<typeof VoiceOfferSchema>;
export type VoiceAnswerPayload = z.infer<typeof VoiceAnswerSchema>;
export type VoiceCandidatePayload = z.infer<typeof VoiceCandidateSchema>;
export type PingCheckPayload = z.infer<typeof PingCheckSchema>;
export type TimeSyncPayload = z.infer<typeof TimeSyncSchema>;
export type QuestionPageReadyPayload = z.infer<typeof QuestionPageReadySchema>;
export type ResultPageReadyPayload = z.infer<typeof ResultPageReadySchema>;
