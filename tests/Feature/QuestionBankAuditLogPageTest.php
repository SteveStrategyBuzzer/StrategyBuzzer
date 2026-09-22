<?php

namespace Tests\Feature;

use App\Models\AdminQuestionAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QuestionBankAuditLogPageTest extends TestCase
{
    use DatabaseTransactions;

    private const URL = '/admin/questions/audit-log';

    protected function setUp(): void
    {
        parent::setUp();

        $admin = $this->makeUser('Audit Admin', 'audit-admin');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => true]);
        $this->actingAs($admin->refresh());
    }

    public function test_authenticated_admin_renders_table(): void
    {
        $alice = $this->makeUser('Alice', 'alice');
        $bob = $this->makeUser('Bob', 'bob');
        $this->seedRow($alice->id, '/generate-master-question', true, 200);
        $this->seedRow($bob->id, '/generate-image-question', false, 403);

        $this->get(self::URL)
            ->assertOk()
            ->assertSee('Alice')
            ->assertSee('Bob')
            ->assertSee('/generate-master-question')
            ->assertSee('/generate-image-question')
            ->assertSee('200')
            ->assertSee('403');
    }

    public function test_filter_by_user_name(): void
    {
        $alice = $this->makeUser('Alice', 'alice-name');
        $bob = $this->makeUser('Bob', 'bob-name');
        $this->seedRow($alice->id, '/generate-master-question', true);
        $this->seedRow($bob->id, '/generate-image-question', true);

        $response = $this->get(self::URL . '?user=Ali');

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-master-question');
        $this->assertCellLacksEndpoint($response, '/generate-image-question');
    }

    public function test_filter_by_user_id(): void
    {
        $alice = $this->makeUser('Alice', 'alice-id');
        $bob = $this->makeUser('Bob', 'bob-id');
        $this->seedRow($alice->id, '/generate-master-question', true);
        $this->seedRow($bob->id, '/generate-image-question', true);

        $response = $this->get(self::URL . '?user=' . $bob->id);

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-image-question');
        $this->assertCellLacksEndpoint($response, '/generate-master-question');
    }

    public function test_filter_by_endpoint(): void
    {
        $user = $this->makeUser('Endpoint', 'endpoint');
        $this->seedRow($user->id, '/generate-master-question', true);
        $this->seedRow($user->id, '/generate-image-question', true);

        $response = $this->get(self::URL . '?endpoint=' . urlencode('/generate-master-question'));

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-master-question');
        $this->assertCellLacksEndpoint($response, '/generate-image-question');
    }

    public function test_filter_by_accepted_status(): void
    {
        $user = $this->makeUser('Accepted', 'accepted');
        $this->seedRow($user->id, '/generate-master-question', true, 200);
        $this->seedRow($user->id, '/generate-image-question', false, 500);

        $response = $this->get(self::URL . '?status=accepted');

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-master-question');
        $this->assertCellLacksEndpoint($response, '/generate-image-question');
    }

    public function test_filter_by_rejected_status(): void
    {
        $user = $this->makeUser('Rejected', 'rejected');
        $this->seedRow($user->id, '/generate-master-question', true, 200);
        $this->seedRow($user->id, '/generate-image-question', false, 500);

        $response = $this->get(self::URL . '?status=rejected');

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-image-question');
        $this->assertCellLacksEndpoint($response, '/generate-master-question');
    }

    public function test_filter_by_date_range(): void
    {
        $user = $this->makeUser('Date', 'date');
        $this->seedRow($user->id, '/generate-master-question', true, 200, Carbon::parse('2026-01-15 10:00:00'));
        $this->seedRow($user->id, '/generate-image-question', true, 200, Carbon::parse('2026-04-15 10:00:00'));

        $response = $this->get(self::URL . '?from=2026-04-01&to=2026-04-30');

        $response->assertOk();
        $this->assertCellHasEndpoint($response, '/generate-image-question');
        $this->assertCellLacksEndpoint($response, '/generate-master-question');
    }

    public function test_pagination_caps_at_fifty_rows(): void
    {
        $user = $this->makeUser('Pagination', 'pagination');
        for ($i = 0; $i < 55; $i++) {
            $this->seedRow($user->id, '/generate-master-question', true, 200, Carbon::now()->subSeconds($i));
        }

        $pageOne = $this->get(self::URL);
        $pageTwo = $this->get(self::URL . '?page=2');

        $pageOne->assertOk()->assertSee('1–50')->assertSee('55');
        $pageTwo->assertOk()->assertSee('51–55')->assertSee('55');
        $this->assertSame(55, AdminQuestionAuditLog::query()->count());
    }

    public function test_empty_state_when_no_rows(): void
    {
        $this->get(self::URL)->assertOk()->assertSee(__('No records found'));
    }

    private function assertCellHasEndpoint($response, string $endpoint): void
    {
        $response->assertSee('<code class="endpoint">' . e($endpoint) . '</code>', false);
    }

    private function assertCellLacksEndpoint($response, string $endpoint): void
    {
        $response->assertDontSee('<code class="endpoint">' . e($endpoint) . '</code>', false);
    }

    private function makeUser(string $name, string $emailPrefix): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $emailPrefix . '@example.test',
            'password' => 'secret',
        ]);
    }

    private function seedRow(
        int $userId,
        string $endpoint,
        bool $accepted,
        ?int $httpStatus = 200,
        ?Carbon $createdAt = null
    ): void {
        AdminQuestionAuditLog::query()->create([
            'jti' => 'jti-' . bin2hex(random_bytes(12)),
            'caller_user_id' => $userId,
            'endpoint' => $endpoint,
            'payload_hash' => str_repeat('a', 64),
            'source' => 'test',
            'accepted' => $accepted,
            'http_status' => $httpStatus,
            'created_at' => $createdAt ?? Carbon::now(),
            'responded_at' => $createdAt ?? Carbon::now(),
        ]);
    }
}