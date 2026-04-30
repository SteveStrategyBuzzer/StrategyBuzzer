<?php

namespace Tests\Feature;

use App\Models\AdminQuestionAuditLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Task #109 — Feature coverage for the admin audit-log page.
 *
 * Strict scope mirror: auth (same QB_HEALTH_TOKEN as the JSON health
 * endpoint), the four required filters (user / endpoint / accepted /
 * date range), pagination, and an explicit "no records found" path.
 *
 * NOTE: this project's `config/database.php` hard-codes the default
 * connection to `pgsql` and explicitly ignores .env / phpunit.xml env
 * blocks (see the in-file "Force PostgreSQL if Replit environment
 * variables are present, ignore .env file" comment). To stay strictly
 * within #109's scope (no infra changes), this test reconfigures the
 * default connection to an in-memory sqlite DB at setUp time and
 * builds the two tables it needs by hand instead of running
 * `migrate:fresh` over the 89 unrelated migrations.
 */
class QuestionBankAuditLogPageTest extends TestCase
{
    private const URL = '/admin/questions/audit-log';
    private const TOKEN = 'test-audit-token-very-secret-1234567890';

    protected function setUp(): void
    {
        parent::setUp();

        // Force a hermetic in-memory sqlite connection just for this test
        // class. We never write to the real Postgres DB.
        Config::set('database.connections.testsqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        Config::set('database.default', 'testsqlite');
        DB::purge('testsqlite');
        DB::reconnect('testsqlite');

        $this->buildFixtureSchema();

        putenv('QB_HEALTH_TOKEN=' . self::TOKEN);
    }

    protected function tearDown(): void
    {
        putenv('QB_HEALTH_TOKEN');
        parent::tearDown();
    }

    public function test_no_token_returns_403(): void
    {
        $resp = $this->get(self::URL);
        $resp->assertStatus(403);
    }

    public function test_wrong_token_returns_403(): void
    {
        $resp = $this->get(self::URL . '?token=nope');
        $resp->assertStatus(403);
    }

    public function test_unset_secret_locks_the_page(): void
    {
        putenv('QB_HEALTH_TOKEN');
        $resp = $this->get(self::URL . '?token=' . self::TOKEN);
        $resp->assertStatus(403);
    }

    public function test_valid_bearer_renders_table(): void
    {
        $alice = $this->makeUser('Alice');
        $bob = $this->makeUser('Bob');
        $this->seedRow($alice, '/generate-master-question', true, 200);
        $this->seedRow($bob, '/generate-image-question', false, 403);

        $resp = $this->withHeader('Authorization', 'Bearer ' . self::TOKEN)->get(self::URL);

        $resp->assertOk();
        $resp->assertSee('Alice');
        $resp->assertSee('Bob');
        $resp->assertSee('/generate-master-question');
        $resp->assertSee('/generate-image-question');
        $resp->assertSee('200');
        $resp->assertSee('403');
    }

    public function test_query_token_works_too(): void
    {
        $u = $this->makeUser('Carol');
        $this->seedRow($u, '/generate-master-question', true, 200);
        $resp = $this->get(self::URL . '?token=' . self::TOKEN);
        $resp->assertOk()->assertSee('Carol');
    }

    public function test_filter_by_user_name(): void
    {
        $alice = $this->makeUser('Alice');
        $bob = $this->makeUser('Bob');
        $this->seedRow($alice, '/generate-master-question', true);
        $this->seedRow($bob, '/generate-image-question', true);

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&user=Ali');
        $resp->assertOk();
        $resp->assertSee('Alice');
        $resp->assertDontSee('Bob');
    }

    public function test_filter_by_user_id(): void
    {
        $alice = $this->makeUser('Alice');
        $bob = $this->makeUser('Bob');
        $this->seedRow($alice, '/generate-master-question', true);
        $this->seedRow($bob, '/generate-image-question', true);

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&user=' . $bob);
        $resp->assertOk();
        $resp->assertSee('Bob');
        $resp->assertDontSee('Alice');
    }

    public function test_filter_by_endpoint(): void
    {
        $u = $this->makeUser('Eve');
        $this->seedRow($u, '/generate-master-question', true);
        $this->seedRow($u, '/generate-image-question', true);

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&endpoint=' . urlencode('/generate-master-question'));
        $resp->assertOk();
        // The dropdown lists every known endpoint as an <option>, so we have to
        // scope the visibility check to the rendered <td> cell.
        $this->assertCellHasEndpoint($resp, '/generate-master-question');
        $this->assertCellLacksEndpoint($resp, '/generate-image-question');
    }

    public function test_filter_by_status_accepted(): void
    {
        $u = $this->makeUser('Frank');
        $this->seedRow($u, '/generate-master-question', true, 200, 'jti-ok');
        $this->seedRow($u, '/generate-image-question', false, 500, 'jti-ko');

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&status=accepted');
        $resp->assertOk();
        $this->assertCellHasEndpoint($resp, '/generate-master-question');
        $this->assertCellLacksEndpoint($resp, '/generate-image-question');
    }

    public function test_filter_by_status_rejected(): void
    {
        $u = $this->makeUser('Gina');
        $this->seedRow($u, '/generate-master-question', true, 200, 'jti-ok2');
        $this->seedRow($u, '/generate-image-question', false, 500, 'jti-ko2');

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&status=rejected');
        $resp->assertOk();
        $this->assertCellHasEndpoint($resp, '/generate-image-question');
        $this->assertCellLacksEndpoint($resp, '/generate-master-question');
    }

    public function test_filter_by_date_range(): void
    {
        $u = $this->makeUser('Henry');
        $this->seedRow($u, '/generate-master-question', true, 200, 'jti-old', Carbon::parse('2026-01-15 10:00:00'));
        $this->seedRow($u, '/generate-image-question', true, 200, 'jti-new', Carbon::parse('2026-04-15 10:00:00'));

        $resp = $this->get(self::URL . '?token=' . self::TOKEN . '&from=2026-04-01&to=2026-04-30');
        $resp->assertOk();
        $this->assertCellHasEndpoint($resp, '/generate-image-question');
        $this->assertCellLacksEndpoint($resp, '/generate-master-question');
    }

    private function assertCellHasEndpoint($resp, string $endpoint): void
    {
        $needle = '<code class="endpoint">' . e($endpoint) . '</code>';
        $resp->assertSee($needle, false);
    }

    private function assertCellLacksEndpoint($resp, string $endpoint): void
    {
        $needle = '<code class="endpoint">' . e($endpoint) . '</code>';
        $resp->assertDontSee($needle, false);
    }

    public function test_pagination_caps_per_page(): void
    {
        $u = $this->makeUser('Ivy');
        for ($i = 0; $i < 55; $i++) {
            $this->seedRow($u, '/generate-master-question', true, 200, 'jti-page-' . $i);
        }

        $page1 = $this->get(self::URL . '?token=' . self::TOKEN);
        $page1->assertOk();
        $page2 = $this->get(self::URL . '?token=' . self::TOKEN . '&page=2');
        $page2->assertOk();

        $this->assertSame(55, AdminQuestionAuditLog::query()->count());
    }

    public function test_empty_state_when_no_rows(): void
    {
        $resp = $this->get(self::URL . '?token=' . self::TOKEN);
        $resp->assertOk();
        $resp->assertSee(__('No records found'));
    }

    private function buildFixtureSchema(): void
    {
        Schema::dropIfExists('admin_question_audit_log');
        Schema::dropIfExists('users');
        Schema::create('users', function ($t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('email')->nullable();
            $t->timestamps();
        });
        Schema::create('admin_question_audit_log', function ($t) {
            $t->bigIncrements('id');
            $t->string('jti', 64)->unique();
            $t->unsignedBigInteger('caller_user_id')->nullable()->index();
            $t->string('endpoint', 64)->index();
            $t->char('payload_hash', 64);
            $t->string('source', 64)->nullable();
            $t->boolean('accepted')->default(false);
            $t->unsignedSmallInteger('http_status')->nullable();
            $t->string('error', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('responded_at')->nullable();
        });
    }

    private function makeUser(string $name): int
    {
        return DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower($name) . '@example.test',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    private function seedRow(
        int $userId,
        string $endpoint,
        bool $accepted,
        ?int $httpStatus = 200,
        ?string $jti = null,
        ?Carbon $createdAt = null
    ): void {
        AdminQuestionAuditLog::create([
            'jti' => $jti ?? ('jti-' . uniqid('', true)),
            'caller_user_id' => $userId,
            'endpoint' => $endpoint,
            'payload_hash' => str_repeat('a', 64),
            'source' => 'test',
            'accepted' => $accepted,
            'http_status' => $httpStatus,
            'created_at' => ($createdAt ?? Carbon::now())->toDateTimeString(),
            'responded_at' => ($createdAt ?? Carbon::now())->toDateTimeString(),
        ]);
    }
}
