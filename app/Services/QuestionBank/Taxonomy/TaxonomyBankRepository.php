<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use Illuminate\Support\Facades\DB;

/**
 * TaxonomyBankRepository — accès DB exclusif pour la couche Taxonomy.
 *
 * Couvre les tables Taxonomy v1.1 :
 *   - taxonomy_v11_occurrences
 *   - taxonomy_v11_subdomains
 *   - taxonomy_v11_subjects
 *   - taxonomy_v11_ideas
 *   - taxonomy_v11_generation_memory
 *   - taxonomy_v11_terminal_facts
 *   - taxonomy_v11_blueprint_assignments
 *
 * Idempotence : toutes les insertions sont protégées par insertOrIgnore()
 * ou par firstOrCreate équivalent sur l'identité métier.
 *
 * NE PAS y mettre de logique métier — c'est une couche d'accès aux données.
 */
final class TaxonomyBankRepository
{
    private const V11_OCCURRENCES  = 'taxonomy_v11_occurrences';
    private const V11_SUBDOMAINS   = 'taxonomy_v11_subdomains';
    private const V11_SUBJECTS     = 'taxonomy_v11_subjects';
    private const V11_IDEAS        = 'taxonomy_v11_ideas';
    private const V11_MEMORY       = 'taxonomy_v11_generation_memory';
    private const V11_FACTS        = 'taxonomy_v11_terminal_facts';
    private const V11_ASSIGNMENTS  = 'taxonomy_v11_blueprint_assignments';

    // =========================================================================
    // Taxonomy v1.1 — occurrences et slots exacts
    // =========================================================================

    /**
     * Retourne la plus récente occurrence non terminale du bassin.
     */
    public function findV11ActiveOccurrence(int $depth, string $domainCode): ?object
    {
        return DB::table(self::V11_OCCURRENCES)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->whereIn('status', ['PREPARING', 'OPEN', 'BLOCKED'])
            ->orderByDesc('id')
            ->first();
    }

    public function countV11Subdomains(int $depth, string $domainCode): int
    {
        return (int) DB::table(self::V11_SUBDOMAINS . ' as subdomain')
            ->join(self::V11_OCCURRENCES . ' as occurrence', 'occurrence.id', '=', 'subdomain.occurrence_id')
            ->where('occurrence.depth', $depth)
            ->where('occurrence.domain_code', $domainCode)
            ->count();
    }

    public function countV11Subjects(int $depth, string $domainCode): int
    {
        return (int) DB::table(self::V11_SUBJECTS . ' as subject')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->join(self::V11_OCCURRENCES . ' as occurrence', 'occurrence.id', '=', 'subdomain.occurrence_id')
            ->where('occurrence.depth', $depth)
            ->where('occurrence.domain_code', $domainCode)
            ->count();
    }

