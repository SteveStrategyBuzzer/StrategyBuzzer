<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\ValidationPhase2;

interface ValidationPhase2Reviewer
{
    /** The implementation must be independent from the Phase2 translator. */
    public function review(ValidationPhase2Request $request): ValidationPhase2Response;
}