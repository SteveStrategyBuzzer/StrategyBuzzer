<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use Illuminate\Support\Facades\DB;

/**
 * TaxonomyBankRepository — accès DB exclusif pour la couche Taxonomy.
 *
 * Couvre les 4 tables :
 *   - taxonomy_subdomain_bank
 *   - taxonomy_subject_bank
 *   - taxonomy_dominant_idea_bank
 *   - taxonomy_generation_memory
 *
 * Idempotence : toutes les insertions sont protégées par insertOrIgnore()
 * ou par firstOrCreate équivalent sur l'identité métier.
 *
 * NE PAS y mettre de logique métier — c'est une couche d'accès aux données.
 */
final class TaxonomyBankRepository
{
    private const TABLE_SUBDOMAINS = 'taxonomy_subdomain_bank';
    private const TABLE_SUBJECTS   = 'taxonomy_subject_bank';
    private const TABLE_IDEAS      = 'taxonomy_dominant_idea_bank';
    private const TABLE_MEMORY     = 'taxonomy_generation_memory';

    // =========================================================================
    // Sous-domaines
    // =========================================================================

    /**
     * Trouve ou crée un sous-domaine par identité métier (depth + domain + nom).
     * Idempotent.
     */
    public function findOrCreateSubdomain(int $depth, string $domainCode, string $subdomainName): object
    {
        $existing = DB::table(self::TABLE_SUBDOMAINS)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->where('subdomain_name', $subdomainName)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        DB::table(self::TABLE_SUBDOMAINS)->insertOrIgnore([
            'depth'                    => $depth,
            'domain_code'              => $domainCode,
            'subdomain_name'           => $subdomainName,
            'status'                   => 'ACTIVE',
            'generation_exhausted'     => false,
            'subject_attempt_count'    => 0,
            'created_at'               => now(),
            'updated_at'               => now(),
        ]);

        $row = DB::table(self::TABLE_SUBDOMAINS)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->where('subdomain_name', $subdomainName)
            ->first();

        if ($row === null) {
            throw new \RuntimeException("TaxonomyBankRepository: subdomain not found after insert: depth={$depth} domain={$domainCode} name={$subdomainName}");
        }

        return $row;
    }

