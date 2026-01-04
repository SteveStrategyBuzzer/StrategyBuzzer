import { z } from "zod";

export const JoinRoomSchema = z.object({
  roomId: z.string().optional(),
  lobbyCode: z.string().optional(),
  playerId: z.string(),
  playerName: z.string(),
  avatarId: z.string().optional(),
  strategicAvatarId: z.string().optional(),
  division: z.string().optional(),
  token: z.string().optional(),
});

export const BuzzSchema = z.object({
  roomId: z.string(),
  clientTimeMs: z.number(),
});

export const AnswerSchema = z.object({
  roomId: z.string(),
  answer: z.union([z.number(), z.string(), z.boolean()]),
});

export const SkillSchema = z.object({
  roomId: z.string(),
  skillId: z.string(),
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

export type JoinRoomPayload = z.infer<typeof JoinRoomSchema>;
export type BuzzPayload = z.infer<typeof BuzzSchema>;
export type AnswerPayload = z.infer<typeof AnswerSchema>;
export type SkillPayload = z.infer<typeof SkillSchema>;
export type ReadyPayload = z.infer<typeof ReadySchema>;
export type VoiceOfferPayload = z.infer<typeof VoiceOfferSchema>;
export type VoiceAnswerPayload = z.infer<typeof VoiceAnswerSchema>;
export type VoiceCandidatePayload = z.infer<typeof VoiceCandidateSchema>;
export type PingCheckPayload = z.infer<typeof PingCheckSchema>;
