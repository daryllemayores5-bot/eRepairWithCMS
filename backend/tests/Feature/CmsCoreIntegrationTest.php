<?php

namespace Tests\Feature;

use App\Models\CmsUser;
use App\Models\User;
use App\Services\CmsAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Auth\Notifications\ResetPassword;
use LogicException;
use Statamic\Facades\User as StatamicUser;
use Statamic\Http\Middleware\CP\CountUsers;
use Statamic\Statamic;
use Tests\TestCase;

class CmsCoreIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires dedicated PostgreSQL integration database.');
        }
        parent::setUp();
    }

    private function editor(): CmsUser
    {
        return app(CmsAccount::class)->provision(User::factory()->create(['role' => 'admin', 'super' => true]));
    }

    public function test_operations_styles_are_loaded_outside_the_vue_mounted_blade_content(): void
    {
        $html = $this->actingAs($this->editor(), 'cp')->get('/cp/erepair')->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//head/link[@rel="stylesheet" and starts-with(@href, "/vendor/erepair/css/operations.css?v=")]')->length,
            'Statamic must load eRepair CSS in the head, outside the Vue template.');
        $this->assertSame(0, $xpath->query('//*[@id="statamic"]//style')->length,
            'Vue removes side-effect style tags from mounted Blade content.');
        $this->assertSame(1, $xpath->query('//*[@class="erepair-ops" and @v-pre]')->length);
        $this->assertFileEquals(resource_path('css/erepair-cp.css'), public_path('vendor/erepair/css/operations.css'));
    }

    public function test_core_counts_one_real_editor_with_many_independent_marketplace_accounts(): void
    {
        User::factory()->count(12)->create();
        $cms = $this->editor();
        $this->assertFalse(Statamic::pro());
        $this->assertSame(13, User::count());
        $this->assertSame(1, StatamicUser::count());
        $this->assertTrue((new CountUsers)->handle(Request::create('/cp'), fn () => true));
        $this->assertTrue(Hash::check('password', $cms->password));
        foreach (['api', 'graphql', 'revisions', 'git'] as $feature) {
            $this->assertFalse(config("statamic.$feature.enabled"));
        }
        $this->actingAs($cms, 'cp')->get('/cp')->assertRedirect();
        $this->get('/cp/dashboard')->assertOk();
        $this->get('/cp/collections')->assertOk();
        $this->get('/cp/erepair')->assertOk();
    }

    public function test_native_cp_login_does_not_authenticate_the_marketplace_api(): void
    {
        $cms = $this->editor();
        $this->postJson('/cp/auth/login', ['email' => $cms->email, 'password' => 'password'])->assertOk();
        $this->assertAuthenticated('cp');
        $this->assertTrue(CmsAccount::identity(auth('cp')->user())->is($cms));
        $this->assertGuest('web');
        $this->getJson('/api/v1/user')->assertUnauthorized();
        $this->get('/cp/erepair')->assertOk();
    }

    public function test_marketplace_login_cannot_access_or_authenticate_the_cms(): void
    {
        $customer = User::factory()->create();
        $this->editor();
        $this->actingAs($customer, 'web')->get('/cp/erepair')->assertRedirect(cp_route('login'));
        $this->postJson('/cp/auth/login', ['email' => $customer->email, 'password' => 'password'])->assertUnprocessable();
        $this->assertGuest('cp');
    }

    public function test_suspending_or_demoting_the_linked_operator_denies_a_live_cms_session(): void
    {
        $cms = $this->editor();
        $this->actingAs($cms, 'cp')->get('/cp/erepair')->assertOk();
        $cms->operator()->update(['account_status' => 'suspended']);
        $this->get('/cp/erepair')->assertForbidden();
        $this->get('/cp/collections')->assertForbidden();
        $cms->operator()->update(['account_status' => 'active', 'role' => 'customer']);
        $this->get('/cp')->assertForbidden();
    }

    public function test_single_editor_is_enforced_by_the_database(): void
    {
        $this->editor();
        $other = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->expectException(QueryException::class);
        CmsUser::forceCreate(['user_id' => $other->id, 'singleton' => true, 'name' => $other->name, 'email' => $other->email, 'password' => 'password']);
    }

    public function test_customers_cannot_be_provisioned_as_cms_users(): void
    {
        $this->expectException(LogicException::class);
        app(CmsAccount::class)->provision(User::factory()->create());
    }

    public function test_single_editor_constraint_rejects_a_false_singleton_flag(): void
    {
        $operator = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->expectException(QueryException::class);
        CmsUser::forceCreate(['user_id' => $operator->id, 'singleton' => false, 'name' => $operator->name, 'email' => $operator->email, 'password' => 'password']);
    }

    public function test_fresh_administrator_command_creates_one_linked_cms_editor(): void
    {
        $this->artisan('erepair:create-admin')
            ->expectsQuestion('Administrator name', 'College Administrator')
            ->expectsQuestion('Administrator email', 'college@example.test')
            ->expectsQuestion('Password (at least 12 characters)', 'PrivateAdminPassword123!')
            ->assertSuccessful();
        $operator = User::sole();
        $cms = CmsUser::sole();
        $this->assertSame('admin', $operator->role);
        $this->assertSame($operator->id, $cms->user_id);
        $this->assertTrue(Hash::check('PrivateAdminPassword123!', $cms->password));
    }

    public function test_second_administrator_command_fails_before_requesting_credentials(): void
    {
        $this->editor();
        $this->artisan('erepair:create-admin')->expectsOutputToContain('An administrator already exists')->assertFailed();
        $this->assertSame(1, CmsUser::count());
    }

    public function test_native_password_reset_uses_its_own_token_store_and_preserves_the_application_password(): void
    {
        Notification::fake();
        $cms = $this->editor();
        $operator = $cms->operator;
        $this->postJson('/cp/auth/password/email', ['email' => $cms->email])->assertOk();
        $notice = Notification::sent($cms, ResetPassword::class)->first();
        $this->assertNotNull($notice);
        $this->assertStringContainsString('/cp/auth/password/reset/', $notice->toMail($cms)->actionUrl);
        $applicationToken = Password::broker('users')->createToken($operator);
        $attributes = ['email' => $cms->email, 'password' => 'NewCmsPassword123!', 'password_confirmation' => 'NewCmsPassword123!'];
        $this->postJson('/cp/auth/password/reset', [...$attributes, 'token' => $applicationToken])->assertUnprocessable();
        $this->postJson('/cp/auth/password/reset', [...$attributes, 'token' => $notice->token])->assertOk();
        $this->assertTrue(Hash::check('NewCmsPassword123!', $cms->fresh()->password));
        $this->assertTrue(Hash::check('password', $operator->fresh()->password));
        $this->assertFalse(Password::broker('cms_resets')->tokenExists($cms->fresh(), $notice->token));
    }

    public function test_production_inspection_rejects_demo_data_without_modifying_it(): void
    {
        $this->editor();
        User::factory()->create(['email' => 'customer1@erepair.test']);
        $status = Artisan::call('erepair:production-check', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $flags = array_column($report['checks'], 'ready', 'key');
        $this->assertSame(1, $status);
        $this->assertTrue($flags['cms_editor']);
        $this->assertFalse($flags['production_data']);
        $this->assertSame(2, User::count());
        $this->assertSame(1, CmsUser::count());
    }
}
