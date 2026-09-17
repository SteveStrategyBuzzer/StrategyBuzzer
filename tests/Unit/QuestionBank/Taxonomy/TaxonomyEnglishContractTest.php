<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\TaxonomyEnglishContract;
use App\Services\QuestionBank\Taxonomy\TaxonomyPreparationException;
use PHPUnit\Framework\TestCase;

final class TaxonomyEnglishContractTest extends TestCase
{
    public function test_english_payload_is_accepted_for_persistence_boundary(): void
    {
        $this->assertSame(
            ['Matter Properties', 'Elementary Particles', 'Electric Charge'],
            TaxonomyEnglishContract::assertValues(
                ['Matter Properties', 'Elementary Particles', 'Electric Charge'],
                'Taxonomy value',
            ),
        );
    }

    /** @dataProvider clearlyFrenchPayloadProvider */
    public function test_clearly_french_payload_is_rejected_before_persistence(string $value): void
    {
        $this->expectException(TaxonomyPreparationException::class);
        TaxonomyEnglishContract::assertValue($value, 'Taxonomy value');
    }

    public static function clearlyFrenchPayloadProvider(): array
    {
        return [
            ['Histoire'],
            ['Guerre mondiale'],
            ['Animaux marins'],
            ['Empire romain'],
            ['Rome antique'],
            ['Art moderne'],
            ['Monde animal'],
            ['Propriétés de la matière'],
            ['Particules élémentaires'],
            ['Charge électrique'],
            ['Guerre de 1812'],
        ];
    }
}