<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

/**
 * ValidationResult — résultat d'une validation d'Idée Dominante.
 *
 * Immuable. Créé par ValidationDominantIdeas.
 */
final class ValidationResult
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_FAIL = 'FAIL';

    private function __construct(
        public readonly string  $status,
        public readonly ?string $reason = null,
        public readonly ?string $conflictWith = null,
    ) {}

    public static function pass(): self
    {
        return new self(self::STATUS_PASS);
    }

    public static function fail(string $reason, ?string $conflictWith = null): self
    {
        return new self(self::STATUS_FAIL, $reason, $conflictWith);
    }

    public function isPass(): bool
    {
        return $this->status === self::STATUS_PASS;
    }

    public function isFail(): bool
    {
        return $this->status === self::STATUS_FAIL;
    }
}
