<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Taxonomy;

use RuntimeException;

final class TaxonomyGeminiTechnicalException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $operation = 'unknown',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }
}