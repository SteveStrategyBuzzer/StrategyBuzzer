<?php

namespace Tests\Feature;

use App\Models\AdminPrivilegeAuditLog;
use App\Models\User;
use App\Services\AdminPrivilegeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        putenv('QB_HEALTH_TOKEN');
        parent::tearDown();
    }

    public function test_new_users_are_not_admin_by_default_and_cast_is_boolean(): void
    {
        $user = $this->makeUser('default')->refresh();

        $this->assertFalse($user->isAdmin());
        $this->assertIsBool($user->is_admin);
        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));
        $this->assertNotContains('is_admin', $user->getFillable());

        $column = DB::selectOne(
            "SELECT is_nullable, column_default FROM information_schema.columns
             WHERE table_schema = 'public' AND table_name = 'users' AND column_name = 'is_admin'"
        );
        $this->assertSame('NO', $column->is_nullable);
        $this->assertStringContainsString('false', strtolower((string) $column->column_default));
    }

    public function test_migration_backfills_an_existing_user_to_false(): void
    {
        $userId = $this->makeUser('pre-migration')->id;
        Schema::table('users', function ($table): void {
            $table->dropColumn('is_admin');
        });

        $migration = require database_path('migrations/2026_09_21_000001_add_is_admin_to_users_table.php');
        $migration->up();

        $this->assertFalse((bool) DB::table('users')->where('id', $userId)->value('is_admin'));
    }

    public function test_guest_cannot_access_admin_pages_even_with_tokens(): void
    {
        putenv('QB_HEALTH_TOKEN=security-test-token');

        foreach (['/admin/questions/audit-log', '/admin/questions/taxonomy-gaps'] as $url) {
            $this->get($url . '?token=security-test-token')->assertRedirect('/login');
        }
    }

    public function test_exact_query_token_does_not_bypass_non_admin(): void
    {
        putenv('QB_HEALTH_TOKEN=security-test-token');
        $this->actingAs($this->makeUser('query-token-user'));

        $this->get('/admin/questions/audit-log?token=security-test-token')->assertForbidden();
        $this->get('/admin/questions/taxonomy-gaps?token=security-test-token')->assertForbidden();
    }

    public function test_valid_bearer_does_not_authorize_a_blade_page(): void
    {
        putenv('QB_HEALTH_TOKEN=security-test-token');
        $this->withHeader('Authorization', 'Bearer security-test-token')
            ->get('/admin/questions/audit-log')
            ->assertRedirect('/login');
    }

    public function test_missing_admin_signal_fails_closed(): void
    {
        $user = new User(['name' => 'Incomplete User']);
        $this->assertFalse($user->isAdmin());

        $this->actingAs($user)->get('/admin/questions/audit-log')->assertForbidden();
    }

    public function test_rank_does_not_grant_admin_access(): void
    {
        $this->actingAs($this->makeUser('high-rank', ['rank' => 'Legendary']))
            ->get('/admin/questions/audit-log')
            ->assertForbidden();
    }

    public function test_is_bot_does_not_grant_admin_access(): void
    {
        $this->actingAs($this->makeUser('bot-user', ['is_bot' => true]))
            ->get('/admin/questions/audit-log')
            ->assertForbidden();
    }

    public function test_owner_like_name_does_not_grant_admin_access(): void
    {
        $this->actingAs($this->makeUser('owner-like-account'))
            ->get('/admin/questions/audit-log')
            ->assertForbidden();
    }

    public function test_authenticated_non_admin_is_forbidden(): void
    {
        $user = $this->makeUser('ordinary-user');
        $this->actingAs($user)
            ->get('/admin/questions/audit-log')
            ->assertForbidden();
    }

    public function test_admin_can_access_both_existing_pages(): void
    {
        $admin = $this->makeUser('real-admin');
        DB::table('users')->where('id', $admin->id)->update(['is_admin' => true]);
        $admin->refresh();

        $this->actingAs($admin)->get('/admin/questions/audit-log')->assertOk();
        $this->actingAs($admin)->get('/admin/questions/taxonomy-gaps')->assertOk();
    }

    public function test_grant_and_revoke_are_exact_and_idempotent(): void
    {
        $user = $this->makeUser('target');
        $other = $this->makeUser('other-target');
        $service = app(AdminPrivilegeService::class);

        $this->assertTrue($service->grant($user->email));
        $this->assertFalse($service->grant($user->email));
        $this->assertTrue($service->revoke($user->email));
        $this->assertFalse($service->revoke($user->email));

        $this->assertSame(2, AdminPrivilegeAuditLog::query()->count());
        $this->assertSame(
            ['ADMIN_GRANTED', 'ADMIN_REVOKED'],
            AdminPrivilegeAuditLog::query()->orderBy('id')->pluck('action')->all()
        );
        $this->assertSame(2, AdminPrivilegeAuditLog::query()->where('target_user_id', $user->id)->count());
        $this->assertSame(
            ['SYSTEM_CLI', 'SYSTEM_CLI'],
            AdminPrivilegeAuditLog::query()->orderBy('id')->pluck('origin')->all()
        );
        $this->assertFalse((bool) DB::table('users')->where('id', $other->id)->value('is_admin'));
    }

    public function test_privilege_update_rolls_back_when_audit_insert_fails(): void
    {
        $user = $this->makeUser('rollback-target');
        DB::statement('drop table admin_privilege_audit_logs');

        $thrown = null;
        try {
            app(AdminPrivilegeService::class)->grant($user->email);
        } catch (\Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertNotNull($thrown, 'Expected the audit insert to fail.');
        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));
    }

    public function test_artisan_commands_use_exact_existing_email_and_do_not_create_users(): void
    {
        $user = $this->makeUser('cli-target');

        $this->artisan('admin:grant', ['email' => '  ' . $user->email . '  '])
            ->assertExitCode(0);
        $this->assertTrue((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));

        $this->artisan('admin:grant', ['email' => $user->email])->assertExitCode(0);
        $this->artisan('admin:revoke', ['email' => $user->email])->assertExitCode(0);
        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));
        $this->assertSame(2, AdminPrivilegeAuditLog::query()->count());
    }

    public function test_artisan_commands_preserve_email_case_and_never_mistarget(): void
    {
        $upper = User::query()->create([
            'name' => 'Upper Case Account',
            'email' => 'CaseSensitive@example.test',
            'password' => 'secret',
        ]);
        $lower = User::query()->create([
            'name' => 'Lower Case Account',
            'email' => 'casesensitive@example.test',
            'password' => 'secret',
        ]);

        $this->artisan('admin:grant', ['email' => $upper->email])->assertExitCode(0);

        $this->assertTrue((bool) DB::table('users')->where('id', $upper->id)->value('is_admin'));
        $this->assertFalse((bool) DB::table('users')->where('id', $lower->id)->value('is_admin'));
    }

    public function test_artisan_commands_refuse_absent_or_invalid_users(): void
    {
        $this->artisan('admin:grant', ['email' => 'not-an-email'])->assertExitCode(2);
        $this->artisan('admin:grant', ['email' => 'missing@example.test'])->assertExitCode(1);
        $this->artisan('admin:revoke', ['email' => 'not-an-email'])->assertExitCode(2);
        $this->artisan('admin:revoke', ['email' => 'missing@example.test'])->assertExitCode(1);
        $this->assertSame(0, DB::table('users')->where('email', 'missing@example.test')->count());
    }

    public function test_user_mass_assignment_cannot_set_is_admin(): void
    {
        $user = $this->makeUser('mass-assignment');
        $user->fill(['is_admin' => true])->save();

        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));
    }

    public function test_registration_payload_cannot_set_is_admin(): void
    {
        $this->post('/auth/email/register', [
            'name' => 'Registration User',
            'email' => 'registration-security@example.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
            'is_admin' => true,
        ]);

        $created = DB::table('users')->where('email', 'registration-security@example.test')->first();
        $this->assertNotNull($created);
        $this->assertFalse((bool) $created->is_admin);
    }

    public function test_profile_payload_cannot_set_is_admin(): void
    {
        $user = $this->makeUser('profile-payload');

        $this->actingAs($user)->post('/profile', [
            'pseudonym' => 'Profile',
            'is_admin' => true,
        ]);

        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('is_admin'));
    }

    public function test_routes_keep_their_existing_names_and_urls(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'admin.questions.audit-log' => '/admin/questions/audit-log',
            'admin.questions.taxonomy-gaps' => '/admin/questions/taxonomy-gaps',
        ] as $name => $uri) {
            $route = $routes->getByName($name);
            $this->assertNotNull($route);
            $this->assertSame($uri, '/' . ltrim($route->uri(), '/'));
            $this->assertSame(['GET', 'HEAD'], $route->methods());
            $this->assertContains('auth', $route->middleware());
            $this->assertContains('admin', $route->middleware());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware);
            $this->assertContains('auth', $middleware);
            $this->assertContains('admin', $middleware);
        }
    }

    public function test_health_route_remains_separate_bearer_get_endpoint(): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/admin/questions/health');

        $this->assertNotNull($route);
        $this->assertSame(['GET', 'HEAD'], $route->methods());
        $this->assertContains('api', $route->gatherMiddleware());
        $this->assertNotContains('admin', $route->gatherMiddleware());
    }

    private function makeUser(string $suffix, array $extra = []): User
    {
        return User::query()->create(array_merge([
            'name' => 'User ' . $suffix,
            'email' => $suffix . '@example.test',
            'password' => 'secret',
        ], $extra));
    }
}