<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\DTO\LearningDirectionInput;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * TaxonomyProgressManager
 *
 * Autorité unique de progression interne d'un bassin Taxonomy.
 *
 * Responsabilités :
 *   - Maintenir un curseur de progression par couple (depth, domain_code)
 *   - Retourner la prochaine paire consommable : sub_domain + subject + dominant_idea
 *   - Avancer le curseur d'un seul cran après création réussie du noyau
 *   - Garantir qu'aucun sujet ni idée dominante ne soit sauté ou oublié
 *
 * Responsabilités interdites :
 *   - Ne choisit pas le domain_code (autorité : KernelRotationPlanner)
 *   - Ne choisit pas le depth (autorité : KernelRotationPlanner)
 *   - Ne connaît pas l'ordre du DomainCycle
 *   - Ne connaît pas les autres domaines ni les autres depths
 *   - Ne stocke pas les paires elles-mêmes (source de vérité : taxonomy.json via TaxonomyReader)
 *
 * Règle de rythme — peekNext() (brut) :
 *   Le curseur avance d'UN SEUL CRAN à chaque appel confirmConsumed().
 *   peekNext() est idempotent : retourne toujours la même paire sans avancer.
 *
 * Règle de rythme — peekNextApproved() (avec KLD) :
 *   Boucle interne jusqu'à trouver une idée approuvée par KLD.
 *   FAIL KLD → confirmConsumed() appelé en interne (saut définitif), retry immédiat.
 *   PASS/REVIEW_STRUCTURE → retourne la paire + kld_result ; confirmConsumed() reste
 *   à la charge de l'appelant (après création réussie du QuestionIntent).
 *   Max MAX_PEEK_ATTEMPTS sauts par appel pour éviter une boucle infinie.
 *
 * Table : taxonomy_progress (une ligne par couple depth × domain_code)
 */
final class TaxonomyProgressManager
{
    private const TABLE = 'taxonomy_progress';

    private const STATUS_ACTIVE    = 'active';
    private const STATUS_EXHAUSTED = 'exhausted';

    /** Garde anti-boucle infinie dans peekNextApproved(). */
    private const MAX_PEEK_ATTEMPTS = 500;

    public function __construct(private readonly TaxonomyReader $taxonomy) {}

    // =========================================================================
    // API publique
    // =========================================================================

    /**
     * Retourne la paire courante SANS avancer le curseur.
     *
     * Si aucune ligne n'existe pour ce couple → initialise le bassin automatiquement.
     * Si status = 'exhausted' → retourne null.
     *
     * Garantie : deux appels successifs sans confirmConsumed() retournent la MÊME paire.
     *
     * @return array{
     *     depth: int,
     *     domain: string,
     *     sub_domain: string,
     *     subject: string,
     *     dominant_idea: string,
     *     knowledge_frequency: int
     * }|null
     */
    public function peekNext(int $depth, string $domainCode): ?array
    {
        $row = $this->findOrInitialise($depth, $domainCode);

        if ($row === null || $row->status === self::STATUS_EXHAUSTED) {
            return null;
        }

        $idees = $this->taxonomy->getIdeesDominantes(
            $domainCode,
            $row->active_sub_domain,
            $row->active_subject,
        );

        if (empty($idees) || $row->dominant_idea_index >= count($idees)) {
            // Incohérence entre le curseur et taxonomy.json — ne pas crasher
            return null;
        }

        $dominantIdea = $idees[$row->dominant_idea_index];
        $kf           = $this->taxonomy->getKnowledgeFrequency(
            $domainCode,
            $row->active_sub_domain,
            $row->active_subject,
        );

        return [
            'depth'               => $depth,
            'domain'              => $domainCode,
            'sub_domain'          => $row->active_sub_domain,
            'subject'             => $row->active_subject,
            'dominant_idea'       => $dominantIdea,
            'knowledge_frequency' => $kf,
        ];
    }

