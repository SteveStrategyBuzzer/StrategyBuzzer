<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TaxonomyProgressManager
 *
 * Autorité unique de progression par SUJET dans un bassin Taxonomy.
 *
 * Responsabilités :
 *   - Maintenir un curseur de SUJET par couple (depth, domain_code)
 *   - Retourner le sujet courant : sub_domain + subject + knowledge_frequency
 *   - Avancer le curseur d'un sujet après que le chargeur d'idées a produit
 *     et que le gameplay a consommé les 5 slots du sujet actif
 *   - Garantir qu'aucun sujet ne soit sauté ou oublié
 *
 * Responsabilités interdites :
 *   - Ne choisit pas le domain_code (autorité : KernelRotationPlanner)
 *   - Ne choisit pas le depth (autorité : KernelRotationPlanner)
 *   - Ne connaît pas l'ordre du DomainCycle
 *   - Ne connaît pas les autres domaines ni les autres depths
 *   - Ne stocke pas les sujets eux-mêmes (source de vérité : taxonomy.json via TaxonomyReader)
 *   - Ne connaît PAS les idées dominantes (responsabilité : IdeaSlotLoader)
 *   - N'appelle JAMAIS confirmConsumed() suite à un FAIL KLD ou KEY_STRUCTURE
 *
 * Curseur :
 *   Le curseur pointe sur le SUJET ACTIF — jamais sur une idée.
 *   Les idées dominantes sont gérées en aval par le chargeur d'idées (IdeaSlotLoader).
 *   Un sujet est déclaré consommé quand le chargeur a rempli ses 5 slots
 *   et que le gameplay les a tous consommés. Alors seulement confirmConsumed() est appelé.
 *
 * Règle de rythme :
 *   Le curseur avance d'UN SEUL SUJET à chaque appel confirmConsumed().
 *   peekNext() est idempotent : retourne toujours le même sujet sans avancer.
 *
 * Note sur dominant_idea_index :
 *   La colonne existe en DB pour compatibilité ascendante.
 *   Elle n'est plus un curseur métier et reste à 0.
 *   Ne pas la lire comme logique de progression.
 *
 * Table : taxonomy_progress (une ligne par couple depth × domain_code)
 */
final class TaxonomyProgressManager implements DomainExhaustionChecker
{
    private const TABLE = 'taxonomy_progress';

    private const STATUS_ACTIVE    = 'active';
    private const STATUS_EXHAUSTED = 'exhausted';

    public function __construct(private readonly TaxonomyReader $taxonomy) {}

    // =========================================================================
    // API publique
    // =========================================================================

    /**
     * Retourne le sujet courant SANS avancer le curseur.
     *
     * Si aucune ligne n'existe pour ce couple → initialise le bassin automatiquement.
     * Si status = 'exhausted' → retourne null.
     *
     * Garantie : deux appels successifs sans confirmConsumed() retournent le MÊME sujet.
     *
     * @return array{
     *     depth: int,
     *     domain: string,
     *     sub_domain: string,
     *     subject: string,
     *     knowledge_frequency: int
     * }|null
     */
    public function peekNext(int $depth, string $domainCode): ?array
    {
        $row = $this->findOrInitialise($depth, $domainCode);

        if ($row === null || $row->status === self::STATUS_EXHAUSTED) {
            return null;
        }

        $kf = $this->taxonomy->getKnowledgeFrequency(
            $domainCode,
            $row->active_sub_domain,
            $row->active_subject,
        );

        return [
            'depth'               => $depth,
            'domain'              => $domainCode,
            'sub_domain'          => $row->active_sub_domain,
            'subject'             => $row->active_subject,
            'knowledge_frequency' => $kf,
        ];
    }

