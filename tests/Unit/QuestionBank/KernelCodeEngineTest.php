<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank;

use App\Exceptions\QuestionBank\KernelCodeEngineException;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\KernelCodeEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Tests officiels de KernelCodeEngine (05_QuestionIntent — VERROUILLÉ).
 *
 * Couvre : format, normalisation, base36, domaines, bassins, idempotence,
 * immutabilité, exhaustion, non-régression, concurrence (séquentielle).
 *
 * NOTE : tests SQLite — lockForUpdate() est un no-op, mais la logique
 * transactionnelle reste entièrement testée.
 */
class KernelCodeEngineTest extends TestCase
{
    private KernelCodeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new KernelCodeEngine();

        // ── Schéma SQLite pour les tests DB ──────────────────────────────────
        Schema::create('kernel_blueprint_runs', function (Blueprint $t) {
            $t->string('blueprint_id', 36)->primary();
            $t->string('execution_state', 64)->default('CREATED_UNENGAGED');
            $t->smallInteger('depth')->nullable();
            $t->string('domain_code', 64)->nullable();
            $t->string('kernel_code', 23)->nullable()->unique();
            $t->timestamp('engaged_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->timestamps();
        });

        Schema::create('kernel_code_sequences', function (Blueprint $t) {
            $t->unsignedSmallInteger('depth');
            $t->char('domain_code', 3);
            $t->unsignedInteger('next_value')->default(0);
            $t->timestamps();
            $t->primary(['depth', 'domain_code']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('kernel_code_sequences');
        Schema::dropIfExists('kernel_blueprint_runs');
        parent::tearDown();
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════════

    private function makeBlueprint(
        string  $blueprintId     = 'bp-test-0001',
        int     $depth           = 4,
        string  $domain          = 'Géographie',
        string  $subdomain       = 'Canada',
        string  $subject         = 'Confédération canadienne',
        string  $dominantIdea    = 'Acte de l\'Amérique du Nord britannique',
        ?string $existingCode    = null,
    ): KernelBlueprint {
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId($blueprintId);
        $bp->fillRotation($depth, $domain);
        $bp->fillTaxonomy($subdomain, $subject, $dominantIdea);

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => $blueprintId,
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'depth'           => $depth,
            'domain_code'     => $domain,
            'kernel_code'     => $existingCode,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        return $bp;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1. normalizeSegment — fonction pure
    // ═════════════════════════════════════════════════════════════════════════

    public function test_normalize_canada(): void
    {
        $this->assertSame('CAN', $this->engine->normalizeSegment('Canada'));
    }

    public function test_normalize_confederation_canadienne(): void
    {
        $this->assertSame('CON', $this->engine->normalizeSegment('Confédération canadienne'));
    }

    public function test_normalize_acte_amerique(): void
    {
        $this->assertSame('ACT', $this->engine->normalizeSegment("Acte de l'Amérique du Nord britannique"));
    }

    public function test_normalize_etats_unis(): void
    {
        $this->assertSame('ETA', $this->engine->normalizeSegment('États-Unis'));
    }

    public function test_normalize_accents(): void
    {
        $this->assertSame('EEA', $this->engine->normalizeSegment('éèà'));
    }

    public function test_normalize_cedille(): void
    {
        $this->assertSame('CAC', $this->engine->normalizeSegment('çaça'));
    }

    public function test_normalize_apostrophe_removed(): void
    {
        // "L'art" → "LART" → "LAR"
        $this->assertSame('LAR', $this->engine->normalizeSegment("L'art"));
    }

    public function test_normalize_numbers_kept(): void
    {
        $this->assertSame('123', $this->engine->normalizeSegment('123abc'));
    }

    public function test_normalize_short_pads_with_x(): void
    {
        $this->assertSame('PIX', $this->engine->normalizeSegment('Pi'));
    }

    public function test_normalize_single_char_pads(): void
    {
        $this->assertSame('AXX', $this->engine->normalizeSegment('a'));
    }

    public function test_normalize_empty_after_strip_throws(): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_INVALID_SEGMENT');
        $this->engine->normalizeSegment('---');
    }

    public function test_normalize_pure_spaces_throws(): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_INVALID_SEGMENT');
        $this->engine->normalizeSegment('   ');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2. toBase36 — fonction pure
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider base36Provider */
    public function test_to_base36(int $input, string $expected): void
    {
        $this->assertSame($expected, $this->engine->toBase36($input));
    }

    public static function base36Provider(): array
    {
        return [
            [0,        '0000'],
            [1,        '0001'],
            [9,        '0009'],
            [10,       '000A'],  // 0009 → 000A (A = 10 en base36)
            [35,       '000Z'],  // Z = 35
            [36,       '0010'],  // 000Z → 0010
            [1295,     '00ZZ'],  // 36*36-1 = 1295
            [1296,     '0100'],  // 36*36 = 1296
            [46655,    '0ZZZ'],  // 36^3-1 = 46655
            [46656,    '1000'],  // 36^3 = 46656
            [1679614,  'ZZZY'],
            [1679615,  'ZZZZ'],  // MAX = 36^4 - 1
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3. resolveDomainCode — 8 domaines officiels
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider domainProvider */
    public function test_resolve_domain_code(string $domain, string $expected): void
    {
        $this->assertSame($expected, $this->engine->resolveDomainCode($domain));
    }

    public static function domainProvider(): array
    {
        return [
            ['Géographie', 'GEO'],
            ['Histoire',   'HIS'],
            ['Faune',      'FAU'],
            ['Art',        'ART'],
            ['Sport',      'SPO'],
            ['Cinéma',     'CIN'],
            ['Cuisine',    'CUI'],
            ['Science',    'SCI'],
        ];
    }

    public function test_general_rejected(): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_INVALID_DOMAIN');
        $this->engine->resolveDomainCode('Général');
    }

    public function test_unknown_domain_rejected(): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_INVALID_DOMAIN');
        $this->engine->resolveDomainCode('Inconnu');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4. resolveDepth
    // ═════════════════════════════════════════════════════════════════════════

    /** @dataProvider depthProvider */
    public function test_resolve_depth_valid(int $depth, string $expected): void
    {
        $this->assertSame($expected, $this->engine->resolveDepth($depth));
    }

    public static function depthProvider(): array
    {
        return [
            [2,  '02'],
            [4,  '04'],
            [6,  '06'],
            [7,  '07'],
            [8,  '08'],
            [9,  '09'],
            [10, '10'],
        ];
    }

    /** @dataProvider invalidDepthProvider */
    public function test_resolve_depth_invalid_throws(int $depth): void
    {
        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_INVALID_DEPTH');
        $this->engine->resolveDepth($depth);
    }

    public static function invalidDepthProvider(): array
    {
        return [[1], [3], [5], [11], [0], [99]];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5. assignKernelCode — intégration DB
    // ═════════════════════════════════════════════════════════════════════════

    public function test_assign_produces_correct_format(): void
    {
        $bp = $this->makeBlueprint();
        $code = $this->engine->assignKernelCode($bp);

        // Format structurel
        $this->assertMatchesRegularExpression(KernelCodeEngine::FORMAT_REGEX, $code);
        $this->assertSame(23, strlen($code));
        $this->assertSame('04-GEO-CAN-CON-ACT-0000', $code);
        $this->assertSame($code, $bp->kernel_code);
    }

    public function test_assign_writes_to_db(): void
    {
        $bp = $this->makeBlueprint();
        $this->engine->assignKernelCode($bp);

        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-test-0001')->first();
        $this->assertSame('04-GEO-CAN-CON-ACT-0000', $row->kernel_code);
    }

    public function test_idempotence_same_blueprint_twice(): void
    {
        $bp = $this->makeBlueprint();
        $code1 = $this->engine->assignKernelCode($bp);

        // Remettre le Blueprint en mémoire (simulate new call)
        $bp2 = new KernelBlueprint();
        $bp2->initializeBlueprintId('bp-test-0001');
        $bp2->fillRotation(4, 'Géographie');
        $bp2->fillTaxonomy('Canada', 'Confédération canadienne', "Acte de l'Amérique du Nord britannique");

        $code2 = $this->engine->assignKernelCode($bp2);

        $this->assertSame($code1, $code2);
        $this->assertSame($code2, $bp2->kernel_code);

        // Le compteur ne doit avoir avancé qu'une seule fois
        $seq = DB::table('kernel_code_sequences')
            ->where('depth', 4)->where('domain_code', 'GEO')->first();
        $this->assertSame(1, (int) $seq->next_value);
    }

    public function test_counter_advances_between_assignments(): void
    {
        $bp1 = $this->makeBlueprint('bp-001');
        $bp2 = $this->makeBlueprint('bp-002');

        $code1 = $this->engine->assignKernelCode($bp1);
        $code2 = $this->engine->assignKernelCode($bp2);

        $this->assertStringEndsWith('-0000', $code1);
        $this->assertStringEndsWith('-0001', $code2);
    }

    public function test_basin_independence_ge_vs_hi(): void
    {
        $bpGe = $this->makeBlueprint('bp-ge', 2, 'Géographie', 'Europe', 'France', 'Gaule');
        $bpHi = $this->makeBlueprint('bp-hi', 2, 'Histoire', 'Antiquité', 'Rome', 'César');

        $codeGe = $this->engine->assignKernelCode($bpGe);
        $codeHi = $this->engine->assignKernelCode($bpHi);

        // Chaque bassin commence à 0000
        $this->assertStringEndsWith('-0000', $codeGe);
        $this->assertStringEndsWith('-0000', $codeHi);
        $this->assertStringContainsString('02-GEO-', $codeGe);
        $this->assertStringContainsString('02-HIS-', $codeHi);
    }

    public function test_basin_independence_depth_differs(): void
    {
        $bp4 = $this->makeBlueprint('bp-d4', 4, 'Géographie', 'Europe', 'France', 'Gaule');
        $bp6 = $this->makeBlueprint('bp-d6', 6, 'Géographie', 'Europe', 'France', 'Gaule');

        $code4 = $this->engine->assignKernelCode($bp4);
        $code6 = $this->engine->assignKernelCode($bp6);

        $this->assertStringEndsWith('-0000', $code4);
        $this->assertStringEndsWith('-0000', $code6);
        $this->assertStringStartsWith('04-GEO-', $code4);
        $this->assertStringStartsWith('06-GEO-', $code6);
    }

    public function test_immutability_cannot_reassign(): void
    {
        $bp = $this->makeBlueprint('bp-imm', existingCode: '04-GEO-CAN-CON-ACT-000A');
        $code = $this->engine->assignKernelCode($bp);

        // Retourne l'existant, ne consomme pas de nouveau suffixe
        $this->assertSame('04-GEO-CAN-CON-ACT-000A', $code);
        $this->assertNull(
            DB::table('kernel_code_sequences')
                ->where('depth', 4)->where('domain_code', 'GEO')->first()
        );
    }

    public function test_missing_blueprint_throws(): void
    {
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId('bp-inexistant');
        $bp->fillRotation(4, 'Géographie');
        $bp->fillTaxonomy('Canada', 'Sujet', 'Idée');

        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_MISSING_INPUT');
        $this->engine->assignKernelCode($bp);
    }

    public function test_missing_rotation_throws(): void
    {
        // Rotation non remplie (depth + domain = null) → engine doit refuser.
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId('bp-miss-rot');
        $bp->fillTaxonomy('Canada', 'Sujet', 'Idée');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'bp-miss-rot',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'kernel_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_MISSING_INPUT');
        $this->engine->assignKernelCode($bp);
    }

    public function test_missing_taxonomy_throws(): void
    {
        // Taxonomy non remplie (3 slots = null) → engine doit refuser.
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId('bp-miss-tax');
        $bp->fillRotation(4, 'Géographie');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'bp-miss-tax',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'kernel_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_MISSING_INPUT');
        $this->engine->assignKernelCode($bp);
    }

    public function test_invalid_domain_throws_and_no_suffix_consumed(): void
    {
        $bp = new KernelBlueprint();
        $bp->initializeBlueprintId('bp-dom');
        $bp->fillRotation(4, 'Général');
        $bp->fillTaxonomy('Canada', 'Sujet', 'Idée');

        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id'    => 'bp-dom',
            'execution_state' => 'ENGAGED_IN_PIPELINE',
            'kernel_code'     => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        try {
            $this->engine->assignKernelCode($bp);
            $this->fail('Exception attendue');
        } catch (KernelCodeEngineException $e) {
            $this->assertSame(KernelCodeEngineException::INVALID_DOMAIN, $e->errorCode);
        }

        // Aucun suffixe consommé
        $this->assertSame(0, DB::table('kernel_code_sequences')->count());
    }

    public function test_suffix_exhaustion_throws(): void
    {
        $bp = $this->makeBlueprint();

        // Forcer le bassin au-delà de ZZZZ
        DB::table('kernel_code_sequences')->insert([
            'depth'       => 4,
            'domain_code' => 'GEO',
            'next_value'  => KernelCodeEngine::MAX_SUFFIX + 1,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('QUESTION_INTENT_SUFFIX_EXHAUSTED');
        $this->engine->assignKernelCode($bp);
    }

    public function test_legacy_two_character_basin_blocks_new_allocation(): void
    {
        $bp = $this->makeBlueprint();

        DB::table('kernel_code_sequences')->insert([
            'depth'       => 4,
            'domain_code' => 'GE',
            'next_value'  => 42,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $this->expectException(KernelCodeEngineException::class);
        $this->expectExceptionMessage('réconciliation requise');
        $this->engine->assignKernelCode($bp);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6. Capacité — §35
    // ═════════════════════════════════════════════════════════════════════════

    public function test_capacity_last_valid_suffix(): void
    {
        $this->assertSame('ZZZZ', $this->engine->toBase36(KernelCodeEngine::MAX_SUFFIX));
    }

    public function test_capacity_max_is_36_pow_4_minus_1(): void
    {
        $this->assertSame(1_679_615, KernelCodeEngine::MAX_SUFFIX);
        $this->assertSame(1_679_616, (int) pow(36, 4));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 7. Non-régression — §36 : les 5 champs du Blueprint sont inchangés
    // ═════════════════════════════════════════════════════════════════════════

    public function test_non_regression_blueprint_fields_unchanged(): void
    {
        $bp = $this->makeBlueprint();

        $before = [
            'depth'                => $bp->depth,
            'domain'               => $bp->domain,
            'subdomain_active'     => $bp->subdomain_active,
            'subject_active'       => $bp->subject_active,
            'dominant_idea_active' => $bp->dominant_idea_active,
        ];

        $this->engine->assignKernelCode($bp);

        $this->assertSame($before['depth'],                $bp->depth);
        $this->assertSame($before['domain'],               $bp->domain);
        $this->assertSame($before['subdomain_active'],     $bp->subdomain_active);
        $this->assertSame($before['subject_active'],       $bp->subject_active);
        $this->assertSame($before['dominant_idea_active'], $bp->dominant_idea_active);
        // Seul kernel_code a changé
        $this->assertNotNull($bp->kernel_code);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 8. Legacy guard — §34
    // ═════════════════════════════════════════════════════════════════════════

    public function test_no_ks_hash_written(): void
    {
        $bp = $this->makeBlueprint();
        $this->engine->assignKernelCode($bp);

        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-test-0001')->first();
        // kernel_blueprint_runs n'a pas de colonne ks_hash — vérifier que le code ne tente pas d'y écrire
        $this->assertFalse(isset($row->ks_hash), 'ks_hash ne doit pas exister dans kernel_blueprint_runs');
    }

    public function test_no_kld_hash_written(): void
    {
        $bp = $this->makeBlueprint();
        $this->engine->assignKernelCode($bp);

        $row = DB::table('kernel_blueprint_runs')->where('blueprint_id', 'bp-test-0001')->first();
        $this->assertFalse(isset($row->kld_hash), 'kld_hash ne doit pas exister dans kernel_blueprint_runs');
    }
}
