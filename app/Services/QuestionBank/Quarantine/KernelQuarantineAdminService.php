<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;

/**
 * Transactional backend for the Admin quarantine actions.  This is an
 * application service, not an HTTP controller and intentionally has no view
 * concerns.
 */
final class KernelQuarantineAdminService
{
    public function __construct(
        private readonly KernelQuarantineWorkCopyRepository $copies = new KernelQuarantineWorkCopyRepository(),
        private readonly KernelQuarantineWorkCopySlotRepository $copySlots = new KernelQuarantineWorkCopySlotRepository(),
        private readonly KernelQuarantineSlotResumptionRepository $resumptions = new KernelQuarantineSlotResumptionRepository(),
    ) {}

    /**
     * Snapshot the complete seven-slot aggregate before clearing suspect
     * canonical positions.  The returned revision is the canonical revision
     * observed while the snapshot was made.
     *
     * @return array{copy_id:string, copy_version:int, canonical_base_revision:int}
     */
    public function createCopyFromCanonical(string $blueprintId, string $originPhase = 'VALIDATION_PHASE1'): array
    {
        return $this->createCopyFromRequest($blueprintId, $originPhase, null, 'MANUAL_CORRECTION');
    }

    /**
     * Create one durable request and its complete non-canonical copy.
     * Repeating the same idempotency key returns the existing current version.
     *
     * @return array{request_id:?string,copy_id:string,copy_version:int,canonical_base_revision:int}
     */
    public function createCopyFromRequest(
        string $blueprintId,
        string $originPhase = 'VALIDATION_PHASE1',
        ?string $idempotencyKey = null,
        string $causeCode = 'SUSPICION',
        array $causePayload = []
    ): array
    {
        return DB::transaction(function () use ($blueprintId, $originPhase, $idempotencyKey, $causeCode, $causePayload): array {
            $hasRequests = Schema::hasTable('kernel_quarantine_requests');
            $requestKey = $idempotencyKey ?? (string) Str::orderedUuid();
            $requestHash = $this->fingerprint([
                'blueprint_id' => $blueprintId,
                'origin_phase' => $originPhase,
                'cause_code' => $causeCode,
                'cause_payload' => $causePayload,
            ]);
            if ($hasRequests) {
                if (DB::getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$requestKey]);
                }
                $existing = DB::table('kernel_quarantine_requests')
                    ->where('idempotency_key', $requestKey)->lockForUpdate()->first();
                if ($existing !== null) {
                    if ((string) $existing->request_hash !== $requestHash) {
                        throw new LogicException('Rejeu idempotent Quarantaine divergent.');
                    }
                    $copy = $existing->copy_id === null
                        ? null
                        : DB::table('kernel_quarantine_work_copies')
                            ->where('copy_id', $existing->copy_id)->first();
                    if ($copy === null) {
                        throw new LogicException('Requête Quarantaine sans copie courante.');
                    }
                    return [
                        'request_id' => (string) $existing->request_id,
                        'copy_id' => (string) $copy->copy_id,
                        'copy_version' => (int) $copy->copy_version,
                        'canonical_base_revision' => (int) DB::table('kernel_quarantine_work_copy_slots')
                            ->where('copy_id', $copy->copy_id)->max('canonical_base_revision'),
                    ];
                }
            }
            $run = DB::table('kernel_blueprint_runs')->where('blueprint_id', $blueprintId)->lockForUpdate()->first();
            if ($run === null) {
                throw new LogicException("Blueprint canonique introuvable: {$blueprintId}.");
            }
            $slots = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $blueprintId)->lockForUpdate()->get()->keyBy('cognitive_type');
            if ($slots->count() !== 7 || array_diff(KernelBlueprint::COGNITIVE_TYPES, $slots->keys()->all()) !== []) {
                throw new LogicException('Le Blueprint canonique doit posséder exactement sept slots.');
            }
            if (! in_array($causeCode, ['SUSPICION', 'CONTENT_UNTRANSLATABLE', 'MANUAL_CORRECTION'], true)) {
                throw new LogicException('Cause Quarantaine non admissible.');
            }
            $hasContentDefect = $slots->contains(static fn (object $slot): bool =>
                (string) $slot->validation_status === 'SUSPICION'
                || (string) $slot->creation_status === 'EMPTY'
            );
            if ($causeCode === 'SUSPICION' && ! $hasContentDefect) {
                throw new LogicException('Une suspicion de contenu est requise.');
            }
            if ($causeCode === 'CONTENT_UNTRANSLATABLE'
                && (($causePayload['language_code'] ?? null) === null
                    || ($causePayload['evidence'] ?? $causePayload['findings'] ?? null) === null)) {
                throw new LogicException('La preuve de traduction est requise.');
            }
            $baseRevision = 0;
            foreach ($slots as $row) {
                $baseRevision = max($baseRevision, (int) ($row->canonical_revision ?? 0));
            }
            $copyId = (string) Str::orderedUuid();
            $requestId = $hasRequests ? (string) Str::orderedUuid() : null;
            $copyAttributes = [
                'blueprint_id' => $blueprintId,
                'kernel_code' => (string) ($run->kernel_code ?? ''),
                'depth' => $run->depth ?? null,
                'domain_code' => $run->domain_code ?? null,
                'subdomain_active' => $run->subdomain_active ?? null,
                'subject_active' => $run->subject_active ?? null,
                'dominant_idea_active' => $run->dominant_idea_active ?? null,
                'origin_phase' => $originPhase,
            ];
            if ($hasRequests) {
                DB::table('kernel_quarantine_requests')->insert([
                    'request_id' => $requestId,
                    'idempotency_key' => $requestKey,
                    'request_hash' => $requestHash,
                    'blueprint_id' => $blueprintId,
                    'origin_phase' => $originPhase,
                    'cause_code' => $causeCode,
                    'cause_payload' => json_encode($causePayload, JSON_THROW_ON_ERROR),
                    'state' => 'OPEN',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $copyAttributes += [
                    'request_id' => $requestId,
                    'intake_idempotency_key' => $requestKey,
                    'cause_code' => $causeCode,
                    'cause_payload' => json_encode($causePayload, JSON_THROW_ON_ERROR),
                ];
            }
            $this->copies->create($copyAttributes, $copyId);

            foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
                $row = $slots->get($type);
                $this->copySlots->insert($copyId, $type, [
                    'source' => $this->decode($row->source) ?? KernelBlueprint::emptyCognitiveSlotSource($type),
                    'creation_failure' => $this->decode($row->creation_failure),
                    'translations' => $this->decode($row->translations) ?? [],
                    'creation_status' => $row->creation_status,
                    'validation_status' => $row->validation_status,
                    'validation_findings' => $this->decode($row->validation_findings) ?? [],
                    'canonical_base_revision' => (int) ($row->canonical_revision ?? 1),
                    'slot_revision' => 1,
                ]);
            }

            // Only red positions are emptied.  A source skeleton is always
            // generated by the official type, never by an arbitrary object.
            foreach ($slots as $row) {
                if ((string) $row->validation_status !== 'SUSPICION'
                    && (string) $row->creation_status !== 'EMPTY') {
                    continue;
                }
                $values = [
                        'source' => json_encode(KernelBlueprint::emptyCognitiveSlotSource((string) $row->cognitive_type), JSON_THROW_ON_ERROR),
                        'creation_failure' => null,
                        'translations' => '{}',
                        'creation_status' => 'EMPTY',
                        'validation_status' => 'NOT_VALIDATED',
                        'validation_findings' => '[]',
                        'updated_at' => now(),
                    ];
                if (DB::getDriverName() !== 'pgsql'
                    && Schema::hasColumn('kernel_blueprint_cognitive_slots', 'canonical_revision')) {
                    $values['canonical_revision'] = DB::raw('canonical_revision + 1');
                }
                if (DB::getDriverName() === 'pgsql') {
                    $returned = DB::selectOne(
                        'UPDATE kernel_blueprint_cognitive_slots
                         SET source = ?, creation_failure = ?, translations = ?,
                             creation_status = ?, validation_status = ?,
                             validation_findings = ?, updated_at = ?
                         WHERE blueprint_id = ? AND cognitive_type = ?
                         RETURNING canonical_revision',
                        [
                            $values['source'], $values['creation_failure'], $values['translations'],
                            $values['creation_status'], $values['validation_status'],
                            $values['validation_findings'], $values['updated_at'],
                            $blueprintId, $row->cognitive_type,
                        ],
                    );
                    $canonicalRevision = (int) $returned->canonical_revision;
                } else {
                    DB::table('kernel_blueprint_cognitive_slots')
                        ->where('blueprint_id', $blueprintId)
                        ->where('cognitive_type', $row->cognitive_type)
                        ->update($values);
                    $canonicalRevision = (int) DB::table('kernel_blueprint_cognitive_slots')
                        ->where('blueprint_id', $blueprintId)
                        ->where('cognitive_type', $row->cognitive_type)
                        ->value('canonical_revision');
                }
                DB::table('kernel_quarantine_work_copy_slots')
                    ->where('copy_id', $copyId)
                    ->where('cognitive_type', $row->cognitive_type)
                    ->update(['canonical_base_revision' => $canonicalRevision, 'updated_at' => now()]);
            }
            if ($hasRequests) {
                DB::table('kernel_quarantine_requests')->where('request_id', $requestId)->update([
                    'copy_id' => $copyId,
                    'updated_at' => now(),
                ]);
            }
            $this->recordTransition($copyId, null, 'EDITABLE', 1, 'INTAKE', $requestId);
            return [
                'request_id' => $requestId,
                'copy_id' => $copyId,
                'copy_version' => 1,
                'canonical_base_revision' => $baseRevision,
            ];
        });
    }

    /** Alias used by callers which name the action after its domain term. */
    public function quarantine(string $blueprintId, string $originPhase = 'VALIDATION_PHASE1'): array
    {
        return $this->createCopyFromCanonical($blueprintId, $originPhase);
    }

    /**
     * Edit any slot.  Editing invalidates PASS and findings and creates the
     * exact persistent yellow resumption index.
     *
     * @param array<string,mixed> $patch
     * @return array{copy_id:string,copy_version:int,manual_revision:int,canonical_base_revision:int}
     */
    public function editSlot(
        string $copyId,
        string $cognitiveType,
        array $patch,
        int $expectedCopyVersion,
        ?int $expectedManualRevision = null,
        ?int $expectedCanonicalBaseRevision = null,
        ?string $claimToken = null
    ): array {
        return DB::transaction(function () use ($copyId, $cognitiveType, $patch, $expectedCopyVersion, $expectedManualRevision, $expectedCanonicalBaseRevision, $claimToken): array {
            $copy = $this->copies->find($copyId, true);
            if ($copy === null || ! in_array((string) $copy->state, ['EDITABLE', 'READY', 'IN_FLIGHT'], true)
                || (int) $copy->copy_version !== $expectedCopyVersion) {
                throw new LogicException('Référence de copie Quarantaine périmée.');
            }
            if ((string) $copy->state === 'IN_FLIGHT') {
                if ($claimToken === null) {
                    throw new LogicException('Une correction après réclamation exige son claim.');
                }
                $this->copies->assertClaim($copy, $expectedCopyVersion, $claimToken);
            }
            $slot = DB::table('kernel_quarantine_work_copy_slots')
                ->where('copy_id', $copyId)->where('cognitive_type', $cognitiveType)
                ->lockForUpdate()->first();
            if ($slot === null) {
                throw new LogicException("Slot Quarantaine absent: {$cognitiveType}.");
            }
            if ($expectedManualRevision === null) {
                throw new LogicException('La révision manuelle attendue est obligatoire.');
            }
            if ((int) $slot->manual_revision !== $expectedManualRevision) {
                throw new LogicException('Révision manuelle périmée.');
            }
            $canonical = DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $copy->blueprint_id)
                ->where('cognitive_type', $cognitiveType)
                ->lockForUpdate()->first();
            if ($canonical === null) {
                throw new LogicException('Slot canonique absent.');
            }
            $expectedBase = $expectedCanonicalBaseRevision
                ?? (int) $slot->canonical_base_revision;
            if ($expectedCanonicalBaseRevision !== null
                && (int) $slot->canonical_base_revision !== $expectedCanonicalBaseRevision) {
                throw new LogicException('Révision canonique Quarantaine périmée.');
            }
            if ((int) $canonical->canonical_revision !== $expectedBase) {
                throw new LogicException('Révision canonique Quarantaine périmée.');
            }
            $currentSource = $this->decode($slot->source) ?? [];
            if (array_key_exists('source', $patch)) {
                $this->assertCompletePayload($cognitiveType, (array) $patch['source'], false);
            }
            if (isset($patch['source']) && is_array($patch['source'])
                && array_key_exists('correct_answer_key', $currentSource)
                && array_key_exists('correct_answer_key', $patch['source'])
                && $currentSource['correct_answer_key'] !== $patch['source']['correct_answer_key']) {
                throw new LogicException('La clé de réponse canonique est immuable.');
            }
            if (isset($patch['translations']) && is_array($patch['translations'])) {
                $currentTranslations = $this->decode($slot->translations) ?? [];
                foreach ($patch['translations'] as $language => $translation) {
                    if (is_array($translation)) {
                        $this->assertCompletePayload($cognitiveType, $translation, true);
                    }
                    $oldTranslation = $currentTranslations[$language] ?? [];
                    if (is_array($translation) && is_array($oldTranslation)
                        && array_key_exists('correct_answer_key', $translation)
                        && array_key_exists('correct_answer_key', $oldTranslation)
                        && $translation['correct_answer_key'] !== $oldTranslation['correct_answer_key']) {
                        throw new LogicException('La clé de réponse canonique est immuable.');
                    }
                }
            }
            $revision = (int) $slot->manual_revision + 1;
            $newVersion = $this->copies->bumpVersion(
                $copyId,
                $expectedCopyVersion,
                (string) $copy->state === 'IN_FLIGHT',
            );
            $patch['validation_status'] = 'NOT_VALIDATED';
            $patch['validation_findings'] = [];
            $this->copySlots->updateManual($copyId, $cognitiveType, $patch, $expectedCopyVersion, $revision);
            if ((string) $copy->state === 'IN_FLIGHT') {
                DB::table('kernel_quarantine_slot_resumptions')
                    ->where('copy_id', $copyId)
                    ->where('state', 'ACTIVE')
                    ->where('copy_version', $expectedCopyVersion)
                    ->update(['copy_version' => $newVersion, 'updated_at' => now()]);
            }
            $this->resumptions->upsert($copyId, $cognitiveType, $newVersion, $revision);
            if ((string) $copy->state === 'IN_FLIGHT') {
                DB::table('kernel_quarantine_work_copies')->where('copy_id', $copyId)->update([
                    'state' => 'IN_FLIGHT',
                    'claimed_version' => $newVersion,
                    'updated_at' => now(),
                ]);
                DB::table('kernel_current_kernel_route_gate')->where('gate_id', 1)
                    ->where('active_copy_id', $copyId)->update([
                        'active_copy_version' => $newVersion,
                        'updated_at' => now(),
                    ]);
            }
            DB::table('kernel_current_kernel_dispatches')
                ->where('copy_id', $copyId)->whereIn('state', ['READY', 'IN_FLIGHT'])
                ->update(['copy_version' => $newVersion, 'updated_at' => now()]);
            $this->recordTransition($copyId, (string) $copy->state, (string) $copy->state, $newVersion, 'MANUAL_CORRECTION');
            $canonicalRevision = (int) DB::table('kernel_blueprint_cognitive_slots')
                ->where('blueprint_id', $copy->blueprint_id)
                ->where('cognitive_type', $cognitiveType)
                ->value('canonical_revision');
            return [
                'copy_id' => $copyId,
                'copy_version' => $newVersion,
                'manual_revision' => $revision,
                'canonical_base_revision' => $canonicalRevision,
            ];
        });
    }

    /** Enqueue is idempotent per copy and never starts Phase 1. */
    /** @return array{dispatch_id:string,event_id:string} */
    public function enqueue(string $copyId, int $expectedCopyVersion, ?string $eventId = null): array
    {
        return DB::transaction(function () use ($copyId, $expectedCopyVersion, $eventId): array {
            $copy = $this->copies->find($copyId, true);
            if ($copy === null || (int) $copy->copy_version !== $expectedCopyVersion
                || ! in_array((string) $copy->state, ['EDITABLE', 'READY'], true)) {
                throw new LogicException('Version de copie périmée.');
            }
            $existing = DB::table('kernel_current_kernel_dispatches')
                ->where('copy_id', $copyId)->first();
            if ($existing !== null) {
                return ['dispatch_id' => (string) $existing->event_id, 'event_id' => (string) $existing->event_id];
            }
            $eventId ??= (string) Str::orderedUuid();
            $order = DB::getDriverName() === 'pgsql'
                ? (int) DB::selectOne("SELECT nextval('kernel_quarantine_ready_order_seq') AS value")->value
                : (int) DB::table('kernel_quarantine_work_copies')->max('ready_order') + 1;
            $this->copies->setReadyRequest($copyId, $eventId, $order);
            DB::table('kernel_current_kernel_dispatches')->insert([
                'event_id' => $eventId,
                'blueprint_id' => $copy->blueprint_id,
                'direction' => KernelCurrentKernelReceivedRouter::QUARANTINE,
                'copy_id' => $copyId,
                'copy_version' => $expectedCopyVersion,
                'ready_order' => $order,
                'state' => 'READY',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->recordTransition($copyId, (string) $copy->state, 'READY', $expectedCopyVersion, 'ENQUEUE');
            return ['dispatch_id' => $eventId, 'event_id' => $eventId];
        });
    }

    /** Explicit backend naming used by Admin adapters. */
    public function updateSlot(
        string $copyId,
        string $cognitiveType,
        array $patch,
        int $expectedCopyVersion,
        ?int $expectedManualRevision = null,
        ?int $expectedCanonicalBaseRevision = null,
        ?string $claimToken = null
    ): array {
        return $this->editSlot(
            $copyId,
            $cognitiveType,
            $patch,
            $expectedCopyVersion,
            $expectedManualRevision,
            $expectedCanonicalBaseRevision,
            $claimToken,
        );
    }

    /**
     * Prepare a source-English correction without allowing the source identity
     * or canonical answer key to be changed.
     *
     * @return array{copy_id:string,copy_version:int,manual_revision:int,canonical_base_revision:int}
     */
    public function prepareSourceCorrection(
        string $copyId,
        string $cognitiveType,
        array $source,
        int $expectedCopyVersion,
        ?int $expectedManualRevision = null,
        ?int $expectedCanonicalBaseRevision = null,
        ?string $claimToken = null
    ): array {
        $current = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $cognitiveType)->first();
        if ($current === null) {
            throw new LogicException('Slot Quarantaine absent.');
        }
        $old = $this->decode($current->source) ?? [];
        $expectedManualRevision ??= (int) $current->manual_revision;
        $this->assertCompletePayload($cognitiveType, $source, false);
        if (array_key_exists('correct_answer_key', $source)
            && array_key_exists('correct_answer_key', $old)
            && $source['correct_answer_key'] !== $old['correct_answer_key']) {
            throw new LogicException('La clé de réponse canonique est immuable.');
        }
        $translations = $this->decode($current->translations) ?? [];
        foreach ($translations as $language => &$value) {
            if (is_array($value)) {
                $value['source_revision'] = $value['source_revision']
                    ?? (property_exists($current, 'source_revision')
                        ? (string) $current->source_revision
                        : $this->sourceRevision($old));
                $value['validation_status'] = 'STALE';
            }
        }
        unset($value);
        return $this->editSlot(
            $copyId,
            $cognitiveType,
            ['source' => $source, 'translations' => $translations],
            $expectedCopyVersion,
            $expectedManualRevision,
            $expectedCanonicalBaseRevision,
            $claimToken,
        );
    }

    /**
     * Prepare one translation only.  Other languages and other slots are not
     * touched; the owner phase remains responsible for actual translation.
     *
     * @return array{copy_id:string,copy_version:int,manual_revision:int,canonical_base_revision:int}
     */
    public function prepareTranslationCorrection(
        string $copyId,
        string $cognitiveType,
        string $languageCode,
        array $translation,
        int $expectedCopyVersion,
        ?int $expectedManualRevision = null,
        ?int $expectedCanonicalBaseRevision = null,
        ?string $claimToken = null
    ): array {
        if (! in_array($languageCode, ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'], true)) {
            throw new LogicException('Langue de traduction non officielle.');
        }
        $slot = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $cognitiveType)->first();
        if ($slot === null) {
            throw new LogicException('Slot Quarantaine absent.');
        }
        $translations = $this->decode($slot->translations) ?? [];
        $existing = $translations[$languageCode] ?? [];
        $expectedManualRevision ??= (int) $slot->manual_revision;
        $this->assertCompletePayload($cognitiveType, $translation, true);
        $source = $this->decode($slot->source) ?? [];
        if (($source['correct_answer_key'] ?? null) !== ($translation['correct_answer_key'] ?? null)) {
            throw new LogicException('La clé de réponse canonique est immuable.');
        }
        unset($translation['translation_revision'], $translation['validation_status'], $translation['source_revision']);
        if (is_array($existing)
            && array_key_exists('correct_answer_key', $translation)
            && array_key_exists('correct_answer_key', $existing)
            && $translation['correct_answer_key'] !== $existing['correct_answer_key']) {
            throw new LogicException('La clé de réponse canonique est immuable.');
        }
        $translations[$languageCode] = $translation + [
            'translation_revision' => (int) (($existing['translation_revision'] ?? 0)) + 1,
            'source_revision' => $this->slotSourceRevision($slot),
            'validation_status' => 'NOT_VALIDATED',
        ];
        return $this->editSlot(
            $copyId,
            $cognitiveType,
            ['translations' => $translations],
            $expectedCopyVersion,
            $expectedManualRevision,
            $expectedCanonicalBaseRevision,
            $claimToken,
        );
    }

    /** Create a targeted owner-phase operation, idempotently. */
    public function requestRegeneration(
        string $copyId,
        int $expectedCopyVersion,
        string $ownerPhase,
        string $operation,
        ?string $cognitiveType = null,
        ?string $languageCode = null,
        ?string $idempotencyKey = null,
        array $payload = [],
        ?string $claimToken = null,
        ?string $expectedSourceRevision = null,
        ?int $expectedTranslationRevision = null,
        ?int $expectedManualRevision = null,
        ?int $expectedYellowRevision = null
    ): array {
        return DB::transaction(function () use (
            $copyId, $expectedCopyVersion, $ownerPhase, $operation,
            $cognitiveType, $languageCode, $idempotencyKey, $payload, $claimToken,
            $expectedSourceRevision, $expectedTranslationRevision, $expectedManualRevision,
            $expectedYellowRevision
        ): array {
            $copy = $this->copies->find($copyId, true);
            if ($copy === null || (int) $copy->copy_version !== $expectedCopyVersion
                || ! in_array((string) $copy->state, ['EDITABLE', 'READY', 'IN_FLIGHT'], true)) {
                throw new LogicException('Version de copie périmée.');
            }
            if ((string) $copy->state === 'IN_FLIGHT') {
                if ($claimToken === null) {
                    throw new LogicException('Un retour en cours exige son claim.');
                }
                $this->copies->assertClaim($copy, $expectedCopyVersion, $claimToken);
            }
            if (! Schema::hasTable('kernel_quarantine_resume_intents')) {
                throw new LogicException('Migration Quarantaine backend absente.');
            }
            if (! in_array($ownerPhase, ['PHASE1','VALIDATION_PHASE1','PHASE2','VALIDATION_PHASE2'], true)
                || ! in_array($operation, ['RESUME_PHASE1','RESUME_VALIDATION_PHASE1','RESUME_PHASE2','RESUME_VALIDATION_PHASE2','REGENERATE_SOURCE','REGENERATE_TRANSLATION'], true)) {
                throw new LogicException('Référence owner-phase Quarantaine non admissible.');
            }
            $key = $idempotencyKey ?? (string) Str::orderedUuid();
            $intentHash = $this->fingerprint([
                'copy_id' => $copyId, 'copy_version' => $expectedCopyVersion,
                'owner_phase' => $ownerPhase, 'operation' => $operation,
                'cognitive_type' => $cognitiveType, 'language_code' => $languageCode,
                'payload' => $payload, 'source_revision' => $expectedSourceRevision,
                'translation_revision' => $expectedTranslationRevision,
                'manual_revision' => $expectedManualRevision, 'yellow_revision' => $expectedYellowRevision,
            ]);
            $existing = DB::table('kernel_quarantine_resume_intents')
                ->where('idempotency_key', $key)->first();
            if ($existing !== null) {
                if ((string) $existing->intent_hash !== $intentHash) {
                    throw new LogicException('Rejeu régénération Quarantaine divergent.');
                }
                return ['intent_id' => (string) $existing->intent_id, 'copy_version' => (int) $existing->copy_version];
            }
            $intentId = (string) Str::orderedUuid();
            DB::table('kernel_quarantine_resume_intents')->insert([
                'intent_id' => $intentId,
                'idempotency_key' => $key,
                'intent_hash' => $intentHash,
                'copy_id' => $copyId,
                'copy_version' => $expectedCopyVersion,
                'owner_phase' => $ownerPhase,
                'operation' => $operation,
                'cognitive_type' => $cognitiveType,
                'language_code' => $languageCode,
                'state' => 'OPEN',
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
                'expected_source_revision' => $expectedSourceRevision,
                'expected_translation_revision' => $expectedTranslationRevision,
                'expected_manual_revision' => $expectedManualRevision,
                'expected_yellow_revision' => $expectedYellowRevision,
            ]);
            return ['intent_id' => $intentId, 'copy_version' => $expectedCopyVersion];
        });
    }

    /** Return only durable references to the owner phase, never content. */
    public function returnToOwner(
        string $copyId,
        int $copyVersion,
        string $claimToken,
        string $ownerPhase,
        string $operation = 'RESUME_PHASE1',
        ?string $idempotencyKey = null
    ): array {
        return DB::transaction(function () use ($copyId, $copyVersion, $claimToken, $ownerPhase, $operation, $idempotencyKey): array {
        if ($claimToken === null) {
            $claimToken = (string) (DB::table('kernel_current_kernel_route_gate')
                ->where('gate_id', 1)->where('active_copy_id', $copyId)->value('active_claim_token') ?? '');
        }
        if ($claimToken === '') {
            throw new LogicException('Un retour en cours exige son claim.');
        }
        $intent = $this->requestRegeneration(
            $copyId,
            $copyVersion,
            $ownerPhase,
            $operation,
            null,
            null,
            $idempotencyKey,
            [],
            $claimToken,
        );
        $this->recordTransition($copyId, 'IN_FLIGHT', 'IN_FLIGHT', $copyVersion, 'RETURN_OWNER');
        return [
            'intent_id' => $intent['intent_id'],
            'blueprint_id' => (string) $this->copies->find($copyId)->blueprint_id,
            'copy_id' => $copyId,
            'copy_version' => $copyVersion,
            'owner_phase' => $ownerPhase,
            'operation' => $operation,
        ];
        });
    }

    private function recordTransition(
        string $copyId,
        ?string $from,
        string $to,
        int $version,
        string $reason,
        ?string $requestId = null
    ): void {
        if (! Schema::hasTable('kernel_quarantine_transition_history')) {
            return;
        }
        DB::table('kernel_quarantine_transition_history')->insert([
            'transition_id' => (string) Str::orderedUuid(),
            'request_id' => $requestId,
            'copy_id' => $copyId,
            'from_state' => $from,
            'to_state' => $to,
            'copy_version' => $version,
            'reason_code' => $reason,
            'payload' => null,
            'created_at' => now(),
        ]);
    }

    private function decode(mixed $value): ?array
    {
        if ($value === null || is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function assertCompletePayload(string $type, array $payload, bool $translation): void
    {
        $required = ['schema_version', 'question', 'choices', 'correct_answer_key'];
        if (! $translation) {
            $required[] = 'source_language';
            $required[] = 'cognitive_type';
        }
        foreach ($required as $key) {
            if (! array_key_exists($key, $payload) || $payload[$key] === null) {
                throw new LogicException('Payload Quarantaine incomplet.');
            }
        }
        if (! $translation && ($payload['source_language'] !== 'en' || $payload['cognitive_type'] !== $type)) {
            throw new LogicException('Identité source Quarantaine invalide.');
        }
        $isQcm = str_starts_with($type, 'QCM_');
        $expectedChoices = $isQcm ? ['a', 'b', 'c', 'd'] : ['a', 'b'];
        if (! is_array($payload['choices'])
            || array_keys($payload['choices']) !== $expectedChoices
            || in_array(null, $payload['choices'], true)
            || ! is_string($payload['question'])
            || $payload['question'] === '') {
            throw new LogicException('Schéma cognitif Quarantaine invalide.');
        }
        $expectedAnswer = str_ends_with($type, '_FALSE') ? 'b' : 'a';
        if (! in_array($payload['correct_answer_key'], $expectedChoices, true)
            || (! $isQcm && $payload['correct_answer_key'] !== $expectedAnswer)) {
            throw new LogicException('Polarité vrai/faux Quarantaine invalide.');
        }
    }

    private function slotSourceRevision(object $slot): string
    {
        if (property_exists($slot, 'source_revision') && $slot->source_revision !== null) {
            return (string) $slot->source_revision;
        }
        return $this->sourceRevision($this->decode($slot->source) ?? []);
    }

    private function sourceRevision(array $source): string
    {
        return hash('sha256', json_encode([
            'question' => $source['question'] ?? null,
            'choices' => $source['choices'] ?? null,
            'correct_answer_key' => $source['correct_answer_key'] ?? null,
            'sv' => $source['sv'] ?? null,
        ], JSON_THROW_ON_ERROR));
    }

    private function canonicalJson(mixed $value): string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                return '[' . implode(',', array_map(fn ($v) => $this->canonicalJson($v), $value)) . ']';
            }
            ksort($value);
            $parts = [];
            foreach ($value as $key => $item) {
                $parts[] = json_encode((string) $key) . ':' . $this->canonicalJson($item);
            }
            return '{' . implode(',', $parts) . '}';
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function fingerprint(array $value): string
    {
        return hash('sha256', $this->canonicalJson($value));
    }
}