<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank;

use App\Exceptions\QuestionBank\KernelCodeEngineException;
use App\Services\QuestionBank\CreatorDomainRegistry;
use Tests\TestCase;

final class CreatorDomainRegistryTest extends TestCase
{
    public function test_registry_contains_exactly_the_eight_domains_in_official_order(): void
    {
        $this->assertSame(
            ['GEO', 'HIS', 'FAU', 'ART', 'SPO', 'CIN', 'CUI', 'SCI'],
            CreatorDomainRegistry::officialCodes()
        );

        $this->assertSame(
            ['GEO', 'HIS', 'FAU', 'ART', 'SPO', 'CIN', 'CUI', 'SCI'],
            array_column(CreatorDomainRegistry::all(), 'code')
        );
    }

    public function test_registry_exposes_the_official_depths_including_depth_ten(): void
    {
        $this->assertSame([2, 4, 6, 7, 8, 9, 10], CreatorDomainRegistry::officialDepths());
        $this->assertTrue(CreatorDomainRegistry::isOfficialDepth(10));
        $this->assertFalse(CreatorDomainRegistry::isOfficialDepth(11));
    }

    /** @dataProvider domainMappingProvider */
    public function test_domain_mapping_is_canonical(
        string $code,
        string $slug,
        string $english,
        string $french,
        string $legacy,
    ): void {
        $domain = CreatorDomainRegistry::get($code);

        $this->assertSame($code, $domain['code']);
        $this->assertSame($code, $domain['do']);
        $this->assertSame($slug, $domain['slug']);
        $this->assertSame($english, $domain['english']);
        $this->assertSame($french, $domain['french']);
        $this->assertSame($legacy, $domain['legacy']);
    }

    public static function domainMappingProvider(): array
    {
        return [
            ['GEO', 'geographie', 'Geography', 'Géographie', 'GE'],
            ['HIS', 'histoire', 'History', 'Histoire', 'HI'],
            ['FAU', 'faune', 'Wildlife', 'Faune', 'FA'],
            ['ART', 'art', 'Art', 'Art', 'AR'],
            ['SPO', 'sport', 'Sports', 'Sport', 'SP'],
            ['CIN', 'cinema', 'Cinema', 'Cinéma', 'CI'],
            ['CUI', 'cuisine', 'Cuisine', 'Cuisine', 'CU'],
            ['SCI', 'science', 'Science', 'Science', 'SC'],
        ];
    }

    /** @dataProvider compatibleInputProvider */
    public function test_compatible_code_slug_and_french_inputs_resolve_to_code(
        string $input,
        string $expected,
    ): void {
        $this->assertSame($expected, CreatorDomainRegistry::fromInput($input));
    }

    public static function compatibleInputProvider(): array
    {
        return [
            ['GEO', 'GEO'],
            ['geographie', 'GEO'],
            ['Géographie', 'GEO'],
            ['gÉOGRAPHIE', 'GEO'],
            ['CIN', 'CIN'],
            ['cinema', 'CIN'],
            ['Cinéma', 'CIN'],
            ['SCI', 'SCI'],
            ['Science', 'SCI'],
        ];
    }

    public function test_explicit_legacy_lookup_is_bidirectional(): void
    {
        $this->assertSame('GEO', CreatorDomainRegistry::fromLegacyAlias('GE'));
        $this->assertSame('GEO', CreatorDomainRegistry::fromLegacyAlias('ge'));
        $this->assertSame('GE', CreatorDomainRegistry::legacyAlias('GEO'));
        $this->assertSame('SCI', CreatorDomainRegistry::fromLegacyAlias('SC'));
        $this->assertSame('SC', CreatorDomainRegistry::legacyAlias('SCI'));
    }

    /** @dataProvider invalidDomainProvider */
    public function test_unknown_values_including_general_are_rejected_generically(string $input): void
    {
        try {
            CreatorDomainRegistry::fromInput($input);
            self::fail('Une valeur non créatrice aurait dû être rejetée.');
        } catch (KernelCodeEngineException $exception) {
            $this->assertSame(KernelCodeEngineException::INVALID_DOMAIN, $exception->errorCode);
            $this->assertStringContainsString('Domaine créateur inconnu', $exception->getMessage());
        }
    }

    public static function invalidDomainProvider(): array
    {
        return [
            ['General'],
            ['general'],
            ['Général'],
            ['unknown'],
            ['Geography'],
            [''],
        ];
    }

    public function test_returned_registry_data_is_copy_on_write_immutable(): void
    {
        $domain = CreatorDomainRegistry::get('GEO');
        $domain['slug'] = 'general';

        $this->assertSame('geographie', CreatorDomainRegistry::get('GEO')['slug']);
    }

    public function test_unknown_code_and_legacy_alias_use_same_generic_rejection(): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage(KernelCodeEngineException::INVALID_DOMAIN);

        CreatorDomainRegistry::fromLegacyAlias('XX');
    }
}