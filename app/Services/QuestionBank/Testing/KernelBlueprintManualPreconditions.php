<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Testing;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeFormat;
use InvalidArgumentException;

/**
 * Valeurs manuelles contrôlées pour compléter un Blueprint de test.
 *
 * Cette classe ne consulte aucun moteur métier. Elle valide localement les
 * valeurs, puis utilise les écritures canoniques du Blueprint pour conserver
 * l'identité du même agrégat créé par la Factory.
 */
final class KernelBlueprintManualPreconditions
{
    public function __construct(
        public readonly int $depth,
        public readonly string $domain,
        public readonly string $subdomainActive,
        public readonly string $subjectActive,
        public readonly string $dominantIdeaActive,
        public readonly string $kernelCode,
    ) {}

    public function applyTo(KernelBlueprint $blueprint): void
    {
        $this->validateLocally();

        $blueprint->fillRotation($this->depth, $this->domain);
        $blueprint->fillTaxonomy(
            $this->subdomainActive,
            $this->subjectActive,
            $this->dominantIdeaActive,
        );
        $blueprint->fillKernelCode($this->kernelCode);

        if ($blueprint->blueprint_id === null) {
            throw new InvalidArgumentException(
                'Les préconditions manuelles exigent un Blueprint créé par la Factory.',
            );
        }
    }

    private function validateLocally(): void
    {
        if ($this->depth < 1 || $this->depth > 10) {
            throw new InvalidArgumentException('Le depth manuel doit être compris entre 1 et 10.');
        }

        $this->assertNonEmpty('domain', $this->domain);
        $this->assertNonEmpty('subdomain_active', $this->subdomainActive);
        $this->assertNonEmpty('subject_active', $this->subjectActive);
        $this->assertNonEmpty('dominant_idea_active', $this->dominantIdeaActive);

        try {
            $domainCode = KernelCodeFormat::domain($this->domain);
            $prefix = implode('-', [
                KernelCodeFormat::depth($this->depth),
                $domainCode,
                KernelCodeFormat::segment($this->subdomainActive),
                KernelCodeFormat::segment($this->subjectActive),
                KernelCodeFormat::segment($this->dominantIdeaActive),
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException(
                'Les préconditions manuelles ne correspondent pas au format canonique.',
                0,
                $exception,
            );
        }

        if (
            strlen($this->kernelCode) !== KernelCodeFormat::CODE_LENGTH
            || preg_match(KernelCodeFormat::FORMAT_REGEX, $this->kernelCode) !== 1
            || ! str_starts_with($this->kernelCode, $prefix . '-')
        ) {
            throw new InvalidArgumentException(
                'Le kernel_code manuel doit correspondre exactement à l’identité fournie.',
            );
        }
    }

    private function assertNonEmpty(string $field, string $value): void
    {
        if (trim($value) === '') {
            throw new InvalidArgumentException(
                "La précondition manuelle {$field} ne peut pas être vide.",
            );
        }
    }
}