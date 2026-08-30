<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionBank\KernelBlueprint;

final class KernelPhase1SourceValidator
{
    public const SCHEMA_VERSION = 'phase1.source.v1';
    public const SOURCE_LANGUAGE = 'fr';
    private const READING_SPEED_WPM = 150;
    private const REQUIRED_SELF_CHECKS = [
        'question_readable_under_8_seconds',
        'sv_readable_under_30_seconds',
        'correct_answer_explained_by_sv',
        'cognitive_type_respected',
        'one_correct_answer_only',
        'choices_are_plausible',
        'distinct_from_other_slots',
        'same_subject_and_dominant_idea',
        'question_answer_choices_sv_coherent_with_subdomain',
    ];

    /**
     * @return array{
     *   valid: array<string, array<string, mixed>>,
     *   invalid: array<string, string>
     * }
     */
    public function validate(KernelBlueprint $blueprint, array $payload): array
    {
        $this->assertEnvelopeIdentity($blueprint, $payload);

        $rawSlots = $payload['slots'] ?? null;
        if (! is_array($rawSlots) || count($rawSlots) !== count(KernelBlueprint::COGNITIVE_TYPES)) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                'La réponse Phase 1 doit contenir exactement sept slots.'
            );
        }

        $indexed = [];
        foreach ($rawSlots as $rawSlot) {
            if (! is_array($rawSlot) || ! is_string($rawSlot['cognitive_type'] ?? null)) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    'Chaque slot doit déclarer un cognitive_type.'
                );
            }

            $type = $rawSlot['cognitive_type'];
            if (isset($indexed[$type])) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    "Type cognitif dupliqué: {$type}."
                );
            }

            $indexed[$type] = $rawSlot;
        }

        $actualTypes = array_keys($indexed);
        sort($actualTypes);
        $expectedTypes = KernelBlueprint::COGNITIVE_TYPES;
        sort($expectedTypes);
        if ($actualTypes !== $expectedTypes) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                'Les sept types cognitifs ne correspondent pas au contrat Phase 1.'
            );
        }

        $valid = [];
        $invalid = [];
        foreach (KernelBlueprint::COGNITIVE_TYPES as $cognitiveType) {
            try {
                $valid[$cognitiveType] = $this->validateSlot(
                    $indexed[$cognitiveType],
                    $cognitiveType,
                    (string) $payload['source_language']
                );
            } catch (Phase1TechnicalException $exception) {
                $invalid[$cognitiveType] = $exception->getMessage();
            }
        }

        return ['valid' => $valid, 'invalid' => $invalid];
    }

    private function assertEnvelopeIdentity(KernelBlueprint $blueprint, array $payload): void
    {
        if (($payload['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            throw new Phase1TechnicalException('INVALID_SCHEMA', 'schema_version Phase 1 invalide.');
        }

        if (($payload['blueprint_id'] ?? null) !== $blueprint->blueprint_id
            || ($payload['kernel_code'] ?? null) !== $blueprint->kernel_code) {
            throw new Phase1TechnicalException(
                'IDENTITY_MISMATCH',
                'L’identité répétée par Phase 1 diverge du KernelBlueprint.'
            );
        }

        if (($payload['source_language'] ?? null) !== self::SOURCE_LANGUAGE) {
            throw new Phase1TechnicalException(
                'IDENTITY_MISMATCH',
                'La langue source répétée par Phase 1 est invalide.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateSlot(
        array $slot,
        string $cognitiveType,
        string $sourceLanguage
    ): array {
        $question = $this->requiredString($slot, 'question', $cognitiveType);
        $sv = $this->requiredString($slot, 'sv', $cognitiveType);
        $choices = $this->normalizeChoices($slot['choices'] ?? null, $cognitiveType);
        $correctAnswerKey = strtolower($this->requiredString(
            $slot,
            'correct_answer_key',
            $cognitiveType
        ));
        $evidence = $slot['creation_evidence'] ?? null;

        if (! is_array($evidence)) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.creation_evidence doit être un objet."
            );
        }

        foreach ([
            'cognitive_operation',
            'cognitive_justification',
            'difference_from_other_slots',
            'truth_basis',
            'self_checks',
        ] as $field) {
            if (! array_key_exists($field, $evidence)
                || ($field !== 'self_checks' && trim((string) $evidence[$field]) === '')) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    "{$cognitiveType}.creation_evidence.{$field} est obligatoire."
                );
            }
        }

        if (! is_array($evidence['self_checks'])) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.creation_evidence.self_checks doit être un objet."
            );
        }

        $actualChecks = array_keys($evidence['self_checks']);
        sort($actualChecks);
        $requiredChecks = self::REQUIRED_SELF_CHECKS;
        sort($requiredChecks);
        if ($actualChecks !== $requiredChecks) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.creation_evidence.self_checks doit contenir les neuf contrôles officiels."
            );
        }

        foreach (self::REQUIRED_SELF_CHECKS as $check) {
            if ($evidence['self_checks'][$check] !== true) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    "{$cognitiveType}.creation_evidence.self_checks.{$check} doit être true."
                );
            }
        }

        if ($cognitiveType === 'QCM_TRAP') {
            if (! is_string($evidence['trap_basis'] ?? null)
                || trim($evidence['trap_basis']) === '') {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    'QCM_TRAP.creation_evidence.trap_basis est obligatoire.'
                );
            }
        } elseif (($evidence['trap_basis'] ?? null) !== null) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.creation_evidence.trap_basis doit être null."
            );
        }

        $isQcm = str_starts_with($cognitiveType, 'QCM_');
        if ($isQcm) {
            $this->assertQcmChoices($choices, $correctAnswerKey, $cognitiveType);
        } else {
            $this->assertTrueFalseChoices($choices, $correctAnswerKey, $cognitiveType);
        }

        if ($this->estimatedSeconds($question) > 8.0) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.question dépasse huit secondes de lecture."
            );
        }

        if ($this->estimatedSeconds($sv) > 30.0) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.sv dépasse trente secondes de lecture."
            );
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'source_language' => $sourceLanguage,
            'cognitive_type' => $cognitiveType,
            'question' => $question,
            'choices' => $choices,
            'correct_answer_key' => $correctAnswerKey,
            'sv' => $sv,
            'creation_evidence' => $evidence,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function normalizeChoices(mixed $rawChoices, string $cognitiveType): array
    {
        if (! is_array($rawChoices)) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType}.choices doit être un tableau."
            );
        }

        $choices = [];
        foreach ($rawChoices as $key => $choice) {
            if (is_array($choice)) {
                $choiceKey = strtolower(trim((string) ($choice['key'] ?? '')));
                $choiceText = trim((string) ($choice['text'] ?? ''));
            } else {
                $choiceKey = strtolower(trim((string) $key));
                $choiceText = trim((string) $choice);
            }

            if ($choiceKey === '' || $choiceText === '' || isset($choices[$choiceKey])) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    "{$cognitiveType}.choices contient une clé ou un texte invalide."
                );
            }

            $choices[$choiceKey] = $choiceText;
        }

        return $choices;
    }

    /**
     * @param array<string, string> $choices
     */
    private function assertQcmChoices(
        array $choices,
        string $correctAnswerKey,
        string $cognitiveType
    ): void {
        if (array_keys($choices) !== ['a', 'b', 'c', 'd'] || $correctAnswerKey !== 'a') {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType} exige choices.a/b/c/d et correct_answer_key=a."
            );
        }

        $normalized = array_map(
            static fn(string $choice): string => mb_strtolower(trim($choice)),
            array_values($choices)
        );
        if (count(array_unique($normalized)) !== 4) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType} contient des choix dupliqués."
            );
        }

        $wordCounts = [];
        foreach ($choices as $choice) {
            $wordCount = $this->wordCount($choice);
            $wordCounts[] = $wordCount;
            if ($wordCount > 6 || mb_strlen($choice) > 64 || preg_match('/[;,\n]/u', $choice)) {
                throw new Phase1TechnicalException(
                    'INVALID_SCHEMA',
                    "{$cognitiveType} contient un choix qui n’est pas une unité courte."
                );
            }
        }

        if (max($wordCounts) - min($wordCounts) > 3) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType} contient des choix de longueur non comparable."
            );
        }
    }

    /**
     * @param array<string, string> $choices
     */
    private function assertTrueFalseChoices(
        array $choices,
        string $correctAnswerKey,
        string $cognitiveType
    ): void {
        if ($choices !== ['a' => 'VRAI', 'b' => 'FAUX']) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType} exige exactement VRAI et FAUX."
            );
        }

        $expectedKey = str_ends_with($cognitiveType, '_TRUE') ? 'a' : 'b';
        if ($correctAnswerKey !== $expectedKey) {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$cognitiveType} ne respecte pas sa polarité Vrai/Faux."
            );
        }
    }

    private function requiredString(array $payload, string $field, string $context): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new Phase1TechnicalException(
                'INVALID_SCHEMA',
                "{$context}.{$field} est obligatoire."
            );
        }

        return trim($value);
    }

    private function estimatedSeconds(string $text): float
    {
        return ($this->wordCount($text) / self::READING_SPEED_WPM) * 60;
    }

    private function wordCount(string $text): int
    {
        $words = preg_split('/[\s\p{Z}]+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return is_array($words) ? count($words) : 0;
    }
}