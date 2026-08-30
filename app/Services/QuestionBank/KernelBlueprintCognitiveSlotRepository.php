<?php

declare(strict_types=1);

namespace App\Services\QuestionBank;

use Illuminate\Support\Facades\DB;
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
                'source' => null,
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

        $updated = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $cognitiveType)
            ->where('creation_status', 'EMPTY')
            ->update([
                'source' => $this->encodeJson($source),
                'creation_failure' => null,
                'creation_status' => 'CREATED',
                'validation_status' => 'NOT_VALIDATED',
                'validation_findings' => '[]',
                'updated_at' => now(),
            ]);

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

        $updated = DB::table(self::TABLE)
            ->where('blueprint_id', $blueprintId)
            ->where('cognitive_type', $cognitiveType)
            ->where('creation_status', 'EMPTY')
            ->update([
                'source' => null,
                'creation_failure' => $this->encodeJson($creationFailure),
                'creation_status' => 'CREATION_FAILED',
                'validation_status' => 'NOT_VALIDATED',
                'validation_findings' => '[]',
                'updated_at' => now(),
            ]);

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