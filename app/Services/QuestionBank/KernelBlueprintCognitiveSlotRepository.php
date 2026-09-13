<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

class KernelBlueprintCognitiveSlotRepository
{
    private const TABLE = 'kernel_blueprint_cognitive_slots';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function initializeEmptySlots(string $blueprintId): array
    {
        $now = now();
        $rows = [];

        foreach (KernelBlueprint::COGNITIVE_TYPES as $cognitiveType) {
            $rows[] = [
                'blueprint_id' => $blueprintId,
                'cognitive_type' => $cognitiveType,
                'source' => $this->encodeJson(
                    KernelBlueprint::emptyCognitiveSlotSource($cognitiveType)
                ),
                'creation_failure' => null,
                'translations' => '{}',
                'creation_status' => 'EMPTY',
                'validation_status' => 'NOT_VALIDATED',
                'validation_findings' => '[]',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table(self::TABLE)->insert($rows);

        return $this->allForBlueprint($blueprintId);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function allForBlueprint(string $blueprintId): array
    {
        $rows = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->orderBy('cognitive_type')
            ->get();

        $slots = [];
        foreach ($rows as $row) {
            $slots[(string) $row->cognitive_type] = [
                'cognitive_type' => (string) $row->cognitive_type,
                'source' => $this->decodeJson($row->source),
                'creation_failure' => $this->decodeJson($row->creation_failure),
                'translations' => $this->decodeJson($row->translations) ?? [],
                'creation_status' => (string) $row->creation_status,
                'validation_status' => (string) $row->validation_status,
                'validation_findings' => $this->decodeJson($row->validation_findings) ?? [],
            ];
        }

        return $slots;
    }

    public function writeCreated(
        string $blueprintId,
        string $cognitiveType,
        array $source
    ): void {
        $this->assertOfficialType($cognitiveType);

        $values = [
            'source' => $this->encodeJson($source),
            'creation_failure' => null,
            'creation_status' => 'CREATED',
            'validation_status' => 'NOT_VALIDATED',
            'validation_findings' => '[]',
            'updated_at' => now(),
        ];
        if (DB::getDriverName() !== 'pgsql' && Schema::hasColumn(self::TABLE, 'canonical_revision')) {
            $values['canonical_revision'] = DB::raw('canonical_revision + 1');
        }
        $updated = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $cognitiveType)
            ->where('creation_status', 'EMPTY')
            ->update($values);

        if ($updated === 1) {
            return;
        }

        $existing = $this->find($blueprintId, $cognitiveType);
        if ($existing !== null
            && $existing['creation_status'] === 'CREATED'
            && $existing['source'] === $source) {
            return;
        }

        throw new LogicException(
            "[KernelBlueprintCognitiveSlotRepository] Slot {$cognitiveType} "
            . "absent, terminal ou déjà créé avec une autre source."
        );
    }

    public function writeCreationFailure(
        string $blueprintId,
        string $cognitiveType,
        array $creationFailure
    ): void {
        $this->assertOfficialType($cognitiveType);

        $values = [
            'source' => $this->encodeJson(
                KernelBlueprint::emptyCognitiveSlotSource($cognitiveType)
            ),
            'creation_failure' => $this->encodeJson($creationFailure),
            'creation_status' => 'CREATION_FAILED',
            'validation_status' => 'NOT_VALIDATED',
            'validation_findings' => '[]',
            'updated_at' => now(),
        ];
        if (DB::getDriverName() !== 'pgsql' && Schema::hasColumn(self::TABLE, 'canonical_revision')) {
            $values['canonical_revision'] = DB::raw('canonical_revision + 1');
        }
        $updated = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $cognitiveType)
            ->where('creation_status', 'EMPTY')
            ->update($values);

        if ($updated === 1) {
            return;
        }

        $existing = $this->find($blueprintId, $cognitiveType);
        if ($existing !== null && in_array(
            $existing['creation_status'],
            ['CREATED', 'CREATION_FAILED'],
            true
        )) {
            return;
        }

        throw new LogicException(
            "[KernelBlueprintCognitiveSlotRepository] Slot {$cognitiveType} introuvable."
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $blueprintId, string $cognitiveType): ?array
    {
        $this->assertOfficialType($cognitiveType);

        return $this->allForBlueprint($blueprintId)[$cognitiveType] ?? null;
    }

    /**
     * Persist the terminal ValidationPhase1 decision for all seven slots.
     *
     * This is the only validation writer.  No source, identity, creation
     * metadata or translation column is included in the update projection.
     *
     * @param array<string, array{validation_status: string, validation_findings: array<int, array<string, mixed>>}> $decisions
     */
    public function writeValidationResults(string $blueprintId, array $decisions): void
    {
        $expected = KernelBlueprint::COGNITIVE_TYPES;
        $actual = array_keys($decisions);
        sort($actual);
        $sortedExpected = $expected;
        sort($sortedExpected);
        if ($actual !== $sortedExpected) {
            throw new LogicException(
                '[KernelBlueprintCognitiveSlotRepository] Les sept décisions de validation sont requises.'
            );
        }

        foreach ($expected as $cognitiveType) {
            $this->assertOfficialType($cognitiveType);
            $decision = $decisions[$cognitiveType];
            if (! in_array($decision['validation_status'] ?? null, ['PASS', 'SUSPICION'], true)
                || ! is_array($decision['validation_findings'] ?? null)) {
                throw new LogicException(
                    "[KernelBlueprintCognitiveSlotRepository] Décision de validation invalide pour {$cognitiveType}."
                );
            }
        }

        DB::transaction(function () use ($blueprintId, $decisions, $expected): void {
            $rows = DB::table(self::TABLE)
                ->where('blueprint_id', $blueprintId)
                ->lockForUpdate()
                ->get()
                ->keyBy('cognitive_type');

            if ($rows->count() !== count($expected)
                || array_diff($expected, $rows->keys()->all()) !== []) {
                throw new LogicException(
                    "[KernelBlueprintCognitiveSlotRepository] Blueprint sans sept slots: {$blueprintId}."
                );
            }

            $allExactTerminal = true;
            foreach ($expected as $cognitiveType) {
                $row = $rows->get($cognitiveType);
                $requested = $decisions[$cognitiveType];
                $existingFindings = $this->decodeJson($row->validation_findings) ?? [];
                $isTerminal = in_array((string) $row->validation_status, ['PASS', 'SUSPICION'], true);
                $isExact = $isTerminal
                    && (string) $row->validation_status === $requested['validation_status']
                    && $existingFindings === $requested['validation_findings'];

                if ($isTerminal && ! $isExact) {
                    throw new LogicException(
                        "[KernelBlueprintCognitiveSlotRepository] Replay divergent pour {$cognitiveType}."
                    );
                }
                if (! $isExact) {
                    $allExactTerminal = false;
                }
            }

            // Exact terminal replay is a true no-op, including updated_at.
            if ($allExactTerminal) {
                return;
            }

            foreach ($expected as $cognitiveType) {
                $requested = $decisions[$cognitiveType];
                $this->beforeValidationSlotUpdate($blueprintId, $cognitiveType);
                $values = [
                    'validation_status' => $requested['validation_status'],
                    'validation_findings' => $this->encodeJson($requested['validation_findings']),
                ];
                if (DB::getDriverName() !== 'pgsql' && Schema::hasColumn(self::TABLE, 'canonical_revision')) {
                    $values['canonical_revision'] = DB::raw('canonical_revision + 1');
                }
                $updated = DB::table(self::TABLE)
                    ->where('blueprint_id', $blueprintId)
                    ->where('cognitive_type', $cognitiveType)
                    ->where('validation_status', 'NOT_VALIDATED')
                    ->update($values);
                if ($updated !== 1) {
                    throw new LogicException(
                        "[KernelBlueprintCognitiveSlotRepository] Écriture atomique de validation interrompue."
                    );
                }
            }
        });
    }

    /**
     * Test seam for exercising transaction rollback; production is a no-op.
     */
    protected function beforeValidationSlotUpdate(string $blueprintId, string $cognitiveType): void
    {
    }

    private function assertOfficialType(string $cognitiveType): void
    {
        if (! in_array($cognitiveType, KernelBlueprint::COGNITIVE_TYPES, true)) {
            throw new LogicException(
                "[KernelBlueprintCognitiveSlotRepository] Type cognitif non officiel: {$cognitiveType}."
            );
        }
    }

    private function encodeJson(array $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new LogicException('Impossible d’encoder le payload JSON du slot cognitif.');
        }

        return $encoded;
    }

    private function decodeJson(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }
}