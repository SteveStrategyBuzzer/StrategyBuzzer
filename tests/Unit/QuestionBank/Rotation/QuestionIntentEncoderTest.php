<?php

declare(strict_types=1);

namespace Tests\Unit\QuestionBank\Rotation;

use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Rotation\QuestionIntentEncoder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * QuestionIntentEncoder — RACCORDEMENT A (flow canonique 2026-08-11).
 *
 * Couvre : encodage complet + mapping legacy kernel_core, idempotence par
 * blueprint_id, STOP explicite sur identité intellectuelle incomplète.
 *
 * DB : SQLite in-memory, table créée manuellement (PATTERN sans RefreshDatabase).
 */
class QuestionIntentEncoderTest extends TestCase
{
    private QuestionIntentEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('question_intents', function (Blueprint $table) {
            $table->id();
            $table->string('intent_key')->unique();
            $table->string('semantic_key', 255)->nullable();
            $table->string('language_source', 8)->default('en');
            $table->string('domain', 64);
            $table->string('sub_domain', 256);
            $table->unsignedTinyInteger('difficulty_depth');
            $table->string('subject', 256)->nullable();
            $table->string('dominant_idea', 512)->nullable();
            $table->string('angle_large', 512)->nullable();
            $table->string('micro_angle', 512)->nullable();
            $table->text('answer_target')->nullable();
            $table->string('concept_family', 256)->nullable();
            $table->string('source', 32)->default('ai_pipeline');
            $table->string('frame_status', 32)->nullable();
            $table->char('blueprint_id', 36)->nullable()->unique();
            $table->unsignedTinyInteger('advance_attempts')->default(0);
            $table->timestamps();
        });

        $this->encoder = new QuestionIntentEncoder();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('question_intents');
        parent::tearDown();
    }

    private function makeBlueprint(): KernelBlueprint
    {
        $bp               = new KernelBlueprint();
        $bp->blueprint_id = 'bp-enc-0001';
        $bp->fillRotation(4, 'histoire');
        $bp->fillTaxonomy(
            'Époque contemporaine',
            'Guerre froide',
            'La guerre froide oppose deux blocs sans affrontement direct',
        );

        return $bp;
    }

    // =========================================================================
    // Encodage nominal + mapping legacy
    // =========================================================================

    public function test_encode_creates_intent_row_with_canonical_mapping(): void
    {
        $id = $this->encoder->encode($this->makeBlueprint());

        $row = DB::table('question_intents')->find($id);

        $this->assertNotNull($row);
        $this->assertSame('BP:bp-enc-0001', $row->intent_key);
        $this->assertSame('BP:bp-enc-0001', $row->semantic_key);
        $this->assertSame('histoire', $row->domain);
        $this->assertSame('Époque contemporaine', $row->sub_domain);
        $this->assertSame(4, (int) $row->difficulty_depth);
        $this->assertSame('Guerre froide', $row->subject);
        $this->assertSame('La guerre froide oppose deux blocs sans affrontement direct', $row->dominant_idea);

        // Mapping legacy kernel_core : l'idée dominante EST l'angle / la cible.
        $this->assertSame($row->dominant_idea, $row->angle_large);
        $this->assertSame($row->dominant_idea, $row->micro_angle);
        $this->assertSame($row->dominant_idea, $row->answer_target);
        $this->assertSame('Époque contemporaine', $row->concept_family);

        $this->assertSame(QuestionIntentEncoder::SOURCE, $row->source);
        $this->assertSame('bp-enc-0001', $row->blueprint_id);
        $this->assertNull($row->frame_status, 'frame_status démarre NULL — Phase 1 pas encore lancée');
    }

    // =========================================================================
    // Idempotence par blueprint_id
    // =========================================================================

    public function test_encode_is_idempotent_per_blueprint(): void
    {
        $first  = $this->encoder->encode($this->makeBlueprint());
        $second = $this->encoder->encode($this->makeBlueprint());

        $this->assertSame($first, $second, 'Ré-encoder le même Blueprint retourne le même intent');
        $this->assertSame(1, (int) DB::table('question_intents')->count(), 'Aucune ligne dupliquée');
    }

    // =========================================================================
    // STOP explicite — identité intellectuelle incomplète
    // =========================================================================

    public function test_encode_stops_when_dominant_idea_missing(): void
    {
        $bp               = new KernelBlueprint();
        $bp->blueprint_id = 'bp-enc-0002';
        $bp->fillRotation(4, 'histoire');
        $bp->fillTaxonomy('Époque contemporaine', 'Guerre froide', ''); // idée absente

        try {
            $this->encoder->encode($bp);
            $this->fail('encode() doit STOPper si dominant_idea_active manque');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('dominant_idea_active', $e->getMessage());
        }

        $this->assertSame(
            0,
            (int) DB::table('question_intents')->count(),
            'Aucun encodage partiel silencieux'
        );
    }

    public function test_encode_stops_when_rotation_not_filled(): void
    {
        $bp               = new KernelBlueprint();
        $bp->blueprint_id = 'bp-enc-0003';
        // Ni fillRotation ni fillTaxonomy

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/depth.*domain.*subdomain_active/s');

        $this->encoder->encode($bp);
    }
}
