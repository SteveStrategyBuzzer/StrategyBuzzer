<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\DTO;

final class LearningDirectionResult
{
    public const STATUS_PASS            = 'pass';
    public const STATUS_FAIL            = 'fail';
    public const STATUS_REVIEW_STRUCTURE = 'review_structure';

    public const REASON_DIRECT_PAIR_DUPLICATE          = 'DIRECT_PAIR_CONTEXT_DUPLICATE';
    public const REASON_POSSIBLE_CONTEXTUAL_DUPLICATE  = 'POSSIBLE_CONTEXTUAL_DUPLICATE';

    private function __construct(
        public readonly string  $status,
        public readonly ?string $reason,
        public readonly string  $normalizedSubject,
        public readonly string  $normalizedDominantIdea,
        public readonly ?string $synonymDetected,
    ) {}

    public static function pass(string $normalizedSubject, string $normalizedIdea): self
    {
        return new self(
            status:               self::STATUS_PASS,
            reason:               null,
            normalizedSubject:    $normalizedSubject,
            normalizedDominantIdea: $normalizedIdea,
            synonymDetected:      null,
        );
    }

    public static function fail(
        string  $reason,
        string  $normalizedSubject,
        string  $normalizedIdea,
        ?string $synonymDetected = null,
    ): self {
        return new self(
            status:               self::STATUS_FAIL,
            reason:               $reason,
            normalizedSubject:    $normalizedSubject,
            normalizedDominantIdea: $normalizedIdea,
            synonymDetected:      $synonymDetected,
        );
    }

    public static function reviewStructure(string $normalizedSubject, string $normalizedIdea): self
    {
        return new self(
            status:               self::STATUS_REVIEW_STRUCTURE,
            reason:               self::REASON_POSSIBLE_CONTEXTUAL_DUPLICATE,
            normalizedSubject:    $normalizedSubject,
            normalizedDominantIdea: $normalizedIdea,
            synonymDetected:      null,
        );
    }

    public function isPass(): bool            { return $this->status === self::STATUS_PASS; }
    public function isFail(): bool            { return $this->status === self::STATUS_FAIL; }
    public function isReviewStructure(): bool { return $this->status === self::STATUS_REVIEW_STRUCTURE; }
}
