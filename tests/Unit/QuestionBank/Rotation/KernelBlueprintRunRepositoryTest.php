<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\Rotation\KernelBlueprintRunRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class KernelBlueprintRunRepositoryTest extends TestCase
{
    public function test_secondary_kernel_code_writer_is_a_tombstone(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'KernelCodeEngine est l’unique writer canonique de kernel_blueprint_runs.kernel_code.'
        );

        (new KernelBlueprintRunRepository())->markKernelCodeAssigned(
            'bp-writer-guard',
            '04-SCI-PHY-LUM-REF-0001',
        );
    }
}