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

/**
 * Consumes one use of a skill from the player's inventory WITHOUT creating an
 * activeEffect entry. Use this for purely visual / one-shot skills whose effect
 * is delivered via a direct socket emit and does not need to persist in game state.
 * (illuminate_numbers, acidify_error, ai_suggestion)
 */
export function consumeSkillUse(
  state: GameState,
  playerId: UUID,
  skillId: SkillEffectType
): GameState {
  const inventory = state.skillInventory[playerId] ?? [];
  const entryIdx = inventory.findIndex((e) => e.skillId === skillId);
  if (entryIdx === -1 || inventory[entryIdx].usesLeft <= 0) return state;

  const s: GameState = structuredClone(state);
  const inv = s.skillInventory[playerId];
  inv[entryIdx] = {
    ...inv[entryIdx],
    usesLeft: Math.max(0, inv[entryIdx].usesLeft - 1),
    lastUsedPhase: s.phase,
    lastUsedQuestionIndex: s.questionIndex,
  };
  return s;
}

/**
 * Computes the visual-effect payload for answer-phase skills.
 * These skills (illuminate_numbers, acidify_error, ai_suggestion) emit a
 * targeted hint to the buzzing player only; they do NOT add an activeEffect.
 *
 * @param skillId       - One of the three answer-phase visual skills
 * @param correctIndex  - Server-known correct answer index (from question)
 * @param totalAnswers  - Total number of answer choices
 * @returns             - Effect payload to emit via socket, or null if skill unknown
 */
export function applyAnswerPhaseSkill(
  skillId: SkillEffectType,
  correctIndex: number,
  totalAnswers: number
): Record<string, unknown> | null {
  const wrongIndices: number[] = [];
  for (let i = 0; i < totalAnswers; i++) {
    if (i !== correctIndex) wrongIndices.push(i);
  }

  if (skillId === "illuminate_numbers") {
    // Client-side visual: the client wraps digit sequences in the question text.
    // No additional server metadata needed — just the skill confirmation.
    return { skillId: "illuminate_numbers" };
  }

  if (skillId === "acidify_error") {
    // Server picks 2 random wrong answer indices to mark as dangerous
    const shuffled = [...wrongIndices].sort(() => Math.random() - 0.5);
    return { skillId: "acidify_error", wrongIndices: shuffled.slice(0, 2) };
  }

  if (skillId === "ai_suggestion") {
    // 90% chance show correct index, 10% show a random wrong one
    const suggestedIndex =
      Math.random() < 0.9
        ? correctIndex
        : (wrongIndices[Math.floor(Math.random() * wrongIndices.length)] ?? correctIndex);
    return { skillId: "ai_suggestion", suggestedIndex };
  }

  return null;
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
