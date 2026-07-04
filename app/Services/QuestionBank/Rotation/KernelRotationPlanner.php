<?php

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * KernelRotationPlanner
 *
 * Première brique décisionnelle après Blueprint Phase 0.
 *
 * Produit exclusivement :
 *   - depth_slot          : profondeur choisie selon DepthNeedMatrix
 *   - domain_slot         : domaine choisi selon DomainCycle
 *   - rotation_identifier : UUID unique de la rotation (≠ kernel_code)
 *
 * Interdictions absolues :
 *   Ne crée / ne modifie JAMAIS : sous-domaine, sujet, idée dominante,
 *   question, réponse, cognitif, traduction, semantic_key, QuestionIntent,
 *   READY_BANK, gameplay, kernel_code, kernel_code_prefix.
 *
 * Gestion d'erreur : RuntimeException (STOP) — jamais de fallback, jamais de retry.
 */
final class KernelRotationPlanner
{
    /**
     * Depths autorisés. Tout depth absent de cette liste est refusé.
     */
    private const ALLOWED_DEPTHS = [4, 6, 7, 8, 9];

    /**
     * Cibles officielles de noyaux par depth (v1 production).
     * Total : 12 000 noyaux.
     */
    public const DEPTH_TARGETS = [
        4 => 3000,
        6 => 3000,
        7 => 2500,
        8 => 2000,
        9 => 1500,
    ];

    // =========================================================================
    // Point d'entrée principal
    // =========================================================================

    /**
     * Produit le contexte de rotation.
     *
     * @param  int|null  $currentDomainIndex  Index courant dans le cycle domaine.
     *                                        null = début du cycle (retourne index 0).
     *
     * @return array{
     *     rotation_context: array{
     *         depth_slot:          array{depth: int},
     *         domain_slot:         array{domain_id: string, domain_code: string},
     *         rotation_identifier: string
     *     },
     *     next_domain_index: int
     * }
     *
     * @throws RuntimeException STOP — aucun recovery, aucun fallback.
     */
    public function plan(?int $currentDomainIndex = null): array
    {
        $existingByDepth = $this->loadExistingKernelCounts();
        $matrix          = $this->buildDepthNeedMatrix($existingByDepth);
        $depth           = $this->chooseDepth($matrix);

        $domains = $this->loadDomains();
        $nextIdx = $this->advanceDomainIndex($currentDomainIndex, $domains);
        $domain  = $domains[$nextIdx];

        $rotationIdentifier = (string) Str::uuid();

        return [
            'rotation_context' => [
                'depth_slot'          => ['depth' => $depth],
                'domain_slot'         => ['domain_id' => $domain, 'domain_code' => $domain],
                'rotation_identifier' => $rotationIdentifier,
            ],
            'next_domain_index' => $nextIdx,
        ];
    }

    // =========================================================================
    // DepthNeedMatrix — calcul pur (testable sans base de données)
    // =========================================================================

    /**
     * Construit la matrice des besoins par depth à partir des comptes fournis.
     *
     * Calcul pur — ne touche jamais la base de données.
     * En production, l'appelant passe loadExistingKernelCounts() comme source.
     * En test, on injecte directement les comptes.
     *
     * @param  array<int, int>  $existingByDepth  Ex : [4 => 2000, 6 => 500]
     * @return array<int, array{
     *     depth:             int,
     *     target_kernels:    int,
     *     existing_kernels:  int,
     *     remaining_kernels: int,
     *     completed:         bool
     * }>
     */
    public function buildDepthNeedMatrix(array $existingByDepth = []): array
    {
        $matrix = [];

        foreach (self::DEPTH_TARGETS as $depth => $target) {
            $existing  = (int) ($existingByDepth[$depth] ?? 0);
            $remaining = max(0, $target - $existing);

            $matrix[] = [
                'depth'             => $depth,
                'target_kernels'    => $target,
                'existing_kernels'  => $existing,
                'remaining_kernels' => $remaining,
                'completed'         => $remaining === 0,
            ];
        }

        return $matrix;
    }

