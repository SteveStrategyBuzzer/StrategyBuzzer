<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Read-only, presentation-safe projection for the Admin quarantine screens.
 *
 * This class deliberately selects and maps fields instead of returning rows
 * from the quarantine tables. Claim tokens, route-gate tokens, request/intent
 * hashes and dispatch internals must never cross the Admin HTTP boundary.
 */
final class KernelQuarantineAdminReadService
{
    public const LANGUAGES = ['fr', 'es', 'de', 'it', 'pt', 'ru', 'zh', 'ar', 'el'];

    public function queue(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        $query = DB::table('kernel_quarantine_work_copies as copies')
            ->select([
                'copies.copy_id',
                'copies.blueprint_id',
                'copies.kernel_code',
                'copies.origin_phase',
                'copies.cause_code',
                'copies.state',
                'copies.copy_version',
                'copies.ready_order',
                'copies.created_at',
                'copies.updated_at',
            ])
            ->orderByRaw('copies.ready_order IS NULL')
            ->orderBy('copies.ready_order')
            ->orderByDesc('copies.created_at')
            ->orderBy('copies.copy_id');

        if (($filters['state'] ?? '') !== '') {
            $query->where('copies.state', $filters['state']);
        }
        if (($filters['cause'] ?? '') !== '') {
            $query->where('copies.cause_code', $filters['cause']);
        }
        if (($filters['phase'] ?? '') !== '') {
            $query->where('copies.origin_phase', $filters['phase']);
        }
        if (($filters['blueprint'] ?? '') !== '') {
            $query->where('copies.blueprint_id', 'like', '%' . $filters['blueprint'] . '%');
        }
        if (($filters['slot'] ?? '') !== '') {
            $query->whereExists(function ($slots) use ($filters): void {
                $slots->selectRaw('1')
                    ->from('kernel_quarantine_work_copy_slots as filtered_slots')
                    ->whereColumn('filtered_slots.copy_id', 'copies.copy_id')
                    ->where('filtered_slots.cognitive_type', $filters['slot']);
            });
        }
        if (($filters['language'] ?? '') !== '') {
            $query->whereExists(function ($slots) use ($filters): void {
                $slots->selectRaw('1')
                    ->from('kernel_quarantine_work_copy_slots as filtered_slots')
                    ->whereColumn('filtered_slots.copy_id', 'copies.copy_id');
                if (DB::getDriverName() === 'pgsql') {
                    $slots->whereRaw('jsonb_exists(filtered_slots.translations, ?)', [$filters['language']]);
                } else {
                    $slots->whereJsonContains('filtered_slots.translations', [$filters['language'] => []]);
                }
            });
        }

        $paginator = $query->paginate($perPage)->withQueryString();
        $paginator->setCollection($paginator->getCollection()->map(
            fn (object $row): array => $this->mapQueueRow($row),
        ));

        return $paginator;
    }