    /**
     * Retourne la prochaine paire APPROUVÉE PAR KLD, en sautant automatiquement
     * les idées rejetées (FAIL).
     *
     * Comportement :
     *   - Appelle peekNext() pour obtenir le candidat courant.
     *   - Soumet le candidat à KeyLearningDirection::check().
     *   - FAIL  → confirmConsumed() interne (saut définitif du curseur) + retry.
     *   - PASS / REVIEW_STRUCTURE → retourne le candidat avec 'kld_result' ajouté.
     *   - null de peekNext() (bassin exhausted) → retourne null.
     *   - Après MAX_PEEK_ATTEMPTS sauts sans candidat approuvé → retourne null (garde).
     *
     * Contrat appelant :
     *   Après réception d'un candidat PASS/REVIEW_STRUCTURE, l'appelant DOIT :
     *     1. Créer le QuestionIntent.
     *     2. Appeler confirmConsumed() pour avancer le curseur.
     *     3. Appeler LearningDirectionRegistry::add() pour enregistrer la direction.
     *
     * @return array{
     *     depth: int,
     *     domain: string,
     *     sub_domain: string,
     *     subject: string,
     *     dominant_idea: string,
     *     knowledge_frequency: int,
     *     kld_result: \App\Services\QuestionBank\Rotation\DTO\LearningDirectionResult
     * }|null
     */
    public function peekNextApproved(
        int                       $depth,
        string                    $domainCode,
        LearningDirectionRegistry $registry,
        KeyLearningDirection      $kld,
    ): ?array {
        $maxAttempts = self::MAX_PEEK_ATTEMPTS;

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $candidate = $this->peekNext($depth, $domainCode);

            if ($candidate === null) {
                return null;
            }

            $input = new LearningDirectionInput(
                depth:              $depth,
                domainCode:         $domainCode,
                subDomain:          $candidate['sub_domain'],
                subject:            $candidate['subject'],
                dominantIdea:       $candidate['dominant_idea'],
                knowledgeFrequency: $candidate['knowledge_frequency'],
            );

            $result = $kld->check($input, $registry);

            if ($result->isFail()) {
                // Saut définitif : cette idée est un doublon pédagogique certain.
                // On avance le curseur sans enregistrer dans le registry.
                $this->confirmConsumed($depth, $domainCode);
                continue;
            }

            // PASS ou REVIEW_STRUCTURE → candidat approuvé, curseur intact.
            return array_merge($candidate, ['kld_result' => $result]);
        }

        // Garde : MAX_PEEK_ATTEMPTS sauts consécutifs sans approbation.
        return null;
    }

    /**
     * Avance le curseur d'UN SEUL CRAN après création réussie du QuestionIntent.
     *
     * Transitions (dans l'ordre) :
     *   1. dominant_idea_index++ si d'autres idées existent pour le sujet actif
     *   2. Sujet suivant + index=0 si d'autres sujets existent dans le sous-domaine actif
     *   3. Sous-domaine suivant + premier sujet + index=0 si disponible
     *      (active_sub_domain ajouté dans used_sub_domains avant de changer)
     *   4. Aucune transition possible → status='exhausted'
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
     * Trouve la ligne de progression ou la crée (premier sous-domaine, premier sujet, index=0).
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

        // Pas encore de curseur — initialiser depuis TaxonomyReader
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
     * Avance le curseur d'un seul cran selon les 4 transitions.
     * Appelé à l'intérieur d'une transaction DB (lockForUpdate déjà posé).
     */
    private function advance(object $row, int $depth, string $domainCode): void
    {
        $idees = $this->taxonomy->getIdeesDominantes(
            $domainCode,
            $row->active_sub_domain,
            $row->active_subject,
        );

        $nextIndex = (int) $row->dominant_idea_index + 1;

        // ── Transition 1 : idée suivante dans le sujet actif ─────────────────
        if ($nextIndex < count($idees)) {
            DB::table(self::TABLE)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->update([
                    'dominant_idea_index' => $nextIndex,
                    'updated_at'          => now(),
                ]);
            return;
        }

        // ── Transition 2 : sujet suivant dans le sous-domaine actif ──────────
        $sujets       = $this->taxonomy->getSubjects($domainCode, $row->active_sub_domain);
        $posSujet     = array_search($row->active_subject, $sujets, true);
        $nextSujetIdx = ($posSujet !== false) ? $posSujet + 1 : count($sujets);

        if ($nextSujetIdx < count($sujets)) {
            DB::table(self::TABLE)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->update([
                    'active_subject'      => $sujets[$nextSujetIdx],
                    'dominant_idea_index' => 0,
                    'updated_at'          => now(),
                ]);
            return;
        }

        // ── Transition 3 : sous-domaine suivant non épuisé ───────────────────
        $usedSubDomains    = json_decode($row->used_sub_domains, true) ?? [];
        $usedSubDomains[]  = $row->active_sub_domain;
        $tousSubDomains    = $this->taxonomy->getSubDomains($domainCode);
        $disponibles       = array_values(array_diff($tousSubDomains, $usedSubDomains));

        if (! empty($disponibles)) {
            $nextSd       = $disponibles[0];
            $premierSujet = $this->taxonomy->getSubjects($domainCode, $nextSd)[0] ?? null;

            if ($premierSujet !== null) {
                DB::table(self::TABLE)
                    ->where('depth', $depth)
                    ->where('domain_code', $domainCode)
                    ->update([
                        'active_sub_domain'   => $nextSd,
                        'active_subject'      => $premierSujet,
                        'dominant_idea_index' => 0,
                        'used_sub_domains'    => json_encode($usedSubDomains),
                        'updated_at'          => now(),
                    ]);
                return;
            }
        }

        // ── Transition 4 : bassin épuisé ─────────────────────────────────────
        DB::table(self::TABLE)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->update([
                'status'              => self::STATUS_EXHAUSTED,
                'active_sub_domain'   => null,
                'active_subject'      => null,
                'dominant_idea_index' => 0,
                'used_sub_domains'    => json_encode($usedSubDomains),
                'updated_at'          => now(),
            ]);
    }
}
