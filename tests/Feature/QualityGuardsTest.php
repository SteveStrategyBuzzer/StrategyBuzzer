<?php

namespace Tests\Feature;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\QualityGuards;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QualityGuardsTest extends TestCase
{
    use RefreshDatabase;

    private function basePayload(array $over = []): array
    {
        $base = [
            'difficulty_level' => 31,
            'difficulty_depth' => 5,
            'domain' => 'Histoire',
            'sub_domain' => 'Histoire',
            'question_type' => 'qcm',
            'cognitive_type' => 'recognition',
            'concept_id' => 'guard-test-1',
            'concept_family' => 'rome-antique',
            'source' => 'gemini',
            'validated' => false,
            'translations' => [
                'fr' => [
                    'question_text' => 'Quel empereur romain a fait construire le Colisée ?',
                    'answer_a' => 'Vespasien', 'answer_b' => 'Néron',
                    'answer_c' => 'Trajan', 'answer_d' => 'Hadrien',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Vespasien a lancé la construction en 70.',
                    'saviez_vous' => 'Le Colisée pouvait accueillir jusqu\'à 50 000 spectateurs en son temps.',
                ],
                'en' => [
                    'question_text' => 'Which Roman emperor began building the Colosseum?',
                    'answer_a' => 'Vespasian', 'answer_b' => 'Nero',
                    'answer_c' => 'Trajan', 'answer_d' => 'Hadrian',
                    'correct_answer_key' => 'A',
                    'explanation' => 'Vespasian began construction in 70 AD.',
                    'saviez_vous' => 'The Colosseum could hold up to 50,000 spectators at the time.',
                ],
            ],
        ];
        return array_replace_recursive($base, $over);
    }

    public function test_accepts_well_formed_payload(): void
    {
        $guards = new QualityGuards(new QuestionBankRepository());
        $verdict = $guards->evaluate($this->basePayload());

        $this->assertTrue($verdict['ok'], json_encode($verdict));
    }

    public function test_rejects_when_required_language_missing(): void
    {
        $payload = $this->basePayload();
        unset($payload['translations']['en']);

        $verdict = (new QualityGuards(new QuestionBankRepository()))->evaluate($payload);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('missing_translations', $verdict['code']);
    }

    public function test_rejects_when_saviez_vous_too_short(): void
    {
        $payload = $this->basePayload();
        $payload['translations']['fr']['saviez_vous'] = 'trop court';

        $verdict = (new QualityGuards(new QuestionBankRepository()))->evaluate($payload);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('missing_saviez_vous', $verdict['code']);
    }

    public function test_rejects_when_correct_key_diverges_across_languages(): void
    {
        $payload = $this->basePayload();
        $payload['translations']['en']['correct_answer_key'] = 'B';

        $verdict = (new QualityGuards(new QuestionBankRepository()))->evaluate($payload);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('answer_key_misaligned', $verdict['code']);
    }

    public function test_rejects_when_concept_id_already_in_segment(): void
    {
        $existing = QuestionGroup::create([
            'difficulty_level' => 31,
            'difficulty_depth' => 5,
            'domain' => 'Histoire',
            'sub_domain' => 'Histoire',
            'question_type' => 'qcm',
            'cognitive_type' => 'recognition',
            'concept_id' => 'dup-id-here',
            'source' => 'seed',
            'validated' => true,
        ]);

        $payload = $this->basePayload(['concept_id' => 'dup-id-here']);

        $verdict = (new QualityGuards(new QuestionBankRepository()))->evaluate($payload);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('dup_concept_id', $verdict['code']);
    }

    public function test_rejects_text_too_similar_to_existing(): void
    {
        $g = QuestionGroup::create([
            'difficulty_level' => 31,
            'difficulty_depth' => 5,
            'domain' => 'Histoire',
            'sub_domain' => 'Histoire',
            'question_type' => 'qcm',
            'cognitive_type' => 'recognition',
            'concept_id' => 'existing-clone',
            'source' => 'seed',
            'validated' => true,
        ]);
        QuestionTranslation::create([
            'question_group_id' => $g->id,
            'language' => 'fr',
            'question_text' => 'Quel empereur romain a fait construire le Colisée ?',
            'answer_a' => 'Vespasien', 'answer_b' => 'Néron',
            'answer_c' => 'Trajan', 'answer_d' => 'Hadrien',
            'correct_answer_key' => 'A',
            'saviez_vous' => 'pareil avec longueur suffisante pour passer le guard min length de 30',
        ]);

        // concept_family=null so the family-share guard is skipped and we
        // really test text similarity in isolation.
        $payload = $this->basePayload(['concept_id' => 'fresh-id-xyz', 'concept_family' => null]);

        $verdict = (new QualityGuards(new QuestionBankRepository()))->evaluate($payload);

        $this->assertFalse($verdict['ok']);
        $this->assertSame('text_similarity', $verdict['code']);
    }
}