    public function detail(string $copyId): ?array
    {
        $copy = DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->first();
        if ($copy === null) {
            return null;
        }

        $slots = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->orderBy('cognitive_type')->get()
            ->map(fn (object $slot): array => $this->mapSlot($slot))
            ->values()->all();

        $history = DB::table('kernel_quarantine_transition_history')
            ->where('copy_id', $copyId)
            ->orderByDesc('created_at')->orderByDesc('transition_id')
            ->get(['from_state', 'to_state', 'copy_version', 'reason_code', 'created_at'])
            ->map(fn (object $row): array => [
                'from_state' => $row->from_state,
                'to_state' => $row->to_state,
                'copy_version' => (int) $row->copy_version,
                'reason_code' => $row->reason_code,
                'created_at' => $row->created_at,
            ])->values()->all();

        $resumptions = DB::table('kernel_quarantine_slot_resumptions')
            ->where('copy_id', $copyId)
            ->orderBy('cognitive_type')
            ->get([
                'cognitive_type', 'resumption_number', 'copy_version', 'manual_revision',
                'phase1_remaining', 'phase1_creation_required', 'validation_phase1_remaining',
                'phase2_remaining', 'validation_phase2_remaining', 'current_stage',
                'state', 'outcome', 'updated_at',
            ])
            ->map(fn (object $row): array => [
                'cognitive_type' => $row->cognitive_type,
                'resumption_number' => (int) $row->resumption_number,
                'copy_version' => (int) $row->copy_version,
                'manual_revision' => (int) $row->manual_revision,
                'phase1_remaining' => (bool) $row->phase1_remaining,
                'phase1_creation_required' => (bool) $row->phase1_creation_required,
                'validation_phase1_remaining' => (bool) $row->validation_phase1_remaining,
                'phase2_remaining' => (bool) $row->phase2_remaining,
                'validation_phase2_remaining' => (bool) $row->validation_phase2_remaining,
                'current_stage' => $row->current_stage,
                'state' => $row->state,
                'outcome' => $row->outcome,
                'updated_at' => $row->updated_at,
            ])->values()->all();

        return [
            'copy' => [
                'copy_id' => (string) $copy->copy_id,
                'blueprint_id' => (string) $copy->blueprint_id,
                'kernel_code' => (string) ($copy->kernel_code ?? ''),
                'origin_phase' => (string) $copy->origin_phase,
                'owner_phase' => (string) $copy->origin_phase,
                'cause_code' => (string) ($copy->cause_code ?? ''),
                'cause_payload' => $this->decode($copy->cause_payload ?? null),
                'state' => (string) $copy->state,
                'copy_version' => (int) $copy->copy_version,
                'ready_order' => $copy->ready_order === null ? null : (int) $copy->ready_order,
                'created_at' => $copy->created_at,
                'updated_at' => $copy->updated_at,
            ],
            'slots' => $slots,
            'history' => $history,
            'resumptions' => $resumptions,
            'resume_intents' => DB::table('kernel_quarantine_resume_intents')
                ->where('copy_id', $copyId)
                ->orderByDesc('created_at')
                ->get([
                    'owner_phase', 'operation', 'cognitive_type', 'language_code',
                    'state', 'expected_source_revision', 'expected_translation_revision',
                    'expected_manual_revision', 'expected_yellow_revision', 'created_at',
                ])
                ->map(fn (object $row): array => [
                    'owner_phase' => $row->owner_phase,
                    'operation' => $row->operation,
                    'cognitive_type' => $row->cognitive_type,
                    'language_code' => $row->language_code,
                    'state' => $row->state,
                    'expected_source_revision' => $row->expected_source_revision,
                    'expected_translation_revision' => $row->expected_translation_revision === null ? null : (int) $row->expected_translation_revision,
                    'expected_manual_revision' => $row->expected_manual_revision === null ? null : (int) $row->expected_manual_revision,
                    'expected_yellow_revision' => $row->expected_yellow_revision === null ? null : (int) $row->expected_yellow_revision,
                    'created_at' => $row->created_at,
                ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    private function mapQueueRow(object $row): array
    {
        $slots = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $row->copy_id)
            ->orderBy('cognitive_type')
            ->get([
                'cognitive_type', 'color', 'validation_status', 'creation_status',
                'manual_revision', 'slot_revision', 'translations',
            ]);
        $colors = $slots->pluck('color')->all();
        $color = in_array('RED', $colors, true)
            ? 'RED'
            : (in_array('YELLOW', $colors, true) ? 'YELLOW' : 'GREEN');
        $languages = [];
        foreach ($slots as $slot) {
            $languages = array_merge($languages, $this->availableLanguages($slot->translations));
        }

        return [
            'copy_id' => (string) $row->copy_id,
            'blueprint_id' => (string) $row->blueprint_id,
            'kernel_code' => (string) ($row->kernel_code ?? ''),
            'origin_phase' => (string) $row->origin_phase,
            'owner_phase' => (string) $row->origin_phase,
            'cause_code' => (string) ($row->cause_code ?? ''),
            'state' => (string) $row->state,
            'copy_version' => (int) $row->copy_version,
            'ready_order' => $row->ready_order === null ? null : (int) $row->ready_order,
            'fifo_position' => $row->ready_order === null ? null : (int) $row->ready_order,
            'available_languages' => array_values(array_intersect(self::LANGUAGES, array_unique($languages))),
            'cognitive_type' => $slots->pluck('cognitive_type')->implode(', '),
            'color' => $color,
            'validation_status' => $slots->pluck('validation_status')->unique()->implode(' / '),
            'creation_status' => $slots->pluck('creation_status')->unique()->implode(' / '),
            'manual_revision' => (int) ($slots->max('manual_revision') ?? 0),
            'slot_revision' => (int) ($slots->max('slot_revision') ?? 0),
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /** @return array<string,mixed> */
    private function mapSlot(object $row): array
    {
        $translations = $this->decode($row->translations) ?? [];
        $safeTranslations = [];
        foreach (self::LANGUAGES as $language) {
            $safeTranslations[$language] = is_array($translations[$language] ?? null)
                ? $translations[$language]
                : null;
        }

        return [
            'cognitive_type' => (string) $row->cognitive_type,
            'source' => $this->decode($row->source) ?? [],
            'source_revision' => property_exists($row, 'source_revision') ? $row->source_revision : null,
            'translations' => $safeTranslations,
            'creation_failure' => $this->decode($row->creation_failure),
            'creation_status' => (string) $row->creation_status,
            'validation_status' => (string) $row->validation_status,
            'validation_findings' => $this->decode($row->validation_findings) ?? [],
            'manually_modified' => (bool) $row->manually_modified,
            'manual_revision' => (int) $row->manual_revision,
            'canonical_base_revision' => (int) $row->canonical_base_revision,
            'slot_revision' => (int) $row->slot_revision,
            'color' => (string) $row->color,
        ];
    }

    private function decode(mixed $value): mixed
    {
        if ($value === null || is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        return $this->redact($decoded);
    }

    /** @return list<string> */
    private function availableLanguages(mixed $translations): array
    {
        $decoded = $this->decode($translations);

        return array_values(array_intersect(
            self::LANGUAGES,
            array_keys(is_array($decoded) ? $decoded : []),
        ));
    }

    private function redact(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $blocked = [
            'claim_token', 'active_claim_token', 'request_hash', 'intent_hash',
            'idempotency_key', 'claimed_event_id', 'event_id', 'dispatch_id',
            'claimed_at', 'claim_expires_at', 'claimed_version',
        ];
        $safe = [];
        foreach ($value as $key => $item) {
            if (in_array((string) $key, $blocked, true)) {
                continue;
            }
            $safe[$key] = $this->redact($item);
        }
        return $safe;
    }
}