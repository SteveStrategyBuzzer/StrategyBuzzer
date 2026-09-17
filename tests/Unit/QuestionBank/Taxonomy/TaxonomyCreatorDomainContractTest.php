<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Exceptions\QuestionBank\KernelCodeEngineException;
use App\Services\QuestionBank\CreatorDomainRegistry;
use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyOrchestrator;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

final class TaxonomyCreatorDomainContractTest extends TestCase
{
    public function test_taxonomy_resolves_each_creator_code_to_its_english_value(): void
    {
        $orchestrator = (new ReflectionClass(TaxonomyOrchestrator::class))
            ->newInstanceWithoutConstructor();
        $label = new ReflectionMethod(TaxonomyOrchestrator::class, 'domainLabel');
        $label->setAccessible(true);

        foreach (CreatorDomainRegistry::all() as $domain) {
            $this->assertSame(
                $domain['english'],
                $label->invoke($orchestrator, $domain['code'])
            );
        }
    }

    public function test_taxonomy_uses_depth_ten_from_the_shared_depth_registry(): void
    {
        $this->assertSame(
            [2, 4, 6, 7, 8, 9, 10],
            DepthContractRegistry::officialDepths()
        );
        $this->assertTrue(DepthContractRegistry::isKnown(10));
        $this->assertSame(10, DepthContractRegistry::get(10)->depth);
    }

    public function test_general_and_slugs_are_rejected_at_the_taxonomy_identity_boundary(): void
    {
        $orchestrator = (new ReflectionClass(TaxonomyOrchestrator::class))
            ->newInstanceWithoutConstructor();
        $canonical = new ReflectionMethod(TaxonomyOrchestrator::class, 'canonicalDomainCode');
        $canonical->setAccessible(true);

        foreach (['General', 'general', 'geographie'] as $invalid) {
            try {
                $canonical->invoke($orchestrator, $invalid);
                self::fail("La valeur {$invalid} aurait dû être rejetée.");
            } catch (\ReflectionException $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $cause = $exception->getPrevious() ?? $exception;
                $this->assertInstanceOf(KernelCodeEngineException::class, $cause);
                $this->assertSame(
                    KernelCodeEngineException::INVALID_DOMAIN,
                    $cause->errorCode
                );
            }
        }
    }
}