    /**
     * Retourne tous les sous-domaines pour (depth, domain) ordonnés par ID.
     *
     * @return object[]
     */
    public function getSubdomains(int $depth, string $domainCode): array
    {
        return DB::table(self::TABLE_SUBDOMAINS)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Retourne le premier sous-domaine non exhausted (peut encore produire des sujets ou a des sujets avec idées).
     */
    public function findActiveSubdomain(int $depth, string $domainCode): ?object
    {
        // Sous-domaine avec génération non épuisée OU ayant des sujets avec idées restantes
        return DB::table(self::TABLE_SUBDOMAINS . ' as sd')
            ->where('sd.depth', $depth)
            ->where('sd.domain_code', $domainCode)
            ->where(function ($q) {
                $q->where('sd.generation_exhausted', false)
                  ->orWhereExists(function ($q2) {
                      $q2->select(DB::raw(1))
                         ->from(self::TABLE_SUBJECTS . ' as s')
                         ->whereColumn('s.subdomain_id', 'sd.id')
                         ->where('s.idea_generation_exhausted', false);
                  });
            })
            ->orderBy('sd.id')
            ->first(['sd.*']);
    }

    /**
     * Marque un sous-domaine comme "génération épuisée" (plus de sujets possibles).
     */
    public function markSubdomainGenerationExhausted(int $subdomainId): void
    {
        DB::table(self::TABLE_SUBDOMAINS)
            ->where('id', $subdomainId)
            ->update([
                'generation_exhausted' => true,
                'updated_at'           => now(),
            ]);
    }

    /**
     * Incrémente le compteur de tentatives de génération de sujets.
     */
    public function incrementSubdomainSubjectAttemptCount(int $subdomainId): void
    {
        DB::table(self::TABLE_SUBDOMAINS)
            ->where('id', $subdomainId)
            ->increment('subject_attempt_count', 1, ['updated_at' => now()]);
    }

    /**
     * Vérifie si tous les sous-domaines sont génération-épuisés pour (depth, domain).
     */
    public function allSubdomainsExhausted(int $depth, string $domainCode): bool
    {
        $hasActive = DB::table(self::TABLE_SUBDOMAINS)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->where('generation_exhausted', false)
            ->exists();

        return ! $hasActive;
    }

    // =========================================================================
    // Sujets
    // =========================================================================

    /**
     * Trouve ou crée un sujet par identité métier (subdomain_id + nom).
     * Idempotent.
     */
    public function findOrCreateSubject(int $subdomainId, string $subjectName): object
    {
        $existing = DB::table(self::TABLE_SUBJECTS)
            ->where('subdomain_id', $subdomainId)
            ->where('subject_name', $subjectName)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        DB::table(self::TABLE_SUBJECTS)->insertOrIgnore([
            'subdomain_id'              => $subdomainId,
            'subject_name'              => $subjectName,
            'status'                    => 'AVAILABLE',
            'idea_attempt_count'        => 0,
            'idea_generation_exhausted' => false,
            'created_at'                => now(),
            'updated_at'                => now(),
        ]);

        $row = DB::table(self::TABLE_SUBJECTS)
            ->where('subdomain_id', $subdomainId)
            ->where('subject_name', $subjectName)
            ->first();

        if ($row === null) {
            throw new \RuntimeException("TaxonomyBankRepository: subject not found after insert: subdomain={$subdomainId} name={$subjectName}");
        }

        return $row;
    }

    /**
     * Retourne tous les sujets d'un sous-domaine ordonnés par ID.
     *
     * @return object[]
     */
    public function getSubjectsForSubdomain(int $subdomainId): array
    {
        return DB::table(self::TABLE_SUBJECTS)
            ->where('subdomain_id', $subdomainId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * Retourne le premier sujet pour lequel des idées peuvent encore être générées.
     */
    public function findSubjectNeedingIdeas(int $subdomainId): ?object
    {
        return DB::table(self::TABLE_SUBJECTS . ' as s')
            ->where('s.subdomain_id', $subdomainId)
            ->where('s.idea_generation_exhausted', false)
            ->where(function ($q) {
                // Soit moins de MAX_IDEAS PASS disponibles, soit aucune idée générée encore
                $q->whereRaw('(SELECT COUNT(*) FROM ' . self::TABLE_IDEAS . ' di '
                    . 'WHERE di.subject_id = s.id AND di.validation_status = ? AND di.status = ?)'
                    . ' < ?', ['PASS', 'AVAILABLE', TaxonomyConfig::MAX_DOMINANT_IDEAS_PER_SUBJECT]);
            })
            ->orderBy('s.id')
            ->first(['s.*']);
    }

    /**
     * Marque un sujet comme "génération idées épuisée".
     */
    public function markSubjectIdeaGenerationExhausted(int $subjectId): void
    {
        DB::table(self::TABLE_SUBJECTS)
            ->where('id', $subjectId)
            ->update([
                'idea_generation_exhausted' => true,
                'updated_at'                => now(),
            ]);
    }

    /**
     * Incrémente le compteur de tentatives de génération d'idées.
     */
    public function incrementSubjectIdeaAttemptCount(int $subjectId): void
    {
        DB::table(self::TABLE_SUBJECTS)
            ->where('id', $subjectId)
            ->increment('idea_attempt_count', 1, ['updated_at' => now()]);
    }

    /**
     * Marque un sujet comme CONSUMED.
     */
    public function markSubjectConsumed(int $subjectId): void
    {
        DB::table(self::TABLE_SUBJECTS)
            ->where('id', $subjectId)
            ->update([
                'status'     => 'CONSUMED',
                'updated_at' => now(),
            ]);
    }

    /**
     * Nombre de sujets AVAILABLE dans un sous-domaine.
     */
    public function countAvailableSubjects(int $subdomainId): int
    {
        return (int) DB::table(self::TABLE_SUBJECTS)
            ->where('subdomain_id', $subdomainId)
            ->where('status', 'AVAILABLE')
            ->count();
    }

    // =========================================================================
    // Idées Dominantes
    // =========================================================================

    /**
     * Persiste une idée PASS (idempotent par subject_id + valeur).
     */
    public function persistPassIdea(int $subjectId, string $ideaValue): object
    {
        $existing = DB::table(self::TABLE_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('idea_value', $ideaValue)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        DB::table(self::TABLE_IDEAS)->insertOrIgnore([
            'subject_id'        => $subjectId,
            'idea_value'        => $ideaValue,
            'validation_status' => 'PASS',
            'fail_reason'       => null,
            'fail_conflict_with'=> null,
            'status'            => 'AVAILABLE',
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $row = DB::table(self::TABLE_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('idea_value', $ideaValue)
            ->first();

        if ($row === null) {
            throw new \RuntimeException("TaxonomyBankRepository: idea not found after insert: subject={$subjectId} value={$ideaValue}");
        }

        return $row;
    }

    /**
     * Persiste une idée FAIL (idempotent par subject_id + valeur).
     */
    public function persistFailIdea(
        int     $subjectId,
        string  $ideaValue,
        string  $reason,
        ?string $conflictWith = null,
    ): void {
        DB::table(self::TABLE_IDEAS)->insertOrIgnore([
            'subject_id'         => $subjectId,
            'idea_value'         => $ideaValue,
            'validation_status'  => 'FAIL',
            'fail_reason'        => $reason,
            'fail_conflict_with' => $conflictWith,
            'status'             => 'FAIL',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    /**
     * Trouve la première idée PASS + AVAILABLE pour (depth, domain).
     * Ordonnée : subdomain.id ASC, subject.id ASC, idea.id ASC.
     * → Garantit l'idempotence de peekNext().
     */
    public function findFirstAvailableIdea(int $depth, string $domainCode): ?object
    {
        return DB::table(self::TABLE_IDEAS . ' as di')
            ->join(self::TABLE_SUBJECTS . ' as s', 's.id', '=', 'di.subject_id')
            ->join(self::TABLE_SUBDOMAINS . ' as sd', 'sd.id', '=', 's.subdomain_id')
            ->where('sd.depth', $depth)
            ->where('sd.domain_code', $domainCode)
            ->where('di.validation_status', 'PASS')
            ->where('di.status', 'AVAILABLE')
            ->orderBy('sd.id')
            ->orderBy('s.id')
            ->orderBy('di.id')
            ->select([
                'di.id as idea_id',
                'di.idea_value',
                'di.subject_id',
                's.subject_name',
                's.subdomain_id',
                'sd.subdomain_name',
            ])
            ->first();
    }

    /**
     * Marque la première idée AVAILABLE comme CONSUMED (idempotent).
     */
    public function markIdeaConsumed(int $ideaId): void
    {
        DB::table(self::TABLE_IDEAS)
            ->where('id', $ideaId)
            ->where('status', 'AVAILABLE')
            ->update([
                'status'     => 'CONSUMED',
                'updated_at' => now(),
            ]);
    }

    /**
     * Retourne toutes les idées PASS pour un sujet (peu importe le statut AVAILABLE/CONSUMED).
     *
     * @return string[]
     */
    public function getPassIdeaValues(int $subjectId): array
    {
        return DB::table(self::TABLE_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'PASS')
            ->orderBy('id')
            ->pluck('idea_value')
            ->all();
    }

    /**
     * Retourne toutes les idées FAIL pour un sujet avec leurs détails.
     *
     * @return array<array{value: string, reason: string, conflict_with: string|null}>
     */
    public function getFailIdeaDetails(int $subjectId): array
    {
        return DB::table(self::TABLE_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'FAIL')
            ->orderBy('id')
            ->get(['idea_value', 'fail_reason', 'fail_conflict_with'])
            ->map(fn($r) => [
                'value'        => $r->idea_value,
                'reason'       => $r->fail_reason ?? 'UNKNOWN',
                'conflict_with'=> $r->fail_conflict_with,
            ])
            ->all();
    }

    /**
     * Compte les idées PASS disponibles pour un sujet.
     */
    public function countAvailablePassIdeas(int $subjectId): int
    {
        return (int) DB::table(self::TABLE_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'PASS')
            ->where('status', 'AVAILABLE')
            ->count();
    }

    // =========================================================================
    // Mémoire cumulative
    // =========================================================================

    /**
     * Retourne la mémoire cumulative complète pour un contexte donné.
     * Ordonnée par numéro d'appel croissant.
     *
     * @return array<array{attempt: int, candidates: array, pass: array, fail_details: array, covered_directions: array}>
     */
    public function getCumulativeMemory(string $contextType, string $contextKey): array
    {
        $rows = DB::table(self::TABLE_MEMORY)
            ->where('context_type', $contextType)
            ->where('context_key', $contextKey)
            ->orderBy('attempt_number')
            ->get(['attempt_number', 'candidates', 'pass_items', 'fail_items', 'covered_directions'])
            ->all();

        return array_map(function (object $row) {
            return [
                'attempt'            => $row->attempt_number,
                'candidates'         => json_decode($row->candidates, true) ?? [],
                'pass'               => json_decode($row->pass_items, true) ?? [],
                'fail_details'       => json_decode($row->fail_items, true) ?? [],
                'covered_directions' => json_decode($row->covered_directions, true) ?? [],
            ];
        }, $rows);
    }

    /**
     * Persiste une entrée de mémoire cumulative (idempotente par context_type + context_key + attempt_number).
     *
     * @param string[] $candidates      Candidats proposés par Gemini
     * @param string[] $passItems       Valeurs PASS après validation
     * @param array    $failDetails     FAIL avec raisons [{value, reason, conflict_with}]
     * @param string[] $coveredDirections Directions maintenant couvertes
     */
    public function persistMemoryEntry(
        string $contextType,
        string $contextKey,
        int    $attemptNumber,
        array  $candidates,
        array  $passItems,
        array  $failDetails,
        array  $coveredDirections,
        bool   $generationExhausted = false,
    ): void {
        DB::table(self::TABLE_MEMORY)->insertOrIgnore([
            'context_type'        => $contextType,
            'context_key'         => $contextKey,
            'attempt_number'      => $attemptNumber,
            'candidates'          => json_encode($candidates),
            'pass_items'          => json_encode($passItems),
            'fail_items'          => json_encode($failDetails),
            'covered_directions'  => json_encode($coveredDirections),
            'generation_exhausted'=> $generationExhausted,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    /**
     * Retourne le prochain numéro d'appel pour un contexte donné (1-indexed).
     */
    public function getNextAttemptNumber(string $contextType, string $contextKey): int
    {
        $max = DB::table(self::TABLE_MEMORY)
            ->where('context_type', $contextType)
            ->where('context_key', $contextKey)
            ->max('attempt_number');

        return ($max === null) ? 1 : (int) $max + 1;
    }

    /**
     * Retourne le nombre d'appels déjà effectués pour un contexte.
     */
    public function getAttemptCount(string $contextType, string $contextKey): int
    {
        return (int) DB::table(self::TABLE_MEMORY)
            ->where('context_type', $contextType)
            ->where('context_key', $contextKey)
            ->count();
    }

    // =========================================================================
    // Helpers de clés de contexte
    // =========================================================================

    /**
     * Génère la clé de contexte pour la mémoire Sous-domaine.
     */
    public static function subdomainContextKey(int $depth, string $domainCode): string
    {
        return "sd:{$depth}:{$domainCode}";
    }

    /**
     * Génère la clé de contexte pour la mémoire Sujet.
     */
    public static function subjectContextKey(int $depth, string $domainCode, string $subdomainName): string
    {
        return "sub:{$depth}:{$domainCode}:" . md5($subdomainName);
    }

    /**
     * Génère la clé de contexte pour la mémoire Idée.
     */
    public static function ideaContextKey(int $depth, string $domainCode, string $subdomainName, string $subjectName): string
    {
        return "idea:{$depth}:{$domainCode}:" . md5($subdomainName . '|' . $subjectName);
    }
}