    // =========================================================================
    // Sélection du depth
    // =========================================================================

    /**
     * Choisit le depth ayant le plus grand remaining_kernels.
     * En cas d'égalité : depth le plus bas.
     * STOP si aucun remaining > 0 ou matrice vide.
     *
     * @param  array<int, array{depth: int, remaining_kernels: int}>  $matrix
     *
     * @throws RuntimeException STOP si aucun depth disponible.
     */
    public function chooseDepth(array $matrix): int
    {
        if (empty($matrix)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — DepthNeedMatrix absente ou invalide.'
            );
        }

        $candidates = array_values(array_filter(
            $matrix,
            static fn($row) => (int) ($row['remaining_kernels'] ?? 0) > 0
        ));

        if (empty($candidates)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — aucun depth restant. Tous les targets sont atteints.'
            );
        }

        // Highest remaining first ; tie-break = lowest depth
        usort($candidates, static function (array $a, array $b): int {
            $cmp = (int) $b['remaining_kernels'] <=> (int) $a['remaining_kernels'];
            return $cmp !== 0 ? $cmp : ((int) $a['depth'] <=> (int) $b['depth']);
        });

        $depth = (int) $candidates[0]['depth'];

        if (!in_array($depth, self::ALLOWED_DEPTHS, true)) {
            throw new RuntimeException(
                "[KernelRotationPlanner] STOP — depth choisi ({$depth}) hors de la liste autorisée [4,6,7,8,9]."
            );
        }

        return $depth;
    }

    // =========================================================================
    // DomainCycle
    // =========================================================================

    /**
     * Charge la liste officielle des domaines Gameplay v1.
     *
     * "general" peut exister dans la config mais n'est PAS un domaine Gameplay.
     * KernelRotationPlanner n'utilise que les 8 domaines Gameplay ci-dessous.
     * DomainCycle Gameplay v1 — ordre officiel figé :
     *
     *   histoire, geographie, sport, art, cuisine, science, cinema, faune
     *
     * @return string[]
     * @throws RuntimeException STOP si liste vide.
     */
    public function loadDomains(): array
    {
        $domains = [
            'histoire',
            'geographie',
            'sport',
            'art',
            'cuisine',
            'science',
            'cinema',
            'faune',
        ];

        if (empty($domains)) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — DomainCycle absent. '
                . 'La liste de domaines Gameplay est vide.'
            );
        }

        return $domains;
    }

    /**
     * Avance d'une position dans le cycle domaine.
     *
     * Règles :
     *   null  → retourne 0 (début du cycle)
     *   last  → retourne 0 (bouclage)
     *   other → retourne currentIndex + 1
     *
     * Aucun saut. Aucune optimisation. Aucune heuristique.
     *
     * @param  int|null  $currentIndex  null = début du cycle
     * @param  string[]  $domains
     *
     * @throws RuntimeException STOP si liste vide.
     */
    public function advanceDomainIndex(?int $currentIndex, array $domains): int
    {
        $count = count($domains);

        if ($count === 0) {
            throw new RuntimeException(
                '[KernelRotationPlanner] STOP — liste de domaines vide.'
            );
        }

        if ($currentIndex === null) {
            return 0;
        }

        return ($currentIndex + 1) % $count;
    }

    // =========================================================================
    // Chargement DB (production uniquement — isolé pour testabilité)
    // =========================================================================

    /**
     * Charge le nombre de noyaux existants par depth depuis question_groups.
     *
     * @return array<int, int>  Ex : [4 => 1200, 6 => 300]
     */
    private function loadExistingKernelCounts(): array
    {
        return DB::table('question_groups')
            ->select('difficulty_depth', DB::raw('COUNT(*) as n'))
            ->whereIn('difficulty_depth', self::ALLOWED_DEPTHS)
            ->groupBy('difficulty_depth')
            ->get()
            ->pluck('n', 'difficulty_depth')
            ->toArray();
    }
}
