<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Quarantine;

use App\Services\QuestionBank\KernelBlueprint;
use Illuminate\Support\Facades\DB;
use LogicException;

final class KernelQuarantineWorkCopySlotRepository
{
    public const TABLE = 'kernel_quarantine_work_copy_slots';

    /** @return array<string,array<string,mixed>> */
    public function allForCopy(string $copyId, bool $lock = false): array
    {
        $query = DB::table(self::TABLE)->where('copy_id', $copyId)->orderBy('cognitive_type');
        if ($lock) {
            $query->lockForUpdate();
        }
        $result = [];
        foreach ($query->get() as $row) {
            $result[(string) $row->cognitive_type] = $this->map($row);
        }
        return $result;
    }

    public function assertExactlySeven(string $copyId): void
    {
        $rows = DB::table(self::TABLE)->where('copy_id', $copyId)
            ->pluck('cognitive_type')->all();
        if (count($rows) !== 7
            || array_diff(KernelBlueprint::COGNITIVE_TYPES, array_map('strval', $rows)) !== []) {
            throw new LogicException('Une copie Quarantaine doit contenir exactement sept types cognitifs.');
        }
    }

    public function insert(string $copyId, string $type, array $slot): void
    {
        $this->assertType($type);
        $source = $slot['source'] ?? KernelBlueprint::emptyCognitiveSlotSource($type);
        $sourceRevision = $this->sourceRevision($source);
        $translations = $slot['translations'] ?? [];
        foreach ($translations as &$translation) {
            if (is_array($translation)) {
                $translation['source_revision'] ??= $sourceRevision;
            }
        }
        unset($translation);
        $values = [
            'copy_id' => $copyId,
            'cognitive_type' => $type,
            'canonical_base_revision' => (int) ($slot['canonical_base_revision'] ?? 1),
            'slot_revision' => (int) ($slot['slot_revision'] ?? 1),
            'manual_revision' => (int) ($slot['manual_revision'] ?? 0),
            'color' => $this->color($slot),
            'source' => $this->json($source),
            'creation_failure' => isset($slot['creation_failure']) ? $this->json($slot['creation_failure']) : null,
            'translations' => $this->json($translations),
            'creation_status' => $slot['creation_status'] ?? 'EMPTY',
            'validation_status' => $slot['validation_status'] ?? 'NOT_VALIDATED',
            'validation_findings' => $this->json($slot['validation_findings'] ?? []),
            'manually_modified' => (bool) ($slot['manually_modified'] ?? false),
            'manual_revision' => (int) ($slot['manual_revision'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if (\Illuminate\Support\Facades\Schema::hasColumn(self::TABLE, 'source_revision')) {
            $values['source_revision'] = (string) ($slot['source_revision'] ?? $sourceRevision);
        }
        DB::table(self::TABLE)->insert($values);
    }

    /** @param array<string,mixed> $patch */
    public function updateManual(
        string $copyId,
        string $type,
        array $patch,
        int $expectedCopyVersion,
        int $manualRevision
    ): void {
        $this->assertType($type);
        $values = [];
        foreach (['source', 'creation_failure', 'translations', 'validation_findings'] as $key) {
            if (array_key_exists($key, $patch)) {
                $values[$key] = $patch[$key] === null ? null : $this->json($patch[$key]);
            }
        }
        foreach (['creation_status', 'validation_status'] as $key) {
            if (array_key_exists($key, $patch)) {
                $values[$key] = $patch[$key];
            }
        }
        if (array_key_exists('source', $patch)
            && \Illuminate\Support\Facades\Schema::hasColumn(self::TABLE, 'source_revision')) {
            $values['source_revision'] = $this->sourceRevision($patch['source']);
        }
        $values += [
            'manually_modified' => true,
            'manual_revision' => $manualRevision,
            'color' => 'YELLOW',
            'slot_revision' => DB::raw('slot_revision + 1'),
            'updated_at' => now(),
        ];
        $updated = DB::table(self::TABLE)->where('copy_id', $copyId)
            ->where('cognitive_type', $type)->update($values);
        if ($updated !== 1) {
            throw new LogicException("Slot Quarantaine absent: {$type}.");
        }
    }

    public function color(array $slot): string
    {
        if (($slot['validation_status'] ?? null) === 'SUSPICION'
            || ($slot['creation_status'] ?? null) === 'EMPTY') {
            return 'RED';
        }
        if (($slot['manually_modified'] ?? false) === true) {
            return 'YELLOW';
        }
        if (($slot['creation_status'] ?? null) === 'CREATED'
            && ($slot['validation_status'] ?? null) === 'PASS') {
            return 'GREEN';
        }
        // NOT_VALIDATED is not red by the DEC-125 colour contract; it is
        // simply not yet terminal and must not be exposed as playable.
        return 'YELLOW';
    }

    /** @return array<string,mixed> */
    private function map(object $row): array
    {
        $decode = static function (mixed $value): mixed {
            if ($value === null || is_array($value)) {
                return $value;
            }
            $decoded = json_decode((string) $value, true);
            return is_array($decoded) ? $decoded : null;
        };
        return [
            'cognitive_type' => (string) $row->cognitive_type,
            'source' => $decode($row->source) ?? [],
            'source_revision' => property_exists($row, 'source_revision') ? $row->source_revision : null,
            'creation_failure' => $decode($row->creation_failure),
            'translations' => $decode($row->translations) ?? [],
            'creation_status' => (string) $row->creation_status,
            'validation_status' => (string) $row->validation_status,
            'validation_findings' => $decode($row->validation_findings) ?? [],
            'manually_modified' => (bool) $row->manually_modified,
            'manual_revision' => (int) $row->manual_revision,
            'canonical_base_revision' => (int) $row->canonical_base_revision,
            'slot_revision' => (int) $row->slot_revision,
            'color' => (string) $row->color,
        ];
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, KernelBlueprint::COGNITIVE_TYPES, true)) {
            throw new LogicException("Type cognitif non officiel: {$type}.");
        }
    }

    private function json(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
}