<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\QuestionBank\KernelBlueprint;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminReadService;
use App\Services\QuestionBank\Quarantine\KernelQuarantineAdminService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuarantineAdminHttpTest extends TestCase
{
    use DatabaseTransactions;

    private string $blueprintId;
    private array $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blueprintId = (string) Str::orderedUuid();
        $suffix = strtoupper(substr(str_replace('-', '', $this->blueprintId), -4));
        DB::table('kernel_blueprint_runs')->insert([
            'blueprint_id' => $this->blueprintId, 'execution_state' => 'READY_BANK_RECEIVED',
            'depth' => 6, 'domain_code' => 'SCI', 'subdomain_active' => 'Physics',
            'subject_active' => 'Light', 'dominant_idea_active' => 'Refraction',
            'kernel_code_dd' => '06', 'kernel_code_do' => 'SCI', 'kernel_code_sub' => 'PHY',
            'kernel_code_suj' => 'LIG', 'kernel_code_ide' => 'REF', 'kernel_code_vvvv' => $suffix,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (KernelBlueprint::COGNITIVE_TYPES as $type) {
            DB::table('kernel_blueprint_cognitive_slots')->insert([
                'blueprint_id' => $this->blueprintId, 'cognitive_type' => $type,
                'source' => json_encode($this->source($type), JSON_THROW_ON_ERROR),
                'translations' => json_encode($this->translations($type), JSON_THROW_ON_ERROR),
                'creation_status' => 'CREATED', 'validation_status' => 'PASS',
                'validation_findings' => '[]', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('kernel_blueprint_cognitive_slots')->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['validation_status' => 'SUSPICION', 'validation_findings' => '["MEANING_DRIFT"]']);
        $this->copy = (new KernelQuarantineAdminService())->createCopyFromRequest(
            $this->blueprintId, 'VALIDATION_PHASE1', 'http-fixture-' . $this->blueprintId,
            'SUSPICION', ['finding' => 'MEANING_DRIFT'],
        );
    }

    public function test_quarantine_pages_require_authenticated_admin(): void
    {
        $this->get('/admin/questions/quarantine')->assertRedirect('/login');
        $this->get('/admin/questions/quarantine/not-a-copy')->assertRedirect('/login');

        $user = $this->makeUser('quarantine-non-admin');
        $this->actingAs($user)->get('/admin/questions/quarantine')->assertForbidden();
    }

    public function test_quarantine_mutation_forms_include_csrf_tokens(): void
    {
        $admin = $this->makeAdmin('quarantine-admin');

        $this->actingAs($admin)
            ->get('/admin/questions/quarantine/' . $this->copy['copy_id'])
            ->assertOk()
            ->assertSee('name="_token"', false);
    }

    public function test_admin_route_contract_is_web_auth_admin_and_mutations_are_post_only(): void
    {
        $routes = app('router')->getRoutes();
        foreach ([
            'admin.quarantine.index' => ['GET', 'HEAD'],
            'admin.quarantine.show' => ['GET', 'HEAD'],
            'admin.quarantine.claim' => ['POST'],
            'admin.quarantine.source' => ['POST'],
            'admin.quarantine.translation' => ['POST'],
            'admin.quarantine.regenerate' => ['POST'],
            'admin.quarantine.ready' => ['POST'],
            'admin.quarantine.return-owner' => ['POST'],
        ] as $name => $methods) {
            $route = $routes->getByName($name);
            self::assertNotNull($route);
            self::assertSame($methods, $route->methods());
            self::assertStringStartsWith('admin/questions/quarantine', $route->uri());
            self::assertContains('auth', $route->gatherMiddleware());
            self::assertContains('admin', $route->gatherMiddleware());
            self::assertContains('web', $route->gatherMiddleware());
        }
    }

    public function test_empty_projection_contains_no_internal_claim_or_hash_fields(): void
    {
        $reader = new KernelQuarantineAdminReadService();
        $page = $reader->queue();

        foreach ($page->items() as $row) {
            self::assertSame([], array_intersect([
                'claim_token', 'active_claim_token', 'request_hash', 'intent_hash',
            ], array_keys($row)));
        }
        self::assertNull($reader->detail('missing-copy'));
    }

    public function test_admin_can_read_queue_and_complete_detail_projection_safely(): void
    {
        $admin = $this->makeAdmin('queue-detail-admin');
        $this->actingAs($admin);

        $this->get('/admin/questions/quarantine')
            ->assertOk()
            ->assertSee('1 copie')
            ->assertDontSee('1 copie(s)')
            ->assertSee('7 slots')
            ->assertSee('9 langues')
            ->assertSee('Examiner')
            ->assertDontSee('QCM_RECOGNITION, QCM_TRAP');
        $response = $this->get('/admin/questions/quarantine/' . $this->copy['copy_id']);
        $response->assertOk()
            ->assertSee('name="expected_translation_revision" value="1"', false)
            ->assertSee('name="expected_manual_revision" value="0"', false)
            ->assertSee('Clé canonique · lecture seule')
            ->assertDontSee('name="source[correct_answer_key]"', false)
            ->assertDontSee('name="translation[correct_answer_key]"', false);

        $detail = (new KernelQuarantineAdminReadService())->detail($this->copy['copy_id']);
        self::assertNotNull($detail);
        $queue = (new KernelQuarantineAdminReadService())->queue();
        self::assertSame(1, $queue->total(), 'A seven-slot copy must occupy one FIFO row.');
        self::assertCount(1, $queue->items());
        self::assertCount(7, $detail['slots']);
        foreach ($detail['slots'] as $slot) {
            self::assertCount(9, $slot['translations']);
        }
        self::assertSame(
            [],
            $this->forbiddenKeys($detail),
            'The Admin projection must not expose claims or durable hashes.',
        );
    }

    public function test_missing_translation_still_has_correction_and_regeneration_controls(): void
    {
        $slot = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $this->copy['copy_id'])
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->first();
        $translations = json_decode((string) $slot->translations, true, 512, JSON_THROW_ON_ERROR);
        $translations['el'] = null;
        DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $this->copy['copy_id'])
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->update(['translations' => json_encode($translations, JSON_THROW_ON_ERROR)]);

        $this->actingAs($this->makeAdmin('missing-translation-admin'))
            ->get('/admin/questions/quarantine/' . $this->copy['copy_id'])
            ->assertOk()
            ->assertSee('name="language_code" value="el"', false)
            ->assertSee('Régénérer EL');
    }

    public function test_admin_claim_is_session_only_and_ready_action_delegates_to_backend(): void
    {
        $admin = $this->makeAdmin('claim-ready-admin');
        $token = Str::uuid()->toString();
        $detailUrl = '/admin/questions/quarantine/' . $this->copy['copy_id'];
        $this->actingAs($admin)->withSession(['_token' => $token]);

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('data-action="claim" data-enabled="false"', false)
            ->assertSee('data-action="ready" data-enabled="true"', false)
            ->assertSee('data-action="return-owner" data-enabled="false"', false);

        $this
            ->from('/admin/questions/quarantine/' . $this->copy['copy_id'])
            ->post('/admin/questions/quarantine/' . $this->copy['copy_id'] . '/ready', [
                '_token' => $token, 'copy_version' => 1,
            ])->assertRedirect();

        self::assertSame('READY', DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $this->copy['copy_id'])->value('state'));

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('data-action="claim" data-enabled="true"', false)
            ->assertSee('data-action="ready" data-enabled="true"', false)
            ->assertSee('data-action="return-owner" data-enabled="false"', false);

        $this->from($detailUrl)
            ->post($detailUrl . '/claim', ['copy_version' => 1])
            ->assertRedirect($detailUrl)
            ->assertSessionHas('admin_quarantine_claim.' . $this->copy['copy_id']);

        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('data-action="claim" data-enabled="false"', false)
            ->assertSee('data-action="ready" data-enabled="false"', false)
            ->assertSee('data-action="return-owner" data-enabled="true"', false);
    }

    public function test_admin_can_claim_correct_regenerate_and_return_without_canonical_write(): void
    {
        $admin = $this->makeAdmin('full-action-admin');
        $copyId = $this->copy['copy_id'];
        $detailUrl = '/admin/questions/quarantine/' . $copyId;
        $canonicalBefore = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('source');
        $canonicalRevision = (int) DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('canonical_revision');

        $this->actingAs($admin)->from($detailUrl)
            ->post($detailUrl . '/ready', ['copy_version' => 1])
            ->assertRedirect($detailUrl);

        $claimResponse = $this->from($detailUrl)
            ->post($detailUrl . '/claim', ['copy_version' => 1])
            ->assertRedirect($detailUrl)
            ->assertSessionHas('admin_quarantine_claim.' . $copyId);
        $claim = session('admin_quarantine_claim.' . $copyId);
        self::assertIsString($claim);
        self::assertNotSame('', $claim);
        self::assertStringNotContainsString($claim, $claimResponse->getContent());
        self::assertSame('IN_FLIGHT', DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->value('state'));

        $source = $this->source('QCM_RECOGNITION');
        $source['question'] = 'Which colour is refracted?';
        unset($source['correct_answer_key']);
        $this->from($detailUrl)->post($detailUrl . '/source', [
            'copy_version' => 1,
            'cognitive_type' => 'QCM_RECOGNITION',
            'manual_revision' => 0,
            'canonical_base_revision' => $canonicalRevision,
            'source' => $source,
        ])->assertRedirect($detailUrl);
        self::assertSame(2, (int) DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->value('copy_version'));

        $translation = $this->translations('QCM_RECOGNITION')['fr'];
        $translation['question'] = 'Quelle couleur est réfractée ?';
        unset($translation['correct_answer_key']);
        $this->from($detailUrl)->post($detailUrl . '/translation', [
            'copy_version' => 2,
            'cognitive_type' => 'QCM_RECOGNITION',
            'language_code' => 'fr',
            'manual_revision' => 1,
            'canonical_base_revision' => $canonicalRevision,
            'translation' => $translation,
        ])->assertRedirect($detailUrl);
        self::assertSame(3, (int) DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->value('copy_version'));

        $this->from($detailUrl)->post($detailUrl . '/regenerate', [
            'copy_version' => 3,
            'owner_phase' => 'VALIDATION_PHASE1',
            'operation' => 'REGENERATE_TRANSLATION',
            'cognitive_type' => 'QCM_RECOGNITION',
            'language_code' => 'fr',
            'idempotency_key' => 'http-regenerate-' . $copyId,
            'expected_manual_revision' => 2,
        ])->assertRedirect($detailUrl);
        self::assertSame(1, DB::table('kernel_quarantine_resume_intents')
            ->where('copy_id', $copyId)->where('operation', 'REGENERATE_TRANSLATION')->count());

        $this->from($detailUrl)->post($detailUrl . '/return-owner', [
            'copy_version' => 3,
            'owner_phase' => 'VALIDATION_PHASE1',
            'operation' => 'RESUME_VALIDATION_PHASE1',
            'idempotency_key' => 'http-return-' . $copyId,
        ])->assertRedirect($detailUrl);
        self::assertSame(1, DB::table('kernel_quarantine_resume_intents')
            ->where('copy_id', $copyId)->where('operation', 'RESUME_VALIDATION_PHASE1')->count());
        $this->get($detailUrl)
            ->assertOk()
            ->assertSee('Intentions de reprise')
            ->assertSee('RESUME_VALIDATION_PHASE1');

        self::assertSame($canonicalBefore, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprintId)
            ->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('source'));
    }

    public function test_true_false_content_corrections_preserve_answer_key_and_polarity(): void
    {
        $admin = $this->makeAdmin('answer-key-preservation-admin');
        $copyId = $this->copy['copy_id'];
        $detailUrl = '/admin/questions/quarantine/' . $copyId;
        $type = 'TRUE_FALSE_RECOGNITION_FALSE';
        $slot = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->first();
        $beforeSource = json_decode((string) $slot->source, true, 512, JSON_THROW_ON_ERROR);
        $beforeTranslations = json_decode((string) $slot->translations, true, 512, JSON_THROW_ON_ERROR);
        $source = $beforeSource;
        $source['question'] = 'The corrected statement is false.';
        $source['choices']['a'] = 'Corrected true';
        $source['sv'] = 'Corrected source fact.';
        unset($source['correct_answer_key']);

        $this->actingAs($admin)->from($detailUrl)->post($detailUrl . '/source', [
            'copy_version' => 1,
            'cognitive_type' => $type,
            'manual_revision' => 0,
            'canonical_base_revision' => (int) $slot->canonical_base_revision,
            'source' => $source,
        ])->assertRedirect($detailUrl);

        $afterSourceRow = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->first();
        $afterSource = json_decode((string) $afterSourceRow->source, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('b', $beforeSource['correct_answer_key']);
        self::assertSame('b', $afterSource['correct_answer_key']);

        $translations = json_decode((string) $afterSourceRow->translations, true, 512, JSON_THROW_ON_ERROR);
        $translation = $translations['fr'];
        $translation['question'] = 'L’énoncé corrigé est faux.';
        $translation['choices']['b'] = 'Faux corrigé';
        $translation['sv'] = 'Fait traduit corrigé.';
        unset($translation['correct_answer_key']);

        $this->from($detailUrl)->post($detailUrl . '/translation', [
            'copy_version' => 1,
            'cognitive_type' => $type,
            'language_code' => 'fr',
            'manual_revision' => 1,
            'canonical_base_revision' => (int) $slot->canonical_base_revision,
            'translation' => $translation,
        ])->assertRedirect($detailUrl);

        $afterTranslationRow = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->first();
        $afterTranslations = json_decode((string) $afterTranslationRow->translations, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('b', $afterTranslations['fr']['correct_answer_key']);
        self::assertSame(
            $beforeTranslations['fr']['translation_revision'] + 1,
            $afterTranslations['fr']['translation_revision'],
        );
        self::assertSame('NOT_VALIDATED', $afterTranslations['fr']['validation_status']);
    }

    public function test_forged_answer_key_changes_are_rejected_by_http_boundary(): void
    {
        $admin = $this->makeAdmin('forged-answer-key-admin');
        $copyId = $this->copy['copy_id'];
        $detailUrl = '/admin/questions/quarantine/' . $copyId;
        $type = 'TRUE_FALSE_RECOGNITION_FALSE';
        $slot = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->first();
        $sourceBefore = (string) $slot->source;
        $translationsBefore = (string) $slot->translations;
        $forgedSource = json_decode($sourceBefore, true, 512, JSON_THROW_ON_ERROR);
        $forgedSource['correct_answer_key'] = 'a';

        $this->actingAs($admin)->from($detailUrl)->post($detailUrl . '/source', [
            'copy_version' => 1,
            'cognitive_type' => $type,
            'manual_revision' => 0,
            'canonical_base_revision' => (int) $slot->canonical_base_revision,
            'source' => $forgedSource,
        ])->assertStatus(409);

        $forgedTranslation = json_decode($translationsBefore, true, 512, JSON_THROW_ON_ERROR)['fr'];
        $forgedTranslation['correct_answer_key'] = 'a';
        $this->from($detailUrl)->post($detailUrl . '/translation', [
            'copy_version' => 1,
            'cognitive_type' => $type,
            'language_code' => 'fr',
            'manual_revision' => 0,
            'canonical_base_revision' => (int) $slot->canonical_base_revision,
            'translation' => $forgedTranslation,
        ])->assertStatus(409);

        $after = DB::table('kernel_quarantine_work_copy_slots')
            ->where('copy_id', $copyId)->where('cognitive_type', $type)->first();
        self::assertSame($sourceBefore, (string) $after->source);
        self::assertSame($translationsBefore, (string) $after->translations);
        self::assertSame(1, (int) DB::table('kernel_quarantine_work_copies')
            ->where('copy_id', $copyId)->value('copy_version'));
    }

    public function test_stale_source_correction_returns_conflict_without_canonical_write(): void
    {
        $admin = $this->makeAdmin('stale-correction-admin');
        $before = DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprintId)->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('source');
        $token = Str::uuid()->toString();
        $payload = $this->source('QCM_RECOGNITION');
        $payload['question'] = 'A stale correction';

        $response = $this->actingAs($admin)->withSession(['_token' => $token])
            ->from('/admin/questions/quarantine/' . $this->copy['copy_id'])
            ->post('/admin/questions/quarantine/' . $this->copy['copy_id'] . '/source', [
                '_token' => $token, 'copy_version' => 999, 'cognitive_type' => 'QCM_RECOGNITION',
                'manual_revision' => 0, 'source' => $payload,
            ]);
        $response->assertStatus(409);
        self::assertSame($before, DB::table('kernel_blueprint_cognitive_slots')
            ->where('blueprint_id', $this->blueprintId)->where('cognitive_type', 'QCM_RECOGNITION')
            ->value('source'));
    }

    private function makeAdmin(string $suffix): User
    {
        $user = $this->makeUser($suffix);
        DB::table('users')->where('id', $user->id)->update(['is_admin' => true]);
        return $user->refresh();
    }

    private function forbiddenKeys(mixed $value): array
    {
        $blocked = ['claim_token', 'active_claim_token', 'request_hash', 'intent_hash', 'idempotency_key'];
        $found = [];
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (in_array((string) $key, $blocked, true)) {
                    $found[] = (string) $key;
                }
                $found = array_merge($found, $this->forbiddenKeys($item));
            }
        }
        return array_values(array_unique($found));
    }

    private function source(string $type): array
    {
        $qcm = str_starts_with($type, 'QCM_');
        return [
            'schema_version' => 'phase1.source.v1', 'source_language' => 'en',
            'cognitive_type' => $type, 'question' => 'Which color?',
            'choices' => $qcm ? ['a' => 'Blue', 'b' => 'Red', 'c' => 'Green', 'd' => 'Yellow'] : ['a' => 'True', 'b' => 'False'],
            'correct_answer_key' => str_ends_with($type, '_FALSE') ? 'b' : 'a',
            'sv' => 'The answer is stable.',
        ];
    }

    private function translations(string $type): array
    {
        $source = $this->source($type);
        $revision = hash('sha256', json_encode([
            'question' => $source['question'], 'choices' => $source['choices'],
            'correct_answer_key' => $source['correct_answer_key'], 'sv' => $source['sv'],
        ], JSON_THROW_ON_ERROR));
        $result = [];
        foreach (KernelQuarantineAdminReadService::LANGUAGES as $language) {
            $translation = $source;
            unset($translation['source_language'], $translation['cognitive_type']);
            $translation['schema_version'] = 'phase2.translation.v1';
            $translation['translation_language'] = $language;
            $translation['translation_revision'] = 1;
            $translation['source_revision'] = $revision;
            $translation['validation_status'] = 'VALIDATED';
            $result[$language] = $translation;
        }
        return $result;
    }

    private function makeUser(string $suffix): User
    {
        return User::query()->create([
            'name' => 'User ' . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'secret',
        ]);
    }
}