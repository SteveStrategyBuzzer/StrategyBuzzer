<?php

declare(strict_types=1);

namespace App\Services\QuestionBank\Phase1;

use App\Services\QuestionApi\QuestionApiClient;
use App\Services\QuestionBank\KernelBlueprint;
use Throwable;

final class QuestionApiKernelQuarantinePhase1Provider implements KernelQuarantinePhase1Provider
{
    public function __construct(
        private readonly QuestionApiClient $questionApi = new QuestionApiClient(),
        private readonly KernelPhase1SourceValidator $validator = new KernelPhase1SourceValidator(),
    ) {}

    public function create(object $copy, array $slots): array
    {
        $blueprint = new KernelBlueprint();
        $blueprint->initializeBlueprintId((string) $copy->blueprint_id);
        $blueprint->fillRotation((int) ($copy->depth ?? 1), (string) ($copy->domain_code ?? 'science'));
        $blueprint->fillTaxonomy(
            (string) ($copy->subdomain_active ?? 'science'),
            (string) ($copy->subject_active ?? 'science'),
            (string) ($copy->dominant_idea_active ?? 'science'),
        );
        $blueprint->fillKernelCode((string) $copy->kernel_code);
        $last = 'Aucune réponse Phase 1 valide.';
        for ($attempt = 1; $attempt <= KernelPhase1Generator::MAX_TECHNICAL_ATTEMPTS; $attempt++) {
            try {
                $response = $this->questionApi->postAdmin(
                    QuestionApiClient::ENDPOINT_KERNEL_PHASE1_SOURCE,
                    $this->requestPayload($blueprint),
                    ['source' => 'kernel_quarantine_phase1', 'timeout' => 120],
                );
                if (! $response->successful()) {
                    throw new Phase1TechnicalException('TRANSPORT', "Question API HTTP {$response->status()}.");
                }
                $envelope = $response->json();
                $payload = is_array($envelope) ? ($envelope['result'] ?? null) : null;
                if (! is_array($envelope) || ($envelope['ok'] ?? false) !== true || ! is_array($payload)) {
                    throw new Phase1TechnicalException('INVALID_SCHEMA', 'Enveloppe Question API Phase 1 invalide.');
                }
                $validated = $this->validator->validate($blueprint, $payload);
                if ($validated['invalid'] === []) {
                    return $validated['valid'];
                }
                $last = implode(' | ', $validated['invalid']);
            } catch (Phase1TechnicalException $exception) {
                $last = $exception->getMessage();
            } catch (Throwable $exception) {
                $last = $exception->getMessage();
            }
        }
        throw new Phase1TechnicalException(
            'CREATION_FAILED',
            'Création Phase 1 Quarantaine échouée après '
            . KernelPhase1Generator::MAX_TECHNICAL_ATTEMPTS . " tentatives: {$last}",
        );
    }

    private function requestPayload(KernelBlueprint $blueprint): array
    {
        return [
            'schema_version' => KernelPhase1SourceValidator::SCHEMA_VERSION,
            'generation_contract_version' => KernelPhase1Generator::GENERATION_CONTRACT_VERSION,
            'blueprint_id' => $blueprint->blueprint_id,
            'kernel_code' => $blueprint->kernel_code,
            'depth' => $blueprint->depth,
            'domain' => $blueprint->domain,
            'subdomain_active' => $blueprint->subdomain_active,
            'subject_active' => $blueprint->subject_active,
            'dominant_idea_active' => $blueprint->dominant_idea_active,
            'source_language' => KernelPhase1SourceValidator::SOURCE_LANGUAGE,
            'cognitive_rules' => ['types' => KernelBlueprint::COGNITIVE_TYPES, 'qcm_correct_answer_key' => 'a',
                'true_false_choices' => ['a' => 'VRAI', 'b' => 'FAUX'], 'independent_slots' => true, 'no_master_slot' => true],
            'reading_rules' => ['reading_speed_wpm' => 150, 'question_max_seconds' => 8, 'sv_max_seconds' => 30],
            'output_schema' => KernelPhase1SourceValidator::SCHEMA_VERSION,
        ];
    }
}