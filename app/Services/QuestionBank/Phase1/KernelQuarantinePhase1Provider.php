<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

interface KernelQuarantinePhase1Provider
{
    /** @return array<string, array<string, mixed>> */
    public function create(object $copy, array $slots): array;
}