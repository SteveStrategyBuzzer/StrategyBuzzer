import type {
  GameState,
  ActiveEffect,
  SkillInventoryEntry,
  SkillEffectType,
  Phase,
  UUID,
} from "@strategybuzzer/shared";
import { getSkillDefinition } from "@strategybuzzer/shared";

export type SkillActivationResult =
  | { success: true; effect: ActiveEffect }
  | { success: false; reason: string };

export function canActivateSkill(
  state: GameState,
  playerId: UUID,
  skillId: SkillEffectType
): { allowed: boolean; reason?: string } {
  const def = getSkillDefinition(skillId);
  if (!def) return { allowed: false, reason: "unknown_skill" };

  if (def.passive) return { allowed: false, reason: "passive_skill" };

  if (!def.allowedPhases.includes(state.phase)) {
    return { allowed: false, reason: `phase_not_allowed:${state.phase}` };
  }

  const inventory = state.skillInventory[playerId] ?? [];
  const entry = inventory.find((e) => e.skillId === skillId);

  if (!entry) return { allowed: false, reason: "skill_not_in_inventory" };

  if (entry.usesLeft === 0) return { allowed: false, reason: "no_uses_left" };

  return { allowed: true };
}

export function applySkillEffect(
  state: GameState,
  sourcePlayerId: UUID,
  targetPlayerId: UUID,
  skillId: SkillEffectType,
  extraParams?: Record<string, unknown>
): GameState {
  const def = getSkillDefinition(skillId);
  if (!def) return state;

  const s: GameState = structuredClone(state);

  const inventory = s.skillInventory[sourcePlayerId] ?? [];
  const entryIdx = inventory.findIndex((e) => e.skillId === skillId);
  if (entryIdx !== -1 && inventory[entryIdx].usesLeft > 0) {
    inventory[entryIdx] = {
      ...inventory[entryIdx],
      usesLeft: Math.max(0, inventory[entryIdx].usesLeft - 1),
      lastUsedPhase: s.phase,
      lastUsedQuestionIndex: s.questionIndex,
    };
    s.skillInventory[sourcePlayerId] = inventory;
  }

  const expiresAtQuestionIndex =
    s.questionIndex + ((extraParams?.questionsAffected as number) ?? 1);

  const effect: ActiveEffect = {
    effectId: skillId,
    sourcePlayerId,
    targetPlayerId,
    appliedAtPhase: s.phase,
    appliedAtQuestionIndex: s.questionIndex,
    expiresAtQuestionIndex,
    params: { ...def.effectParams, ...extraParams },
  };

  s.activeEffects = [...s.activeEffects, effect];
  return s;
}

export function expireEffects(state: GameState): GameState {
  if (state.activeEffects.length === 0) return state;

  const remaining = state.activeEffects.filter(
    (e) => e.expiresAtQuestionIndex > state.questionIndex
  );

  if (remaining.length === state.activeEffects.length) return state;

  const s: GameState = structuredClone(state);
  s.activeEffects = remaining;
  return s;
}

export function getActiveEffectsForPlayer(
  state: GameState,
  playerId: UUID
): ActiveEffect[] {
  return state.activeEffects.filter((e) => e.targetPlayerId === playerId);
}

export function hasActiveEffect(
  state: GameState,
  playerId: UUID,
  effectId: SkillEffectType
): boolean {
  return state.activeEffects.some(
    (e) => e.targetPlayerId === playerId && e.effectId === effectId
  );
}

export function getPlayerInventory(
  state: GameState,
  playerId: UUID
): SkillInventoryEntry[] {
  return state.skillInventory[playerId] ?? [];
}

export function rechargeInventory(
  state: GameState,
  playerId: UUID
): GameState {
  const s: GameState = structuredClone(state);
  const inventory = s.skillInventory[playerId] ?? [];
  s.skillInventory[playerId] = inventory.map((entry) => {
    const def = getSkillDefinition(entry.skillId);
    if (!def || def.maxUses < 0) return entry;
    return { ...entry, usesLeft: def.maxUses };
  });
  return s;
}

/**
 * Consomme (supprime) un effet actif pour un joueur donné.
 * À appeler après qu'un effet ponctuel (score_shield, double_points) a été déclenché.
 */
export function consumeEffect(
  state: GameState,
  playerId: UUID,
  effectId: SkillEffectType
): GameState {
  const remaining = state.activeEffects.filter(
    (e) => !(e.targetPlayerId === playerId && e.effectId === effectId)
  );
  if (remaining.length === state.activeEffects.length) return state;
  const s: GameState = structuredClone(state);
  s.activeEffects = remaining;
  return s;
}

export type ScoreEffectResult = {
  pointsEarned: number;
  skillsTriggered: Array<{ skillId: string; playerId: string }>;
  newState: GameState;
};

/**
 * Applique les effets de skills actifs sur les points d'un joueur.
 * - score_shield : annule les pertes (−2 → 0). Consommé après usage.
 * - double_points : double les gains. Consommé après usage.
 * Retourne les points finaux, la liste des skills déclenchés, et le nouvel état.
 */
export function applyScoreEffects(
  state: GameState,
  playerId: UUID,
  rawPoints: number
): ScoreEffectResult {
  let pointsEarned = rawPoints;
  let newState = state;
  const skillsTriggered: Array<{ skillId: string; playerId: string }> = [];

  if (pointsEarned < 0 && hasActiveEffect(newState, playerId, "score_shield")) {
    pointsEarned = 0;
    newState = consumeEffect(newState, playerId, "score_shield");
    skillsTriggered.push({ skillId: "score_shield", playerId });
  }

  if (pointsEarned > 0 && hasActiveEffect(newState, playerId, "double_points")) {
    pointsEarned = pointsEarned * 2;
    newState = consumeEffect(newState, playerId, "double_points");
    skillsTriggered.push({ skillId: "double_points", playerId });
  }

  return { pointsEarned, skillsTriggered, newState };
}
