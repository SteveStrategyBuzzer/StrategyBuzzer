import type { SkillDefinition } from "../types.js";

export const SKILL_REGISTRY: SkillDefinition[] = [
  {
    id: "reduce_time",
    name: "Réduction du temps",
    description: "Réduit le temps de réponse de l'adversaire pour les prochaines questions",
    targetType: "opponent",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {
      reductionMs: 2000,
      questionsAffectedByRound: { 1: 5, 2: 5, 3: 3, 4: 1 },
    },
  },
  {
    id: "shuffle_answers",
    name: "Mélange des réponses",
    description: "Mélange l'ordre des réponses de l'adversaire pour une question",
    targetType: "opponent",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {
      questionsAffected: 1,
    },
  },
  {
    id: "faster_buzz",
    name: "Buzz rapide",
    description: "Réduit votre délai de buzz",
    targetType: "self",
    allowedPhases: ["QUESTION_ACTIVE"],
    uiSlot: "passive",
    activationConditions: ["always"],
    maxUses: -1,
    passive: true,
    effectParams: {
      reductionMs: 300,
    },
  },
  {
    id: "score_shield",
    name: "Bouclier de score",
    description: "Vous protège contre une perte de points sur la prochaine question",
    targetType: "self",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {
      questionsAffected: 1,
    },
  },
  {
    id: "reveal_correct",
    name: "Révélation",
    description: "Révèle la bonne réponse avant la question",
    targetType: "self",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {
      questionsAffected: 1,
    },
  },
  {
    id: "double_points",
    name: "Double points",
    description: "Double vos points gagnés sur la prochaine question",
    targetType: "self",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {
      questionsAffected: 1,
    },
  },
  {
    id: "skill_recharge",
    name: "Recharge de skills",
    description: "Réactive tous vos skills automatiquement après chaque manche",
    targetType: "self",
    allowedPhases: ["ROUND_SCOREBOARD"],
    uiSlot: "passive",
    activationConditions: ["always"],
    maxUses: -1,
    passive: true,
    effectParams: {},
  },
  {
    id: "illuminate_numbers",
    name: "Illumine si chiffre",
    description: "Met en évidence les réponses contenant un chiffre",
    targetType: "self",
    allowedPhases: ["ANSWER_SELECTION"],
    uiSlot: "answer_action",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {},
  },
  {
    id: "acidify_error",
    name: "Acidifie erreur",
    description: "Marque 2 mauvaises réponses en rouge pour les identifier",
    targetType: "self",
    allowedPhases: ["ANSWER_SELECTION"],
    uiSlot: "answer_action",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {},
  },
  {
    id: "ai_suggestion",
    name: "Suggestion IA",
    description: "Illumine une réponse avec 90% de chance que ce soit la bonne",
    targetType: "self",
    allowedPhases: ["ANSWER_SELECTION"],
    uiSlot: "answer_action",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: { successRate: 0.9 },
  },
  {
    id: "cancel_error",
    name: "Annule erreur",
    description: "Annule la pénalité -2 de la dernière mauvaise réponse (remplace par 0)",
    targetType: "self",
    allowedPhases: ["REVEAL", "ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 1,
    passive: false,
    effectParams: {},
  },
  {
    id: "premonition",
    name: "Prémonition",
    description: "Révèle la catégorie de la prochaine question",
    targetType: "self",
    allowedPhases: ["ROUND_SCOREBOARD"],
    uiSlot: "post_question",
    activationConditions: ["always"],
    maxUses: 5,
    passive: false,
    effectParams: {},
  },
  {
    id: "timeout_forgiveness",
    name: "Pardon du temps",
    description: "Un timeout ne cause pas de pénalité -2 (0 pt à la place)",
    targetType: "self",
    allowedPhases: [],
    uiSlot: "passive",
    activationConditions: ["always"],
    maxUses: -1,
    passive: true,
    effectParams: {},
  },
];

export function getSkillDefinition(id: string): SkillDefinition | undefined {
  return SKILL_REGISTRY.find((s) => s.id === id);
}

export function buildInitialInventory(
  skillIds: string[]
): import("../types.js").SkillInventoryEntry[] {
  return skillIds
    .map((id) => {
      const def = getSkillDefinition(id);
      if (!def) return null;
      return {
        skillId: def.id,
        usesLeft: def.maxUses,
        lastUsedPhase: null,
        lastUsedQuestionIndex: null,
      };
    })
    .filter((e): e is NonNullable<typeof e> => e !== null);
}
