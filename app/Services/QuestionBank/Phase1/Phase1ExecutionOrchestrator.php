<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;
use LogicException;

final class Phase1ExecutionOrchestrator
{
    public function __construct(
        private readonly Phase1ExecutionRepository $executions,
        private readonly KernelPhase1Generator $generator,
        private readonly ValidationPhase1 $validation,
    ) {}

    public static function production(): self
    {
        return new self(
            new Phase1ExecutionRepository(),
            new KernelPhase1Generator(
                new QuestionApiClient(),
                new \App\Services\QuestionBank\KernelBlueprintCognitiveSlotRepository(),
                new KernelPhase1SourceValidator(),
            ),
            new ValidationPhase1(
                new QuestionApiValidationPhase1Reviewer(new QuestionApiClient()),
            ),
        );
    }

    public function process(string $blueprintId): string
    {
        $claim = $this->executions->claim($blueprintId);
        if ($claim['status'] === Phase1ExecutionRepository::NO_OP_ACTIVE) {
            return $blueprintId;
        }
        if ($claim['status'] === Phase1ExecutionRepository::NO_OP_COMPLETED) {
            $this->validation->validate($blueprintId);
            return $blueprintId;
        }

        $executionId = (string) $claim['execution_id'];
        $leaseToken = (string) $claim['lease_token'];
        $revision = $claim['identity_revision'];
        $phase1Completed = false;

        try {
            $this->generator->generate(
                $blueprintId,
                $revision,
                $executionId,
                $leaseToken,
            );
            $result = $this->executions->completeCreatedPhase1(
                $executionId,
                $leaseToken,
                $blueprintId,
                $revision,
            );
            if ($result === Phase1ExecutionRepository::STALE_RESULT) {
                throw new LogicException("[Phase1] Résultat obsolète: {$blueprintId}.");
            }
            $phase1Completed = true;
            $this->validation->validate($blueprintId);
            return $blueprintId;
        } catch (\Throwable $exception) {
            if (! $phase1Completed) {
                try {
                    $this->executions->complete(
                        $executionId,
                        $leaseToken,
                        $blueprintId,
                        $revision,
                        [
                            'outcome' => 'FAILURE',
                            'type' => get_class($exception),
                        ],
                    );
                } catch (\Throwable) {
                    // Preserve the original provider/generation exception.
                }
            }
            throw $exception;
        }
    }
}