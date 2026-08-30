<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use RuntimeException;

final class Phase1TechnicalException extends RuntimeException
{
    public function __construct(
        public readonly string $failureType,
        string $message
    ) {
        parent::__construct($message);
    }
}