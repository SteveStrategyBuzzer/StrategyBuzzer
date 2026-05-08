<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Mémoire cross-match par joueur — Phase A (écriture) + Phase B (lecture).
 *
 * Deux clés Redis par joueur (ZSET, score = Unix timestamp) :
 *   player:mem:{uid}:groups   — question_group_ids vus (hard block)
 *   player:mem:{uid}:families — concept_families vus (soft cooldown)
 *
 * TTL stricts par mode, décroissance naturelle via ZRANGEBYSCORE à la
 * lecture. Tout appel Redis est dans try/catch → fail-open silencieux.
 * Aucun impact sur le gameplay, aucune migration DB, aucune persistance
 * PostgreSQL (Phase C reportée).
 */
class PlayerMemoryService
{
    private const MAX_EXCLUDE_GROUPS = 200;

    private const TTL_GROUPS = [
        'solo'     => 2592000,  // 30 jours
        'boss'     => 2592000,  // 30 jours
        'duo'      => 1814400,  // 21 jours
        'ligue'    => 1814400,  // 21 jours
        'mj_auto'  => 604800,   //  7 jours
        'mj_buzz'  => 604800,   //  7 jours
        'mj_pole'  => 604800,   //  7 jours
        'mj_order' => 604800,   //  7 jours
    ];

    private const TTL_FAMILIES = [
        'solo'     => 1209600,  // 14 jours
        'boss'     => 1209600,  // 14 jours
        'duo'      =>  864000,  // 10 jours
        'ligue'    => 1209600,  // 14 jours
        'mj_auto'  =>  259200,  //  3 jours
        'mj_buzz'  =>  259200,  //  3 jours
        'mj_pole'  =>  259200,  //  3 jours
        'mj_order' =>  259200,  //  3 jours
    ];

    private const TTL_GROUPS_DEFAULT   = 1209600; // 14 jours
    private const TTL_FAMILIES_DEFAULT =  604800; //  7 jours

    // -------------------------------------------------------------------------
    // Clés Redis
    // -------------------------------------------------------------------------

    private function keyGroups(int $userId): string
    {
        return "player:mem:{$userId}:groups";
    }

    private function keyFamilies(int $userId): string
    {
        return "player:mem:{$userId}:families";
    }

    private function ttlGroups(string $mode): int
    {
        return self::TTL_GROUPS[$mode] ?? self::TTL_GROUPS_DEFAULT;
    }

    private function ttlFamilies(string $mode): int
    {
        return self::TTL_FAMILIES[$mode] ?? self::TTL_FAMILIES_DEFAULT;
    }

    // -------------------------------------------------------------------------
    // Phase A — écriture après chaque question servie
    // -------------------------------------------------------------------------

