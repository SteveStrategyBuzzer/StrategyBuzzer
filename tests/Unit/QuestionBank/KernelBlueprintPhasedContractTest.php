<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank;

use App\Services\QuestionBank\KernelBlueprint;
use LogicException;
use Tests\TestCase;

final class KernelBlueprintPhasedContractTest extends TestCase
{
    public function test_empty_blueprint_exposes_exact_persisted_names_and_null_projection(): void
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId('bp-empty');

        $this->assertNull($blueprint->kernel_code);
        foreach ([
            'kernel_code_dd',
            'kernel_code_do',
            'kernel_code_sub',
            'kernel_code_suj',
            'kernel_code_ide',
            'kernel_code_vvvv',
        ] as $name) {
            $this->assertNull($blueprint->{$name});
        }

        $this->assertFalse(isset($blueprint->dd));
        $this->assertFalse(isset($blueprint->do));
        $this->assertSame(null, $blueprint->kernelCodeProjection());
    }

    public function test_each_phase_writes_only_its_exact_segments_and_projection_waits_for_vvvv(): void
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId('bp-phased');
        $blueprint->fillRotation(4, 'Géographie');

        $this->assertSame('04', $blueprint->kernel_code_dd);
        $this->assertSame('GEO', $blueprint->kernel_code_do);
        $this->assertNull($blueprint->kernelCodeProjection());

        $blueprint->fillTaxonomy('Canada', 'Confédération canadienne', 'Acte');
        $this->assertSame('CAN', $blueprint->kernel_code_sub);
        $this->assertSame('CON', $blueprint->kernel_code_suj);
        $this->assertSame('ACT', $blueprint->kernel_code_ide);
        $this->assertNull($blueprint->kernelCodeProjection());

        $blueprint->fillVvvv('000A');
        $this->assertSame('000A', $blueprint->kernel_code_vvvv);
        $this->assertSame('04-GEO-CAN-CON-ACT-000A', $blueprint->kernel_code);
        $this->assertSame($blueprint->kernel_code, $blueprint->kernelCodeProjection());
    }

    public function test_vvvv_is_write_once_and_requires_complete_taxonomy(): void
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId('bp-write-once');
        $blueprint->fillRotation(4, 'Science');

        $this->expectException(LogicException::class);
        $blueprint->fillVvvv('0000');
    }
}