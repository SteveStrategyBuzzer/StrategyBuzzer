<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Taxonomy;

use App\Services\QuestionBank\Taxonomy\DepthContractRegistry;
use App\Services\QuestionBank\Taxonomy\TaxonomyGeminiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use Tests\TestCase;

final class TaxonomyGeminiClientLookbackTest extends TestCase
{
    public function test_occurrence_prompt_preserves_subject_scoped_pass_and_fail_history(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'status' => 'CANDIDATES',
                                'subdomain' => 'Physique',
                                'subjects' => [['value' => 'Mécanique']],
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $reflection = new ReflectionClass(TaxonomyGeminiClient::class);
        /** @var TaxonomyGeminiClient $client */
        $client = $reflection->newInstanceWithoutConstructor();
        $apiKey = $reflection->getProperty('apiKey');
        $apiKey->setValue($client, 'test-only-key');

        $client->generateOccurrence(
            'science',
            'Science',
            DepthContractRegistry::get(2),
            [[
                'subdomain' => 'Biologie',
                'subjects' => [[
                    'subject' => 'Cellules',
                    'pass_ideas' => ['Membrane cellulaire'],
                    'fail_ideas' => [[
                        'value' => 'Cuisine moléculaire',
                        'reason' => 'OUTSIDE_SUBDOMAIN',
                    ]],
                ]],
            ]],
        );

        Http::assertSent(function (Request $request): bool {
            $prompt = $request->data()['contents'][0]['parts'][0]['text'] ?? '';

            return str_contains($prompt, 'Sous-domaine : Biologie')
                && str_contains($prompt, 'Sujet : Cellules')
                && str_contains($prompt, 'PASS : Membrane cellulaire')
                && str_contains($prompt, 'FAIL : Cuisine moléculaire (OUTSIDE_SUBDOMAIN)');
        });
    }
}