    /**
     * Avance le curseur d'UN SEUL SUJET après consommation complète de ce sujet.
     *
     * Transitions (dans l'ordre) :
     *   1. Sujet suivant dans le sous-domaine actif
     *   2. Sous-domaine suivant + premier sujet si disponible
     *      (active_sub_domain ajouté dans used_sub_domains avant de changer)
     *   3. Aucune transition possible → status='exhausted'
     *
     * Sémantique : le sujet actif a été entièrement consommé
     * (5 slots IdeaSlotLoader produits et consommés par le gameplay).
     *
     * JAMAIS appelé si KLD ou KEY_STRUCTURE ont rejeté.
     *
     * @throws RuntimeException si la ligne est introuvable ou déjà exhausted
     */
    public function confirmConsumed(int $depth, string $domainCode): void
    {
        DB::transaction(function () use ($depth, $domainCode) {
            $row = DB::table(self::TABLE)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new RuntimeException(
                    "TaxonomyProgressManager: aucune progression pour depth={$depth} domain={$domainCode}"
                );
            }

            if ($row->status === self::STATUS_EXHAUSTED) {
                throw new RuntimeException(
                    "TaxonomyProgressManager: bassin déjà exhausted pour depth={$depth} domain={$domainCode}"
                );
            }

            $this->advance($row, $depth, $domainCode);
        });
    }

    /**
     * Retourne true si le bassin est entièrement épuisé pour ce couple.
     */
    public function isExhausted(int $depth, string $domainCode): bool
    {
        $row = DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->first(['status']);

        return $row !== null && $row->status === self::STATUS_EXHAUSTED;
    }

    /**
     * Retourne le snapshot de progression pour audit / reporting.
     * Retourne null si le bassin n'a jamais été initialisé.
     *
     * Note : dominant_idea_index est retourné pour compatibilité DB mais
     * n'est plus un curseur métier (toujours 0).
     *
     * @return array{
     *     depth: int,
     *     domain_code: string,
     *     active_sub_domain: string|null,
     *     active_subject: string|null,
     *     dominant_idea_index: int,
     *     used_sub_domains: string[],
     *     status: string
     * }|null
     */
    public function getStatus(int $depth, string $domainCode): ?array
    {
        $row = DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'depth'               => $depth,
            'domain_code'         => $domainCode,
            'active_sub_domain'   => $row->active_sub_domain,
            'active_subject'      => $row->active_subject,
            'dominant_idea_index' => (int) $row->dominant_idea_index,
            'used_sub_domains'    => json_decode($row->used_sub_domains, true) ?? [],
            'status'              => $row->status,
        ];
    }

    // =========================================================================
    // Initialisation
    // =========================================================================

    /**
     * Trouve la ligne de progression ou la crée (premier sous-domaine, premier sujet).
     * Retourne null si taxonomy.json ne contient aucun candidat pour ce domain_code.
     */
    private function findOrInitialise(int $depth, string $domainCode): ?object
    {
        $existing = DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subDomains = $this->taxonomy->getSubDomains($domainCode);

        if (empty($subDomains)) {
            return null;
        }

        $firstSubDomain = $subDomains[0];
        $subjects       = $this->taxonomy->getSubjects($domainCode, $firstSubDomain);

        if (empty($subjects)) {
            return null;
        }

        $firstSubject = $subjects[0];

        DB::table(self::TABLE)->insertOrIgnore([
            'depth'               => $depth,
            'domain_code'         => $domainCode,
            'active_sub_domain'   => $firstSubDomain,
            'active_subject'      => $firstSubject,
            'dominant_idea_index' => 0,
            'used_sub_domains'    => '[]',
            'status'              => self::STATUS_ACTIVE,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);

        return DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->first();
    }

    // =========================================================================
    // Avancement du curseur — logique interne
    // =========================================================================

    /**
     * Avance le curseur d'un sujet selon les 3 transitions.
     * Appelé à l'intérieur d'une transaction DB (lockForUpdate déjà posé).
     */
    private function advance(object $row, int $depth, string $domainCode): void
    {
        // ── Transition 1 : sujet suivant dans le sous-domaine actif ──────────
        $sujets       = $this->taxonomy->getSubjects($domainCode, $row->active_sub_domain);
        $posSujet     = array_search($row->active_subject, $sujets, true);
        $nextSujetIdx = ($posSujet !== false) ? $posSujet + 1 : count($sujets);

        if ($nextSujetIdx < count($sujets)) {
            DB::table(self::TABLE)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->update([
                    'active_subject' => $sujets[$nextSujetIdx],
                    'updated_at'     => now(),
                ]);
            return;
        }

        // T1 échoue → tous les sujets du sous-domaine courant sont consommés
        $usedSubDomains   = json_decode($row->used_sub_domains, true) ?? [];
        $usedSubDomains[] = $row->active_sub_domain;

        // ── Transition 2 : sous-domaine suivant non épuisé ───────────────────
        $tousSubDomains = $this->taxonomy->getSubDomains($domainCode);
        $disponibles    = array_values(array_diff($tousSubDomains, $usedSubDomains));

        if (! empty($disponibles)) {
            $nextSd       = $disponibles[0];
            $premierSujet = $this->taxonomy->getSubjects($domainCode, $nextSd)[0] ?? null;

            if ($premierSujet !== null) {
                DB::table(self::TABLE)
                    ->where('depth', $depth)
                    ->where('domain_code', $domainCode)
                    ->update([
                        'active_sub_domain' => $nextSd,
                        'active_subject'    => $premierSujet,
                        'used_sub_domains'  => json_encode($usedSubDomains),
                        'updated_at'        => now(),
                    ]);
                return;
            }
        }

        // ── Transition 3 : bassin épuisé ─────────────────────────────────────
        DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->update([
                'status'            => self::STATUS_EXHAUSTED,
                'active_sub_domain' => null,
                'active_subject'    => null,
                'used_sub_domains'  => json_encode($usedSubDomains),
                'updated_at'        => now(),
            ]);
    }
}
