<?php

namespace Tests\Feature;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use App\Services\QuestionBank\MatchQuestionPlanner;
use App\Services\QuestionBank\QuestionBankPicker;
use App\Services\QuestionBank\QuotaAllocator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks down the canonical guarantees of the persistent question bank planner:
 *  - Boss 100 composition = 17 / 9 / 4
 *  - Ligue or composition = 15 / 9 / 6
 *  - Per-round drift bounded by ±1
 *  - 8 sub-domains over 30 questions = largest-remainder (4×6 + 3×2)
 *  - fr & en translations resolve to the SAME canonical group_id
 */
class QuestionBankPlannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_boss_100_composition_is_17_9_4(): void
    {
        $profile = config('question_bank_profiles.boss_profiles.100');
        $this->assertNotNull($profile, 'Boss 100 profile must be defined');

        $alloc = QuotaAllocator::allocate($profile['mix'], 30);

        $this->assertSame(17, $alloc['recognition']);
        $this->assertSame(9, $alloc['reasoning']);
        $this->assertSame(4, $alloc['deceptive_trap']);
        $this->assertSame(30, array_sum($alloc));
    }

    public function test_ligue_or_composition_is_15_9_6(): void
    {
        $profile = config('question_bank_profiles.boss_profiles.40');
        $this->assertNotNull($profile, 'Boss 40 (= ligue or) profile must be defined');

        $alloc = QuotaAllocator::allocate($profile['mix'], 30);

        $this->assertSame(15, $alloc['recognition']);
        $this->assertSame(9, $alloc['reasoning']);
        $this->assertSame(6, $alloc['deceptive_trap']);
    }

    public function test_per_round_distribution_within_plus_minus_one(): void
    {
        $globals = ['recognition' => 17, 'reasoning' => 9, 'deceptive_trap' => 4];
        $perRound = [];

        foreach ($globals as $key => $total) {
            $perRound[$key] = QuotaAllocator::allocatePerRound($total, 3);
        }

        foreach ($globals as $key => $total) {
            $rounds = $perRound[$key];
            $this->assertCount(3, $rounds);
            $this->assertSame($total, array_sum($rounds), "global mismatch for {$key}");
            $this->assertLessThanOrEqual(1, max($rounds) - min($rounds), "drift > 1 for {$key}");
        }

        // The per-round cognitive drift is bounded by ±1 for every cognitive type.
        // The planner may cross-balance the per-round totals so the GLOBAL sum is 30,
        // but the round-level totals can drift slightly when the global mix doesn't
        // divide evenly across rounds — the canonical guarantee is the cognitive drift.
        $globalRoundTotal = array_sum($globals);
        $sumOfRounds = 0;
        for ($r = 1; $r <= 3; $r++) {
            $sumOfRounds += $perRound['recognition'][$r] + $perRound['reasoning'][$r] + $perRound['deceptive_trap'][$r];
        }
        $this->assertSame($globalRoundTotal, $sumOfRounds, 'sum of per-round questions must equal global total');
    }

    public function test_planner_per_round_slot_count_and_cog_drift_for_boss_100(): void
    {
        // Regression test: Boss 100 (17/9/4) over 3 rounds must produce
        //   - exactly 10 slots per round
        //   - cog-per-round drift ≤ 1 per cognitive type
        //   - global per-cog totals preserved
        // Earlier independent per-cog splitting produced [11, 10, 9] round sums.
        $planner = app(MatchQuestionPlanner::class);
        $projection = $planner->projectPlan('boss', 100, 30, 3, 'general');

        $perRound = $projection['per_round_composition'];
        $globalComposition = $projection['global_composition'];
        $this->assertSame(17, $globalComposition['recognition']);
        $this->assertSame(9, $globalComposition['reasoning']);
        $this->assertSame(4, $globalComposition['deceptive_trap']);

        // Each round must have exactly 10 slots.
        for ($r = 1; $r <= 3; $r++) {
            $this->assertSame(10, array_sum($perRound[$r]), "round {$r} must have exactly 10 slots");
        }

        // Per-cognitive drift ≤ 1 across rounds.
        foreach (['recognition', 'reasoning', 'deceptive_trap'] as $cog) {
            $vals = [$perRound[1][$cog], $perRound[2][$cog], $perRound[3][$cog]];
            $this->assertLessThanOrEqual(
                1,
                max($vals) - min($vals),
                "cog drift > 1 for {$cog}: " . implode(',', $vals)
            );
            $this->assertSame($globalComposition[$cog], array_sum($vals), "global mismatch for {$cog}");
        }
    }

    public function test_eight_subdomains_largest_remainder_sums_to_30(): void
    {
        $shares = [
            'Histoire'   => 1, 'Sport'  => 1, 'Géographie' => 1, 'Art'    => 1,
            'Cuisine'    => 1, 'Science'=> 1, 'Cinéma'    => 1, 'Faune' => 1,
        ];
        $alloc = QuotaAllocator::allocate($shares, 30);
        $this->assertSame(30, array_sum($alloc));

        foreach ($shares as $sub => $_) {
            $this->assertArrayHasKey($sub, $alloc);
            $this->assertGreaterThanOrEqual(3, $alloc[$sub]);
            $this->assertLessThanOrEqual(4, $alloc[$sub]);
        }
        $this->assertSame(6, count(array_filter($alloc, fn ($v) => $v === 4)), 'six sub-domains must get 4');
        $this->assertSame(2, count(array_filter($alloc, fn ($v) => $v === 3)), 'two sub-domains must get 3');
    }

    public function test_solo_band_1_9_recognition_dominant(): void
    {
        $bands = config('question_bank_profiles.student_bands');
        $band = collect($bands)->firstWhere(fn ($b) => $b['levels'] === [1, 9]);
        $this->assertNotNull($band, 'Solo band 1-9 must be defined');

        $studentMix = config('question_bank_profiles.student_cognitive_mix');
        $alloc = QuotaAllocator::allocate($studentMix, 30);
        $this->assertGreaterThanOrEqual($alloc['reasoning'], $alloc['recognition']);
        $this->assertGreaterThanOrEqual($alloc['deceptive_trap'], $alloc['recognition']);
        $this->assertSame(30, array_sum($alloc));
    }

    public function test_multilingual_same_group_ids_for_fr_and_en(): void
    {
        $group = QuestionGroup::create([
            'difficulty_level' => 5,
            'difficulty_depth' => 3,
            'domain' => 'Histoire',
            'sub_domain' => 'Histoire',
            'question_type' => 'qcm',
            'cognitive_type' => 'recognition',
            'concept_id' => 'test:concept:napoleon-1769',
            'source' => 'seed',
            'validated' => true,
        ]);

        QuestionTranslation::create([
            'question_group_id' => $group->id,
            'language' => 'fr',
            'question_text' => 'En quelle année est né Napoléon ?',
            'answer_a' => '1769', 'answer_b' => '1770',
            'answer_c' => '1771', 'answer_d' => '1772',
            'correct_answer_key' => 'A',
        ]);

        QuestionTranslation::create([
            'question_group_id' => $group->id,
            'language' => 'en',
            'question_text' => 'In what year was Napoleon born?',
            'answer_a' => '1769', 'answer_b' => '1770',
            'answer_c' => '1771', 'answer_d' => '1772',
            'correct_answer_key' => 'A',
        ]);

        // Use 'general' as the theme so the picker expands to the 8 sub-domains
        // (the seeded group lives under domain=general / sub_domain=Histoire).
        // Force cognitive_type=recognition to deterministically match the seeded group.
        $picker = app(QuestionBankPicker::class);
        $fr = $picker->pickOne('general', 5, 'fr', [], 'recognition');
        $en = $picker->pickOne('general', 5, 'en', [], 'recognition');

        $this->assertNotNull($fr, 'FR translation must be served');
        $this->assertNotNull($en, 'EN translation must be served');
        $this->assertSame($fr['group_id'], $en['group_id'], 'fr and en must resolve to same canonical group');
        $this->assertSame('1769', $fr['answers'][$fr['correct_index']]);
        $this->assertSame('1769', $en['answers'][$en['correct_index']]);
        $this->assertSame('fr', $fr['language']);
        $this->assertSame('en', $en['language']);
    }
}
