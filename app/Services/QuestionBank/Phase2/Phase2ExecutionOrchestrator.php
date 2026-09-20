<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase2;

final class Phase2ExecutionOrchestrator
{
    public function __construct(
        private readonly Phase2TranslationRepository $repository = new Phase2TranslationRepository(),
        private readonly ?Phase2Provider $provider = null,
    ) {}

    /** Phase boundary: the only input and output is blueprint_id. */
    public function run(string $blueprintId): string
    {
        $preflight = $this->repository->preflight($blueprintId);
        if ($this->provider === null && $preflight['source_revision'] !== []) {
            throw new \LogicException('Phase2 provider is not configured.');
        }
        if ($this->provider === null) return $blueprintId;
        foreach ($preflight['source_revision'] as $type => $revision) {
            foreach (Phase2TranslationRepository::LANGUAGES as $language) {
                $claim = $this->repository->claim(
                    $blueprintId, $type, $language, $revision,
                    $preflight['blueprint'], $preflight['slots'][$type],
                );
                if (($claim['outcome'] ?? null) !== null) continue;
                $response = $this->provider->translate($claim['request']);
                if ($response instanceof Phase2ProviderTechnicalFailure) {
                    $this->repository->fail($claim['operation_id'], $response);
                } else {
                    $result = $this->repository->apply($claim['operation_id'], $response);
                    if ($result === Phase2TranslationRepository::STALE_RESULT
                        && $this->repository->isCurrentProviderResponse($claim['operation_id'], $response)) {
                        // A malformed structured response is technical, never
                        // an intellectual conclusion. Fencing still prevents
                        // this from consuming a changed/expired operation.
                        $this->repository->fail(
                            $claim['operation_id'],
                            new Phase2ProviderTechnicalFailure('INVALID_STRUCTURED_RESPONSE', true),
                        );
                    }
                }
            }
        }
        return $blueprintId;
    }
}