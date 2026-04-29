<?php

namespace Tests\Feature;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use App\Services\QuestionBank\QuestionBankRepository;
use App\Services\QuestionBank\Worker\BankNeedsCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankNeedsCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_bank_yields_deficit_for_every_segment(): void
    {
        $calc = new BankNeedsCalculator(new QuestionBankRepository());

        $deficits = $calc->computeDeficits(limit: 5);

        $this->assertNotEmpty($deficits, 'empty bank must report deficits');
        foreach ($deficits as $row) {
            $this->assertGreaterThan(0, $row['deficit']);
            $this->assertSame(0, $row['present']);
            $this->assertArrayHasKey('language', $row);
            $this->assertArrayHasKey('cognitive_type', $row);
            $this->assertArrayHasKey('sub_domain', $row);
        }
    }

    public function test_deficits_sorted_desc_by_deficit(): void
    {
        $calc = new BankNeedsCalculator(new QuestionBankRepository());

        $deficits = $calc->computeDeficits(limit: 50);

        $previous = PHP_INT_MAX;
        foreach ($deficits as $row) {
            $this->assertLessThanOrEqual($previous, $row['deficit'], 'deficits must be sorted desc');
            $previous = $row['deficit'];
        }
    }

    public function test_present_count_drops_when_validated_groups_exist(): void
    {
        // Insert a single validated FR question for Solo band 31-39 / Histoire / recognition.
        $group = QuestionGroup::create([
            'difficulty_level' => 31,
            'difficulty_depth' => 5,
            'domain' => 'Histoire',
            'sub_domain' => 'Histoire',
            'question_type' => 'qcm',
            'cognitive_type' => 'recognition',
            'concept_id' => 'test-concept-1',
            'concept_family' => 'antiquite',
            'source' => 'seed',
            'validated' => true,
        ]);
        QuestionTranslation::create([
            'question_group_id' => $group->id,
            'language' => 'fr',
            'question_text' => 'Question test 1?',
            'answer_a' => 'A', 'answer_b' => 'B', 'answer_c' => 'C', 'answer_d' => 'D',
            'correct_answer_key' => 'A',
            'explanation' => 'x',
            'saviez_vous' => 'fait surprenant et detaille pour passer le guard de longueur min',
        ]);

        $calc = new BankNeedsCalculator(new QuestionBankRepository());
        $deficits = $calc->computeDeficits();

        // The FR/Histoire/recognition row for the duo novice profile must have present=1.
        $matching = array_filter($deficits, fn ($r) =>
            $r['language'] === 'fr'
            && $r['sub_domain'] === 'Histoire'
            && $r['cognitive_type'] === 'recognition'
            && ($r['mode_target']['type'] ?? null) === 'solo_range'
            && ($r['mode_target']['levels'][0] ?? null) === 31
        );
        $this->assertNotEmpty($matching, 'expected a Duo-novice/Histoire/recognition/fr row');
        foreach ($matching as $row) {
            $this->assertSame(1, $row['present']);
        }
    }

    public function test_estimate_matches_buildable_returns_zero_on_empty_bank(): void
    {
        $calc = new BankNeedsCalculator(new QuestionBankRepository());
        $rows = $calc->estimateMatchesBuildable('fr');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame(0, $row['matches_buildable'], 'empty bank cannot build any match');
        }
    }
}
