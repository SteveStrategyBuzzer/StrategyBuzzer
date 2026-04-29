<?php

namespace Tests\Feature;

use App\Models\QuestionGroup;
use App\Models\QuestionTranslation;
use App\Services\QuestionBank\MatchQuestionPlanner as BankNamespacePlanner;
use App\Services\QuestionBank\QuestionBankPicker;
use App\Services\QuestionBank\QuotaAllocator;
use App\Services\MatchQuestionPlanner as FlatPlanner;
use App\Services\GameServerQuestionPipeline;
use App\Services\QuestionService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Locks down the canonical guarantees of the persistent question bank.
 *
 * Two planner implementations coexist after rebase:
 *   - App\Services\QuestionBank\MatchQuestionPlanner (HEAD): projectPlan() returning
 *     per_round_composition / global_composition. Used by the worker (#82) and the
 *     bank-first nominal path in QuestionService.
 *   - App\Services\MatchQuestionPlanner (Task #81 incoming): buildPlan() returning
 *     composition_actual / per_round_actual / ordered_questions / ordered_group_ids /
 *     shortages. Used by the multiplayer match-init pipeline.
 *
 * Both must hold their respective contracts.
 */
class QuestionBankPlannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force la connexion par défaut à un sqlite en mémoire pour ce test,
        // indépendamment de la config production (pgsql sur Neon).
        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');
        DB::setDefaultConnection('sqlite');

        // Crée les tables minimales nécessaires (pas tout le schema legacy
        // qui exige des extensions pgsql).
        Schema::dropAllTables();
        Schema::create('question_groups', function ($t) {
            $t->id();
            $t->unsignedSmallInteger('difficulty_level')->nullable();
            $t->unsignedSmallInteger('boss_level')->nullable();
            $t->unsignedTinyInteger('difficulty_depth');
            $t->string('domain', 64);
            $t->string('sub_domain', 64);
            $t->string('question_type', 16);
            $t->string('cognitive_type', 32);
            $t->string('concept_id', 96);
            $t->string('concept_family', 96)->nullable();
            $t->string('source', 32)->default('seed');
            $t->boolean('validated')->default(false);
            $t->unsignedInteger('usage_count')->default(0);
            $t->timestamp('last_used_at')->nullable();
            $t->timestamps();
            $t->unique('concept_id');
        });
        Schema::create('question_translations', function ($t) {
            $t->id();
            $t->unsignedBigInteger('question_group_id');
            $t->string('language', 4);
            $t->text('question_text');
            $t->text('answer_a');
            $t->text('answer_b');
            $t->text('answer_c')->nullable();
            $t->text('answer_d')->nullable();
            $t->char('correct_answer_key', 1);
            $t->text('explanation')->nullable();
            $t->text('saviez_vous')->nullable();
            $t->timestamps();
            $t->unique(['question_group_id', 'language']);
        });
        Schema::create('match_question_plans', function ($t) {
            $t->id();
            $t->string('plan_id', 64)->unique();
            $t->string('mode', 32);
            $t->string('division', 32)->nullable();
            $t->unsignedSmallInteger('total_questions');
            $t->unsignedSmallInteger('rounds_count');
            $t->string('language', 4);
            $t->json('global_composition');
            $t->json('per_round_composition');
            $t->json('ordered_group_ids');
            $t->json('shortages')->nullable();
            $t->timestamps();
        });
        Schema::create('jobs', function ($t) {
            $t->id();
            $t->string('queue')->index();
            $t->longText('payload');
            $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable();
            $t->unsignedInteger('available_at');
            $t->unsignedInteger('created_at');
        });
    }

    /**
     * Construit une banque suffisamment riche pour que le planner puisse
     * remplir n'importe quelle composition exigée par les profils.
     *
     * Pour chaque combinaison (boss_level | difficulty_level, cognitive_type,
     * sub_domain), on crée plusieurs groupes avec leurs traductions FR + EN.
     */
    private function seedRichBank(): void
    {
        $cognitiveTypes = ['recognition', 'reasoning', 'deceptive_trap'];
        $subDomains = ['histoire', 'sport', 'geographie', 'art', 'cuisine', 'science', 'cinema', 'faune'];
        $bossLevels = [10, 20, 30, 40, 60, 70, 90, 100];
        $soloLevels = [5, 15, 25, 35, 45, 55, 65, 75, 85, 95];

        $counter = 0;

        // Boss
        foreach ($bossLevels as $boss) {
            $depth = config("question_bank_profiles.boss_profiles.{$boss}.depth");
            foreach ($cognitiveTypes as $cog) {
                foreach ($subDomains as $sub) {
                    for ($i = 0; $i < 6; $i++) {
                        $counter++;
                        $g = QuestionGroup::create([
                            'difficulty_level' => null,
                            'boss_level'       => $boss,
                            'difficulty_depth' => $depth,
                            'domain'           => 'general',
                            'sub_domain'       => $sub,
                            'question_type'    => 'qcm',
                            'cognitive_type'   => $cog,
                            'concept_id'       => "boss-{$boss}-{$cog}-{$sub}-{$i}-{$counter}",
                            'concept_family'   => "boss-fam-{$boss}-{$sub}-{$i}",
                            'source'           => 'seed',
                            'validated'        => true,
                        ]);
                        $this->seedTranslations($g, $counter);
                    }
                }
            }
        }

        // Solo
        foreach ($soloLevels as $level) {
            $depth = $this->bandDepth($level);
            foreach ($cognitiveTypes as $cog) {
                foreach ($subDomains as $sub) {
                    for ($i = 0; $i < 6; $i++) {
                        $counter++;
                        $g = QuestionGroup::create([
                            'difficulty_level' => $level,
                            'boss_level'       => null,
                            'difficulty_depth' => $depth,
                            'domain'           => 'general',
                            'sub_domain'       => $sub,
                            'question_type'    => 'qcm',
                            'cognitive_type'   => $cog,
                            'concept_id'       => "solo-{$level}-{$cog}-{$sub}-{$i}-{$counter}",
                            'concept_family'   => "solo-fam-{$level}-{$sub}-{$i}",
                            'source'           => 'seed',
                            'validated'        => true,
                        ]);
                        $this->seedTranslations($g, $counter);
                    }
                }
            }
        }
    }

    private function bandDepth(int $level): int
    {
        $bands = config('question_bank_profiles.solo_bands', config('question_bank_profiles.student_bands', []));
        foreach ($bands as $b) {
            [$lo, $hi] = $b['levels'];
            if ($level >= $lo && $level <= $hi) {
                $d = $b['depth'] ?? 5;
                return is_array($d) ? $d[0] : $d;
            }
        }
        return 5;
    }

    private function seedTranslations(QuestionGroup $g, int $counter): void
    {
        $base = [
            'question_group_id'  => $g->id,
            'correct_answer_key' => 'B',
            'explanation'        => "Explication {$counter}",
            'saviez_vous'        => "SV {$counter}",
        ];
        QuestionTranslation::create(array_merge($base, [
            'language'      => 'fr',
            'question_text' => "Question FR #{$counter}",
            'answer_a'      => "Réponse A {$counter}",
            'answer_b'      => "Réponse B {$counter}",
            'answer_c'      => "Réponse C {$counter}",
            'answer_d'      => "Réponse D {$counter}",
        ]));
        QuestionTranslation::create(array_merge($base, [
            'language'      => 'en',
            'question_text' => "Question EN #{$counter}",
            'answer_a'      => "Answer A {$counter}",
            'answer_b'      => "Answer B {$counter}",
            'answer_c'      => "Answer C {$counter}",
            'answer_d'      => "Answer D {$counter}",
        ]));
    }

    // -------------------------------------------------------------------------
    // Incoming (Task #81) integration tests — exercise the flat planner with
    // buildPlan() and the rich seeded bank.
    // -------------------------------------------------------------------------

    public function test_boss_100_yields_exact_17_9_4_composition(): void
    {
        $this->seedRichBank();
        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('boss', 100, 30, 3, 'fr');

        $this->assertSame(17, $plan['composition_actual']['recognition'] ?? 0);
        $this->assertSame(9,  $plan['composition_actual']['reasoning'] ?? 0);
        $this->assertSame(4,  $plan['composition_actual']['deceptive_trap'] ?? 0);
        $this->assertCount(30, $plan['ordered_questions']);
        $this->assertEmpty($plan['shortages']);
    }

    public function test_ligue_or_yields_15_9_6_composition(): void
    {
        $this->seedRichBank();
        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('ligue', 'or', 30, 3, 'fr');

        $this->assertSame(15, $plan['composition_actual']['recognition'] ?? 0);
        $this->assertSame(9,  $plan['composition_actual']['reasoning'] ?? 0);
        $this->assertSame(6,  $plan['composition_actual']['deceptive_trap'] ?? 0);
    }

    public function test_per_round_tolerance_within_one(): void
    {
        $this->seedRichBank();
        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('boss', 100, 30, 3, 'fr');

        $tolerance = config('question_bank_profiles.composition_tolerance', 1);
        foreach ($plan['per_round_actual'] as $round => $cog) {
            foreach ($cog as $code => $count) {
                $target = $plan['per_round_quotas'][$round][$code] ?? 0;
                $this->assertLessThanOrEqual(
                    $tolerance,
                    abs($count - $target),
                    "Round {$round} cog={$code} actual={$count} target={$target}"
                );
            }
        }
    }

    public function test_duo_intermediaire_yields_50_20_30_split_within_tolerance(): void
    {
        $this->seedRichBank();
        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('duo', 'intermediaire', 30, 3, 'fr');

        $this->assertSame(15, $plan['composition_actual']['recognition'] ?? 0);
        $this->assertSame(6,  $plan['composition_actual']['reasoning'] ?? 0);
        $this->assertSame(9,  $plan['composition_actual']['deceptive_trap'] ?? 0);
    }

    public function test_two_languages_get_same_group_ids_in_same_order(): void
    {
        $this->seedRichBank();
        $planner = new FlatPlanner();

        $planFr = $planner->buildPlan('duo', 'intermediaire', 30, 3, 'fr');

        QuestionGroup::query()->update(['usage_count' => 0, 'last_used_at' => null]);

        $planEn = $planner->buildPlan('duo', 'intermediaire', 30, 3, 'en');

        $this->assertSame(
            $planFr['ordered_group_ids'],
            $planEn['ordered_group_ids'],
            'Les deux langues doivent recevoir les mêmes question_group_id dans le même ordre.'
        );

        foreach ($planFr['ordered_questions'] as $i => $qFr) {
            $qEn = $planEn['ordered_questions'][$i];
            $this->assertNotSame($qFr['question_text'], $qEn['question_text']);
            $this->assertSame($qFr['correct_index'], $qEn['correct_index']);
        }
    }

    public function test_plan_does_not_call_external_question_api(): void
    {
        $this->seedRichBank();

        Http::fake();

        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('duo', 'intermediaire', 30, 3, 'fr');

        $this->assertCount(30, $plan['ordered_questions']);
        Http::assertNothingSent();
    }

    // -------------------------------------------------------------------------
    // HEAD canonical guarantees — exercise QuotaAllocator + the QuestionBank
    // namespace planner (projection API).
    // -------------------------------------------------------------------------

    public function test_boss_100_composition_is_17_9_4(): void
    {
        $profile = config('question_bank_profiles.boss_profiles.100');
        $this->assertNotNull($profile, 'Boss 100 profile must be defined');

        $mix = $profile['mix'] ?? $profile['cognitive_mix'] ?? null;
        $this->assertNotNull($mix, 'Boss 100 cognitive mix must be defined');

        $alloc = QuotaAllocator::allocate($mix, 30);

        $this->assertSame(17, $alloc['recognition']);
        $this->assertSame(9, $alloc['reasoning']);
        $this->assertSame(4, $alloc['deceptive_trap']);
        $this->assertSame(30, array_sum($alloc));
    }

    public function test_ligue_or_composition_is_15_9_6(): void
    {
        $profile = config('question_bank_profiles.boss_profiles.40');
        $this->assertNotNull($profile, 'Boss 40 (= ligue or) profile must be defined');

        $mix = $profile['mix'] ?? $profile['cognitive_mix'] ?? null;
        $this->assertNotNull($mix, 'Boss 40 cognitive mix must be defined');

        $alloc = QuotaAllocator::allocate($mix, 30);

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

        $globalRoundTotal = array_sum($globals);
        $sumOfRounds = 0;
        for ($r = 1; $r <= 3; $r++) {
            $sumOfRounds += $perRound['recognition'][$r] + $perRound['reasoning'][$r] + $perRound['deceptive_trap'][$r];
        }
        $this->assertSame($globalRoundTotal, $sumOfRounds, 'sum of per-round questions must equal global total');
    }

    public function test_planner_per_round_slot_count_and_cog_drift_for_boss_100(): void
    {
        $planner = app(BankNamespacePlanner::class);
        $projection = $planner->projectPlan('boss', 100, 30, 3, 'general');

        $perRound = $projection['per_round_composition'];
        $globalComposition = $projection['global_composition'];
        $this->assertSame(17, $globalComposition['recognition']);
        $this->assertSame(9, $globalComposition['reasoning']);
        $this->assertSame(4, $globalComposition['deceptive_trap']);

        for ($r = 1; $r <= 3; $r++) {
            $this->assertSame(10, array_sum($perRound[$r]), "round {$r} must have exactly 10 slots");
        }

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
        $bands = config('question_bank_profiles.student_bands', config('question_bank_profiles.solo_bands', []));
        $band = collect($bands)->firstWhere(fn ($b) => $b['levels'] === [1, 9]);
        $this->assertNotNull($band, 'Solo band 1-9 must be defined');

        $studentMix = config('question_bank_profiles.student_cognitive_mix', config('question_bank_profiles.student_mix'));
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
            'domain' => 'general',
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

    /**
     * Régression critique :
     *
     *   "Un slot couvert par le plan ne doit JAMAIS appeler l'IA en cours
     *    de partie, même en cas de shortage."
     *
     * On exerce la méthode privée `renderFromPlanOrFallback` du pipeline en
     * passant chaque type de slot que le planner peut produire (groupe, seed,
     * stub de shortage, slot null) ainsi qu'un index hors plan, en injectant
     * un faux QuestionService qui jette une exception si on l'appelle.
     *
     *   - Pour TOUS les indices à l'intérieur du plan : aucun appel au
     *     QuestionService (= aucun appel IA possible).
     *   - Pour un index HORS plan (bonus skill / tiebreaker) : le fallback
     *     historique (qui peut appeler l'IA) est bien sollicité.
     */
    public function test_pipeline_never_calls_ai_for_planned_slots_even_on_shortage(): void
    {
        $this->seedRichBank();

        // QuestionService mock : son generateQuestion() compte les appels
        // pour qu'on puisse asserter "0 appel" sur les slots planifiés.
        // On étend la vraie classe pour respecter le type-hint strict de la
        // propriété $questionService du pipeline.
        $mockService = new class extends QuestionService {
            public int $calls = 0;
            public function __construct() {} // bypass parent ctor
            public function generateQuestion(
                $theme,
                $niveau,
                $questionNumber,
                $usedQuestionIds = [],
                $usedAnswers = [],
                $sessionUsedAnswers = [],
                $sessionUsedQuestionTexts = [],
                $opponentAge = null,
                $isBoss = false,
                $language = 'fr',
                $skipCache = false,
                $context = 'solo'
            ) {
                $this->calls++;
                return [
                    'id'             => 'fake_ai_q',
                    'group_id'       => null,
                    'type'           => 'multiple',
                    'question_text'  => 'AI generated',
                    'text'           => 'AI generated',
                    'answers'        => ['a', 'b', 'c', 'd'],
                    'correct_index'  => 0,
                    'correct_id'     => 0,
                    'theme'          => 'general',
                    'sub_theme'      => null,
                    'language'       => 'fr',
                ];
            }
        };

        $pipeline = new GameServerQuestionPipeline();
        $refl = new \ReflectionClass($pipeline);
        $svcProp = $refl->getProperty('questionService');
        $svcProp->setAccessible(true);
        $svcProp->setValue($pipeline, $mockService);

        // On construit un plan riche (30 slots, tous des groupes), puis on
        // injecte un slot seed et un slot stub à des positions précises pour
        // couvrir les 3 cas du plan.
        $planner = new FlatPlanner();
        $plan = $planner->buildPlan('duo', 'intermediaire', 30, 3, 'fr', ['domain' => 'general']);
        $slots = $plan['ordered_questions'];

        // Forcer un slot "from_seed" et un slot "shortage stub" pour bien
        // couvrir tous les cas planifiés.
        $slots[10] = [
            'id'             => 'seed_xyz',
            'group_id'       => null,
            'type'           => 'multiple',
            'question_text'  => 'Une question seed pré-rendue',
            'text'           => 'Une question seed pré-rendue',
            'answers'        => ['a', 'b', 'c', 'd'],
            'correct_index'  => 1,
            'correct_id'     => 1,
            'theme'          => 'general',
            'sub_theme'      => 'histoire',
            'language'       => 'fr',
            'from_seed'      => true,
        ];
        $slots[20] = [
            'id'             => null,
            'group_id'       => null,
            'shortage'       => true,
            'cognitive_type' => 'reasoning',
            'sub_theme'      => 'sport',
            'language'       => 'fr',
        ];

        $renderMethod = $refl->getMethod('renderFromPlanOrFallback');
        $renderMethod->setAccessible(true);

        // Sert tous les indices dans le plan : 1..30. AUCUN appel IA attendu.
        $served = [];
        for ($n = 1; $n <= 30; $n++) {
            $q = $renderMethod->invoke($pipeline, $slots, $n, 'fr', 'general', 50, [], []);
            $this->assertNotNull($q, "Slot planifié #{$n} doit être servi sans appel IA");
            $this->assertNotEmpty($q['question_text'] ?? $q['text'] ?? null, "Slot #{$n} doit avoir un texte");
            $served[] = $q;
        }

        $this->assertSame(
            0,
            $mockService->calls,
            'Le pipeline ne doit JAMAIS appeler QuestionService::generateQuestion sur un slot couvert par le plan.'
        );

        // Vérifie le slot seed et le slot stub spécifiquement.
        $this->assertTrue(($served[10]['from_seed'] ?? false) === true);
        $this->assertTrue(
            ($served[20]['shortage'] ?? false) === true || ($served[20]['from_plan_stub'] ?? false) === true,
            'Le slot 21 (shortage stub) doit être servi comme stub déterministe.'
        );

        // Maintenant un index HORS plan (bonus skill, par ex. n°31) :
        // le fallback historique DOIT bien être sollicité.
        $offPlan = $renderMethod->invoke($pipeline, $slots, 31, 'fr', 'general', 50, [], []);
        $this->assertNotNull($offPlan);
        $this->assertSame(
            1,
            $mockService->calls,
            'Pour un index hors plan (bonus skill / tiebreaker), le pipeline doit retomber sur QuestionService.'
        );
    }
}