    /**
     * Enregistre un group_id et sa concept_family dans la mémoire Redis du
     * joueur. Fail-open : toute exception Redis est loguée puis ignorée.
     *
     * Appelé depuis MarkQuestionGroupUsedJob (fire-and-forget, queue async).
     */
    public function recordGroupSeen(
        int     $userId,
        int     $groupId,
        ?string $conceptFamily,
        string  $mode
    ): void {
        try {
            $now       = time();
            $ttlG      = $this->ttlGroups($mode);
            $ttlF      = $this->ttlFamilies($mode);
            $kGroups   = $this->keyGroups($userId);
            $kFamilies = $this->keyFamilies($userId);

            Redis::zadd($kGroups, $now, (string) $groupId);
            Redis::expire($kGroups, $ttlG);

            if ($conceptFamily !== null && $conceptFamily !== '') {
                Redis::zadd($kFamilies, $now, $conceptFamily);
                Redis::expire($kFamilies, $ttlF);
            }
        } catch (\Throwable $e) {
            Log::warning('[PlayerMemoryService] recordGroupSeen failed (fail-open)', [
                'user_id'        => $userId,
                'group_id'       => $groupId,
                'concept_family' => $conceptFamily,
                'mode'           => $mode,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Phase B — lecture avant buildPlan()
    // -------------------------------------------------------------------------

    /**
     * Retourne le contexte joueur pour la planification du match :
     *   - exclude_group_ids     : group_ids vus dans la fenêtre hard-block
     *   - deprioritise_families : concept_families vus dans la fenêtre soft
     *
     * Fail-open : si Redis est indisponible → tableau vide → planner utilise
     * la diversité globale existante (usage_count ASC).
     *
     * @return array{exclude_group_ids: int[], deprioritise_families: string[]}
     */
    public function getForPlan(int $userId, string $mode): array
    {
        $empty = ['exclude_group_ids' => [], 'deprioritise_families' => []];

        try {
            $now  = time();
            $ttlG = $this->ttlGroups($mode);
            $ttlF = $this->ttlFamilies($mode);

            // group_ids vus dans la fenêtre hard-block (ZSET score ASC = plus
            // ancien en premier). On prend les MAX_EXCLUDE_GROUPS plus récents.
            $rawGroups = Redis::zrangebyscore(
                $this->keyGroups($userId),
                $now - $ttlG,
                '+inf'
            ) ?: [];

            $excludeGroupIds = array_map('intval', $rawGroups);

            if (count($excludeGroupIds) > self::MAX_EXCLUDE_GROUPS) {
                $excludeGroupIds = array_slice($excludeGroupIds, -self::MAX_EXCLUDE_GROUPS);
            }

            // concept_families vues dans la fenêtre soft cooldown
            $deprioritiseFamilies = Redis::zrangebyscore(
                $this->keyFamilies($userId),
                $now - $ttlF,
                '+inf'
            ) ?: [];

            if (empty($excludeGroupIds) && empty($deprioritiseFamilies)) {
                return $empty;
            }

            Log::debug('[PlayerMemoryService] getForPlan loaded', [
                'user_id'          => $userId,
                'mode'             => $mode,
                'exclude_groups'   => count($excludeGroupIds),
                'soft_families'    => count($deprioritiseFamilies),
            ]);

            return [
                'exclude_group_ids'     => $excludeGroupIds,
                'deprioritise_families' => array_values($deprioritiseFamilies),
            ];
        } catch (\Throwable $e) {
            Log::warning('[PlayerMemoryService] getForPlan failed (fail-open)', [
                'user_id' => $userId,
                'mode'    => $mode,
                'error'   => $e->getMessage(),
            ]);

            return $empty;
        }
    }

    // -------------------------------------------------------------------------
    // Utilitaires (tests / ops)
    // -------------------------------------------------------------------------

    /**
     * Retourne le TTL restant (secondes) des deux clés Redis d'un joueur.
     * Retourne -2 si la clé n'existe pas, -1 si elle n'a pas de TTL.
     *
     * @return array{groups_ttl: int, families_ttl: int}
     */
    public function inspectTtl(int $userId): array
    {
        try {
            return [
                'groups_ttl'   => (int) Redis::ttl($this->keyGroups($userId)),
                'families_ttl' => (int) Redis::ttl($this->keyFamilies($userId)),
            ];
        } catch (\Throwable $e) {
            return ['groups_ttl' => -2, 'families_ttl' => -2];
        }
    }

    /**
     * Compte les entrées actives (dans la fenêtre TTL) pour un joueur.
     *
     * @return array{groups_count: int, families_count: int}
     */
    public function inspectCounts(int $userId, string $mode): array
    {
        try {
            $now  = time();
            $ttlG = $this->ttlGroups($mode);
            $ttlF = $this->ttlFamilies($mode);

            return [
                'groups_count'   => (int) Redis::zcount(
                    $this->keyGroups($userId),
                    $now - $ttlG,
                    '+inf'
                ),
                'families_count' => (int) Redis::zcount(
                    $this->keyFamilies($userId),
                    $now - $ttlF,
                    '+inf'
                ),
            ];
        } catch (\Throwable $e) {
            return ['groups_count' => 0, 'families_count' => 0];
        }
    }
}
