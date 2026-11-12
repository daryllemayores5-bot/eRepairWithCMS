<?php

namespace Tests\Feature;

use App\Models\CmsUser;
use App\Models\DeviceType;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HostedBootstrapTest extends TestCase
{
    use RefreshDatabase;

    private array $previous = [];

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires disposable PostgreSQL test database.');
        }
        parent::setUp();
        foreach (['EREPAIR_ADMIN_NAME' => 'Platform Owner', 'EREPAIR_ADMIN_EMAIL' => 'owner@erepair.test', 'EREPAIR_ADMIN_PASSWORD' => 'PrivateTestPassword123!'] as $key => $value) {
            $this->previous[$key] = getenv($key);
            putenv($key.'='.$value);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previous as $key => $value) {
            putenv($value === false ? $key : $key.'='.$value);
        }
        parent::tearDown();
    }

    public function test_first_admin_has_one_native_cms_identity_and_repeat_start_does_not_reset_password(): void
    {
        $this->artisan('erepair:bootstrap-hosted-admin')->assertSuccessful();
        $operator = User::sole();
        $this->assertSame('admin', $operator->role);
        $this->assertSame('active', $operator->account_status);
        $this->assertTrue(Hash::check('PrivateTestPassword123!', CmsUser::sole()->password));
        $hash = $operator->password;
        putenv('EREPAIR_ADMIN_PASSWORD=ChangedBootstrapPassword123!');
        $this->artisan('erepair:bootstrap-hosted-admin')->assertSuccessful();
        $this->assertSame($hash, $operator->fresh()->password);
        $this->assertSame(1, CmsUser::count());
    }

    public function test_existing_customer_email_cannot_be_promoted_by_bootstrap(): void
    {
        User::factory()->create(['email' => 'owner@erepair.test']);
        $this->artisan('erepair:bootstrap-hosted-admin')->assertFailed();
        $this->assertSame('customer', User::sole()->role);
        $this->assertSame(0, CmsUser::count());
    }

    public function test_production_catalog_is_idempotent_and_preserves_disabled_admin_records(): void
    {
        $this->seed(CatalogSeeder::class);
        DeviceType::where('slug', 'laptop')->update(['active' => false, 'name' => 'Custom laptop label']);
        $this->seed(CatalogSeeder::class);
        $this->assertSame(0, User::count());
        $this->assertSame(2, DeviceType::count());
        $this->assertDatabaseHas('device_types', ['slug' => 'laptop', 'active' => false, 'name' => 'Custom laptop label']);
        $this->assertDatabaseCount('specializations', 6);
        $this->assertDatabaseCount('repair_categories', 6);
        $this->assertDatabaseCount('repair_requests', 0);
        $this->assertDatabaseCount('reviews', 0);
    }
}
