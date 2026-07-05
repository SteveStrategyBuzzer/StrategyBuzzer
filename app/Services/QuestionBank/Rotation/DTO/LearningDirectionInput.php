<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Rotation\DTO;

final class LearningDirectionInput
{
    public function __construct(
        public readonly int     $depth,
        public readonly string  $domainCode,
        public readonly string  $subDomain,
        public readonly string  $subject,
        public readonly string  $dominantIdea,
        public readonly int     $knowledgeFrequency,
        public readonly ?string $rotationIdentifier = null,
    ) {}
}
