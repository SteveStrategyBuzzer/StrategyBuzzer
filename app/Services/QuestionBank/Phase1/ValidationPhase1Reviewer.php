<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

/**
 * Provider-neutral review boundary. Implementations may be deterministic
 * fakes in tests; production wiring is deliberately outside this service.
 */
interface ValidationPhase1Reviewer
{
    /** @return array<string, mixed> */
    public function review(array $input): array;
}
