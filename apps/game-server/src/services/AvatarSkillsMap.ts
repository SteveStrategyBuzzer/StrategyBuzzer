/**
 * Mapping des avatars stratégiques vers leurs skill IDs (Socket.IO).
 * Source de vérité alignée avec AvatarSkillService.php côté Laravel.
 * Seuls les skills ACTIFS (non-passifs) du registre sont inclus ici.
 * Les skills passifs (faster_buzz, skill_recharge, timeout_forgiveness) sont inclus
 * dans l'inventaire pour que le moteur puisse les détecter.
 */

export const AVATAR_ACTIVE_SKILLS: Record<string, string[]> = {
  // ── RARE ────────────────────────────────────────────────────────────────────
  Mathématicien: ["illuminate_numbers"],
  Scientifique:  ["acidify_error"],

  // ── ÉPIQUE ──────────────────────────────────────────────────────────────────
  Magicienne:    ["cancel_error"],
  Challenger:    ["reduce_time", "shuffle_answers"],
  // Historien : timeout_forgiveness est passif, mais on l'inclut pour que le
  // moteur puisse le détecter lors du scoring (passive lookup).
  Historien:     ["timeout_forgiveness"],

  // ── LÉGENDAIRE ──────────────────────────────────────────────────────────────
  "IA Junior":   ["ai_suggestion"],
  Sprinteur:     ["faster_buzz", "skill_recharge"],
  Visionnaire:   ["premonition"],

  // ── Anciens noms conservés pour rétrocompatibilité ──────────────────────────
  Bouclier:      ["score_shield"],
  Défenseur:     ["score_shield"],
  Oracle:        ["reveal_correct"],
  Saboteur:      ["shuffle_answers"],
  Doubleur:      ["double_points"],
};

/**
 * Retourne la liste des skill IDs actifs d'un avatar stratégique.
 * Retourne un tableau vide si l'avatar n'a pas de skills actifs configurés.
 */
export function getAvatarSkillIds(strategicAvatarId: string | undefined | null): string[] {
  if (!strategicAvatarId) return [];
  return AVATAR_ACTIVE_SKILLS[strategicAvatarId] ?? [];
}
