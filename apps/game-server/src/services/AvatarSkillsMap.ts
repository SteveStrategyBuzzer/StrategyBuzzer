/**
 * Mapping des avatars stratégiques vers leurs skill IDs (Socket.IO).
 * Source de vérité alignée avec AvatarSkillService.php côté Laravel.
 * Seuls les skills ACTIFS (non-passifs) du registre sont inclus ici.
 */

export const AVATAR_ACTIVE_SKILLS: Record<string, string[]> = {
  Challenger:   ["reduce_time"],
  Bouclier:     ["score_shield"],
  Oracle:       ["reveal_correct"],
  Saboteur:     ["shuffle_answers"],
  Doubleur:     ["double_points"],
};

/**
 * Retourne la liste des skill IDs actifs d'un avatar stratégique.
 * Retourne un tableau vide si l'avatar n'a pas de skills actifs configurés.
 */
export function getAvatarSkillIds(strategicAvatarId: string | undefined | null): string[] {
  if (!strategicAvatarId) return [];
  return AVATAR_ACTIVE_SKILLS[strategicAvatarId] ?? [];
}