    public function countV11SubjectsWithAvailableIdeas(int $depth, string $domainCode): int
    {
        return (int) DB::table(self::V11_SUBJECTS . ' as subject')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->join(self::V11_OCCURRENCES . ' as occurrence', 'occurrence.id', '=', 'subdomain.occurrence_id')
            ->where('occurrence.depth', $depth)
            ->where('occurrence.domain_code', $domainCode)
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(self::V11_IDEAS . ' as idea')
                    ->whereColumn('idea.subject_id', 'subject.id')
                    ->where('idea.validation_status', 'PASS')
                    ->where('idea.status', 'AVAILABLE');
            })
            ->count();
    }

    /**
     * Ouvre une occurrence propre seulement si aucune occurrence exploitable
     * ou bloquée n'existe déjà pour le même bassin.
     */
    public function findOrCreateV11Occurrence(int $depth, string $domainCode): object
    {
        $existing = $this->findV11ActiveOccurrence($depth, $domainCode);
        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($depth, $domainCode) {
            $lockedExisting = DB::table(self::V11_OCCURRENCES)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->whereIn('status', ['PREPARING', 'OPEN', 'BLOCKED'])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($lockedExisting !== null) {
                return $lockedExisting;
            }

            $ordinal = (int) DB::table(self::V11_OCCURRENCES)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->max('ordinal') + 1;

            DB::table(self::V11_OCCURRENCES)->insertOrIgnore([
                'depth'                            => $depth,
                'domain_code'                      => $domainCode,
                'ordinal'                          => $ordinal,
                'status'                           => 'PREPARING',
                'consecutive_technical_failures'   => 0,
                'last_error'                       => null,
                'created_at'                       => now(),
                'updated_at'                       => now(),
            ]);

            $created = DB::table(self::V11_OCCURRENCES)
                ->where('depth', $depth)
                ->where('domain_code', $domainCode)
                ->where('ordinal', $ordinal)
                ->first();

            if ($created === null) {
                throw new \RuntimeException(
                    "Taxonomy v1.1: occurrence introuvable après création ({$depth}/{$domainCode})."
                );
            }

            return $created;
        });
    }

    public function lockV11Occurrence(int $occurrenceId): ?object
    {
        return DB::table(self::V11_OCCURRENCES)
            ->where('id', $occurrenceId)
            ->lockForUpdate()
            ->first();
    }

    public function findV11Subdomain(int $occurrenceId): ?object
    {
        return DB::table(self::V11_SUBDOMAINS)
            ->where('occurrence_id', $occurrenceId)
            ->first();
    }

    public function createV11Subdomain(int $occurrenceId, string $subdomainName): object
    {
        DB::table(self::V11_SUBDOMAINS)->insertOrIgnore([
            'occurrence_id'  => $occurrenceId,
            'subdomain_name' => $subdomainName,
            'status'         => 'ACTIVE',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $subdomain = $this->findV11Subdomain($occurrenceId);
        if ($subdomain === null) {
            throw new \RuntimeException(
                "Taxonomy v1.1: sous-domaine introuvable après création pour occurrence={$occurrenceId}."
            );
        }

        return $subdomain;
    }

    /**
     * @param string[] $subjectNames
     */
    public function createV11Subjects(int $subdomainId, array $subjectNames): void
    {
        $now = now();
        $rows = [];

        foreach (array_slice(array_values(array_unique($subjectNames)), 0, TaxonomyConfig::MAX_SUBJECTS_PER_SUBDOMAIN) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }

            $rows[] = [
                'subdomain_id'              => $subdomainId,
                'subject_name'              => $name,
                'status'                    => 'AVAILABLE',
                'idea_attempt_count'        => 0,
                'idea_generation_exhausted' => false,
                'created_at'                => $now,
                'updated_at'                => $now,
            ];
        }

        if ($rows !== []) {
            DB::table(self::V11_SUBJECTS)->insertOrIgnore($rows);
        }
    }

    public function findV11FirstAvailableIdea(int $occurrenceId): ?object
    {
        return DB::table(self::V11_IDEAS . ' as idea')
            ->join(self::V11_SUBJECTS . ' as subject', 'subject.id', '=', 'idea.subject_id')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->where('subdomain.occurrence_id', $occurrenceId)
            ->where('subject.status', 'AVAILABLE')
            ->where('idea.validation_status', 'PASS')
            ->where('idea.status', 'AVAILABLE')
            ->orderBy('subject.id')
            ->orderBy('idea.id')
            ->select([
                'idea.id as idea_id',
                'idea.idea_value',
                'subject.id as subject_id',
                'subject.subject_name',
                'subdomain.id as subdomain_id',
                'subdomain.subdomain_name',
            ])
            ->first();
    }

    public function claimV11FirstAvailableIdea(int $occurrenceId): ?object
    {
        return DB::table(self::V11_IDEAS . ' as idea')
            ->join(self::V11_SUBJECTS . ' as subject', 'subject.id', '=', 'idea.subject_id')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->where('subdomain.occurrence_id', $occurrenceId)
            ->where('subject.status', 'AVAILABLE')
            ->where('idea.validation_status', 'PASS')
            ->where('idea.status', 'AVAILABLE')
            ->orderBy('subject.id')
            ->orderBy('idea.id')
            ->select([
                'idea.id as idea_id',
                'idea.idea_value',
                'subject.id as subject_id',
                'subject.subject_name',
                'subdomain.id as subdomain_id',
                'subdomain.subdomain_name',
            ])
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return object[] Lignes Subject V11 (id, subject_name, ...) pour un Sous-domaine, triées par id.
     */
    public function getV11SubjectsForSubdomain(int $subdomainId): array
    {
        return DB::table(self::V11_SUBJECTS)
            ->where('subdomain_id', $subdomainId)
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function findV11SubjectNeedingIdeas(int $occurrenceId): ?object
    {
        return DB::table(self::V11_SUBJECTS . ' as subject')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->where('subdomain.occurrence_id', $occurrenceId)
            ->where('subject.status', 'AVAILABLE')
            ->where('subject.idea_generation_exhausted', false)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from(self::V11_IDEAS . ' as idea')
                    ->whereColumn('idea.subject_id', 'subject.id')
                    ->where('idea.validation_status', 'PASS')
                    ->where('idea.status', 'AVAILABLE');
            })
            ->orderBy('subject.id')
            ->select(['subject.*', 'subdomain.subdomain_name', 'subdomain.id as subdomain_id'])
            ->first();
    }

    /**
     * @return string[]
     */
    public function getV11PassIdeaValues(int $subjectId): array
    {
        return DB::table(self::V11_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'PASS')
            ->orderBy('id')
            ->pluck('idea_value')
            ->all();
    }

    /**
     * @return array<array{value: string, reason: string, conflict_with: string|null}>
     */
    public function getV11FailIdeaDetails(int $subjectId): array
    {
        return DB::table(self::V11_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'FAIL')
            ->orderBy('id')
            ->get(['idea_value', 'fail_reason', 'fail_conflict_with'])
            ->map(fn(object $row) => [
                'value'         => $row->idea_value,
                'reason'        => $row->fail_reason ?? 'UNKNOWN',
                'conflict_with' => $row->fail_conflict_with,
            ])
            ->all();
    }

    public function persistV11PassIdea(int $subjectId, string $ideaValue): void
    {
        DB::table(self::V11_IDEAS)->insertOrIgnore([
            'subject_id'         => $subjectId,
            'idea_value'         => $ideaValue,
            'validation_status'  => 'PASS',
            'fail_reason'        => null,
            'fail_conflict_with' => null,
            'status'             => 'AVAILABLE',
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    public function persistV11FailIdea(
        int $subjectId,
        string $ideaValue,
        string $reason,
        ?string $conflictWith = null,
    ): void {
        DB::table(self::V11_IDEAS)->insertOrIgnore([
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

    public function nextV11AttemptNumber(int $occurrenceId, string $contextType, string $contextKey): int
    {
        $max = DB::table(self::V11_MEMORY)
            ->where('occurrence_id', $occurrenceId)
            ->where('context_type', $contextType)
            ->where('context_key', $contextKey)
            ->max('attempt_number');

        return $max === null ? 1 : (int) $max + 1;
    }

    /**
     * @return array<array{attempt: int, candidates: array, pass: array, fail_details: array, covered_directions: array}>
     */
    public function getV11Memory(int $occurrenceId, string $contextType, string $contextKey): array
    {
        return DB::table(self::V11_MEMORY)
            ->where('occurrence_id', $occurrenceId)
            ->where('context_type', $contextType)
            ->where('context_key', $contextKey)
            ->orderBy('attempt_number')
            ->get()
            ->map(fn(object $row) => [
                'attempt'            => $row->attempt_number,
                'candidates'         => json_decode($row->candidates ?? '[]', true) ?: [],
                'pass'               => json_decode($row->pass_items ?? '[]', true) ?: [],
                'fail_details'       => json_decode($row->fail_items ?? '[]', true) ?: [],
                'covered_directions' => json_decode($row->covered_directions ?? '[]', true) ?: [],
            ])
            ->all();
    }

    public function persistV11Memory(
        int $occurrenceId,
        string $contextType,
        string $contextKey,
        int $attemptNumber,
        array $candidates,
        array $passItems,
        array $failItems,
        array $coveredDirections,
        bool $generationExhausted,
    ): void {
        DB::table(self::V11_MEMORY)->insertOrIgnore([
            'occurrence_id'       => $occurrenceId,
            'context_type'        => $contextType,
            'context_key'         => $contextKey,
            'attempt_number'      => $attemptNumber,
            'candidates'          => json_encode($candidates),
            'pass_items'          => json_encode($passItems),
            'fail_items'          => json_encode($failItems),
            'covered_directions'  => json_encode($coveredDirections),
            'generation_exhausted'=> $generationExhausted,
            'created_at'          => now(),
            'updated_at'          => now(),
        ]);
    }

    public function incrementV11SubjectIdeaAttempts(int $subjectId): void
    {
        DB::table(self::V11_SUBJECTS)
            ->where('id', $subjectId)
            ->increment('idea_attempt_count', 1, ['updated_at' => now()]);
    }

    public function markV11SubjectIdeaGenerationExhausted(int $subjectId): void
    {
        DB::table(self::V11_SUBJECTS)
            ->where('id', $subjectId)
            ->update(['idea_generation_exhausted' => true, 'updated_at' => now()]);
    }

    public function markV11IdeaConsumed(int $ideaId): void
    {
        DB::table(self::V11_IDEAS)
            ->where('id', $ideaId)
            ->where('status', 'AVAILABLE')
            ->update(['status' => 'CONSUMED', 'updated_at' => now()]);
    }

    public function markV11SubjectUsedIfNoAvailableIdeas(int $subjectId): void
    {
        $available = DB::table(self::V11_IDEAS)
            ->where('subject_id', $subjectId)
            ->where('validation_status', 'PASS')
            ->where('status', 'AVAILABLE')
            ->exists();

        if (! $available) {
            DB::table(self::V11_SUBJECTS)
                ->where('id', $subjectId)
                ->where('idea_generation_exhausted', true)
                ->update(['status' => 'USED', 'updated_at' => now()]);
        }
    }

    public function remainingV11Ideas(int $occurrenceId): int
    {
        return (int) DB::table(self::V11_IDEAS . ' as idea')
            ->join(self::V11_SUBJECTS . ' as subject', 'subject.id', '=', 'idea.subject_id')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->where('subdomain.occurrence_id', $occurrenceId)
            ->where('idea.validation_status', 'PASS')
            ->where('idea.status', 'AVAILABLE')
            ->count();
    }

    public function remainingV11Subjects(int $occurrenceId): int
    {
        return (int) DB::table(self::V11_SUBJECTS . ' as subject')
            ->join(self::V11_SUBDOMAINS . ' as subdomain', 'subdomain.id', '=', 'subject.subdomain_id')
            ->where('subdomain.occurrence_id', $occurrenceId)
            ->where('subject.status', 'AVAILABLE')
            ->where(function ($query) {
                $query->where('subject.idea_generation_exhausted', false)
                    ->orWhereExists(function ($subquery) {
                        $subquery->select(DB::raw(1))
                            ->from(self::V11_IDEAS . ' as idea')
                            ->whereColumn('idea.subject_id', 'subject.id')
                            ->where('idea.validation_status', 'PASS')
                            ->where('idea.status', 'AVAILABLE');
                    });
            })
            ->count();
    }

    public function markV11OccurrenceExhausted(int $occurrenceId, int $depth, string $domainCode): void
    {
        DB::table(self::V11_OCCURRENCES)
            ->where('id', $occurrenceId)
            ->where('status', 'OPEN')
            ->update([
                'status'       => 'EXHAUSTED',
                'exhausted_at' => now(),
                'updated_at'   => now(),
            ]);

        DB::table(self::V11_SUBDOMAINS)
            ->where('occurrence_id', $occurrenceId)
            ->update(['status' => 'USED', 'updated_at' => now()]);

        DB::table(self::V11_FACTS)->insertOrIgnore([
            'occurrence_id'    => $occurrenceId,
            'fact_id'          => 'taxonomy-v11-occurrence-' . $occurrenceId,
            'depth'            => $depth,
            'domain_code'      => $domainCode,
            'status'           => 'PENDING',
            'delivery_attempts'=> 0,
            'last_error'       => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function findV11BlueprintAssignment(string $blueprintId): ?object
    {
        return DB::table(self::V11_ASSIGNMENTS)
            ->where('blueprint_id', $blueprintId)
            ->first();
    }

    public function createV11BlueprintAssignment(
        string $blueprintId,
        int $occurrenceId,
        object $idea,
        int $depth,
        string $domainCode,
    ): bool {
        return DB::table(self::V11_ASSIGNMENTS)->insertOrIgnore([
            'blueprint_id'          => $blueprintId,
            'occurrence_id'         => $occurrenceId,
            'subdomain_id'          => $idea->subdomain_id,
            'subject_id'            => $idea->subject_id,
            'idea_id'               => $idea->idea_id,
            'depth'                 => $depth,
            'domain_code'           => $domainCode,
            'subdomain_active'      => $idea->subdomain_name,
            'subject_active'        => $idea->subject_name,
            'dominant_idea_active'  => $idea->idea_value,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]) === 1;
    }

    /**
     * @return object[]
     */
    public function pendingV11TerminalFacts(): array
    {
        return DB::table(self::V11_FACTS)
            ->where('status', 'PENDING')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function markV11TerminalFactDelivered(int $factRowId): void
    {
        DB::table(self::V11_FACTS)
            ->where('id', $factRowId)
            ->where('status', 'PENDING')
            ->update([
                'status'            => 'DELIVERED',
                'delivery_attempts' => DB::raw('delivery_attempts + 1'),
                'last_error'        => null,
                'delivered_at'      => now(),
                'updated_at'        => now(),
            ]);
    }

    public function recordV11TerminalDeliveryFailure(int $factRowId, string $error): void
    {
        DB::table(self::V11_FACTS)
            ->where('id', $factRowId)
            ->where('status', 'PENDING')
            ->update([
                'delivery_attempts' => DB::raw('delivery_attempts + 1'),
                'last_error'        => mb_substr($error, 0, 2000),
                'updated_at'        => now(),
            ]);
    }

    public function recordV11TechnicalFailure(int $occurrenceId, string $error): bool
    {
        return DB::transaction(function () use ($occurrenceId, $error) {
            $occurrence = $this->lockV11Occurrence($occurrenceId);
            if ($occurrence === null) {
                return false;
            }

            $count = (int) $occurrence->consecutive_technical_failures + 1;
            $status = $count >= 3 ? 'BLOCKED' : (string) $occurrence->status;

            DB::table(self::V11_OCCURRENCES)
                ->where('id', $occurrenceId)
                ->update([
                    'consecutive_technical_failures' => $count,
                    'status'                         => $status,
                    'last_error'                     => mb_substr($error, 0, 2000),
                    'updated_at'                     => now(),
                ]);

            return $status === 'BLOCKED';
        });
    }

    public function resetV11TechnicalFailures(int $occurrenceId): void
    {
        DB::table(self::V11_OCCURRENCES)
            ->where('id', $occurrenceId)
            ->whereIn('status', ['PREPARING', 'OPEN'])
            ->update([
                'consecutive_technical_failures' => 0,
                'last_error'                     => null,
                'updated_at'                     => now(),
            ]);
    }

    public function markV11OccurrenceOpen(int $occurrenceId): void
    {
        DB::table(self::V11_OCCURRENCES)
            ->where('id', $occurrenceId)
            ->where('status', 'PREPARING')
            ->update([
                'status'     => 'OPEN',
                'updated_at' => now(),
            ]);
    }

    /**
     * LOOKBACK-2 : mémoire persistante et séparée par couple (Depth, Domain).
     *
     * Transmet les deux occurrences EXHAUSTED antérieures les plus récentes du
     * MÊME Depth et du MÊME Domain (filtre Depth jamais retiré), selon l'ordre
     * chronologique réel de production (id croissant = ordre de création).
     *
     * Pour chaque occurrence retenue, transmet au minimum : le Subdomain
     * historique, et pour chaque Subject la paire complète Idées PASS +
     * Idées FAIL (valeur + raison) — nécessaire à l'anti-doublon exact
     * Subject+Idea, jamais un simple pool d'idées mélangées sans Subject.
     *
     * @return array<int, array{
     *     subdomain: string,
     *     subjects: array<int, array{
     *         subject: string,
     *         pass_ideas: string[],
     *         fail_ideas: array<int, array{value: string, reason: string}>,
     *     }>,
     * }>
     */
    public function v11Lookback(int $depth, string $domainCode, int $limit = 2): array
    {
        $occurrences = DB::table(self::V11_OCCURRENCES)
            ->where('depth', $depth)
            ->where('domain_code', $domainCode)
            ->where('status', 'EXHAUSTED')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return $occurrences->map(function (object $occurrence) {
            $subdomain = $this->findV11Subdomain((int) $occurrence->id);
            if ($subdomain === null) {
                return ['subdomain' => '', 'subjects' => []];
            }

            $subjects = DB::table(self::V11_SUBJECTS)
                ->where('subdomain_id', $subdomain->id)
                ->orderBy('id')
                ->get(['id', 'subject_name']);

            $subjectIds = $subjects->pluck('id')->all();

            $ideasBySubject = [];
            if ($subjectIds !== []) {
                $ideas = DB::table(self::V11_IDEAS)
                    ->whereIn('subject_id', $subjectIds)
                    ->whereIn('validation_status', ['PASS', 'FAIL'])
                    ->orderBy('id')
                    ->get(['subject_id', 'idea_value', 'validation_status', 'fail_reason']);

                foreach ($ideas as $idea) {
                    $ideasBySubject[$idea->subject_id]['pass'] ??= [];
                    $ideasBySubject[$idea->subject_id]['fail'] ??= [];

                    if ($idea->validation_status === 'PASS') {
                        $ideasBySubject[$idea->subject_id]['pass'][] = $idea->idea_value;
                    } else {
                        $ideasBySubject[$idea->subject_id]['fail'][] = [
                            'value'  => $idea->idea_value,
                            'reason' => $idea->fail_reason ?? 'UNKNOWN',
                        ];
                    }
                }
            }

            $subjectsPayload = $subjects->map(function (object $subject) use ($ideasBySubject) {
                return [
                    'subject'    => $subject->subject_name,
                    'pass_ideas' => $ideasBySubject[$subject->id]['pass'] ?? [],
                    'fail_ideas' => $ideasBySubject[$subject->id]['fail'] ?? [],
                ];
            })->all();

            return [
                'subdomain' => $subdomain->subdomain_name,
                'subjects'  => $subjectsPayload,
            ];
        })->all();
    }

    /**
     * Retourne les anomalies de préparation v1.1 : au moins un FAIL et aucun PASS.
     *
     * @return array<int, object>
     */
    public function findV11PreparationAnomalies(int $minFails = 1): array
    {
        return DB::table(self::V11_SUBJECTS . ' as s')
            ->join(self::V11_SUBDOMAINS . ' as sd', 'sd.id', '=', 's.subdomain_id')
            ->join(self::V11_OCCURRENCES . ' as o', 'o.id', '=', 'sd.occurrence_id')
            ->leftJoin(self::V11_IDEAS . ' as i', 'i.subject_id', '=', 's.id')
            ->select([
                'o.depth',
                'o.domain_code',
                'sd.subdomain_name',
                's.id as subject_id',
                's.subject_name',
                's.idea_attempt_count',
                'o.status as occurrence_status',
            ])
            ->selectRaw("SUM(CASE WHEN i.validation_status = 'FAIL' THEN 1 ELSE 0 END) as fail_count")
            ->selectRaw("SUM(CASE WHEN i.validation_status = 'PASS' THEN 1 ELSE 0 END) as pass_count")
            ->whereIn('o.status', ['PREPARING', 'BLOCKED'])
            ->groupBy([
                'o.depth',
                'o.domain_code',
                'sd.subdomain_name',
                's.id',
                's.subject_name',
                's.idea_attempt_count',
                'o.status',
            ])
            ->havingRaw("SUM(CASE WHEN i.validation_status = 'PASS' THEN 1 ELSE 0 END) = 0")
            ->havingRaw("SUM(CASE WHEN i.validation_status = 'FAIL' THEN 1 ELSE 0 END) >= ?", [$minFails])
            ->orderByDesc('fail_count')
            ->get()
            ->all();
    }

}
