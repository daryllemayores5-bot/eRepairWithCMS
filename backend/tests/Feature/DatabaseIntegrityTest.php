<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\Review;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\DatabaseIntegrity;
use App\Services\SubscriptionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
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

    private function rejects(callable $write, string $state): void
    {
        try {
            DB::transaction($write);
            $this->fail('The database accepted an invalid record.');
        } catch (QueryException $error) {
            $this->assertSame($state, $error->errorInfo[0]);
        }
    }

    public function test_database_checks_find_validated_constraints_and_indexed_foreign_keys(): void
    {
        $checks = app(DatabaseIntegrity::class)->checks(DB::connection());
        foreach ($checks as $check) {
            $this->assertTrue($check['ready'], $check['detail']);
        }
        $this->artisan('erepair:database-check', ['--json' => true])->assertSuccessful();
        DB::statement('DROP INDEX db_estimates_repair');
        $checks = collect(app(DatabaseIntegrity::class)->checks(DB::connection()))->keyBy('key');
        $this->assertFalse($checks['database_fk_indexes']['ready']);
        $this->artisan('erepair:database-check', ['--json' => true])->assertFailed();
    }

    public function test_active_subscriptions_require_expiration_and_payments_cannot_change_technician(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $other = TechnicianProfile::factory()->approved()->create();
        $payment = app(SubscriptionService::class)->request($profile->user, (string) Str::uuid(), '199.00');
        $this->rejects(fn () => DB::table('technician_subscriptions')->where('id', $payment->technician_subscription_id)
            ->update(['status' => 'active', 'starts_at' => now(), 'expires_at' => null]), '23514');
        $this->rejects(fn () => DB::table('subscription_demo_payments')->where('id', $payment->id)
            ->update(['technician_id' => $other->id]), '23503');
        $this->assertSame($profile->id, $payment->fresh()->technician_id);
        $this->assertSame('pending', $payment->subscription->fresh()->status);
    }

    public function test_schema_export_contains_actual_metadata_without_account_records(): void
    {
        $user = User::factory()->create(['email' => 'private-schema-check@example.test']);
        $this->assertSame(0, Artisan::call('erepair:database-schema'));
        $document = Artisan::output();
        $this->assertStringContainsString('## subscription_demo_payments', $document);
        $this->assertStringContainsString('payment_subscription_owner_fk', $document);
        $this->assertStringContainsString('numeric(10,2)', $document);
        $this->assertStringNotContainsString($user->email, $document);
        $this->assertStringNotContainsString($user->getRawOriginal('password'), $document);
    }

    public function test_production_preflight_checks_schema_and_requires_the_production_version_baseline(): void
    {
        $this->assertSame(1, Artisan::call('erepair:production-check', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $checks = array_column($report['checks'], 'ready', 'key');
        $version = (int) DB::selectOne("SELECT current_setting('server_version_num') AS version")->version;
        $this->assertSame($version >= 160000, $checks['database_version']);
        $this->assertTrue($checks['database_constraints']);
        $this->assertTrue($checks['database_fk_indexes']);
    }

    public function test_review_ownership_is_enforced_even_when_all_individual_foreign_keys_exist(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $other = TechnicianProfile::factory()->approved()->create();
        $customer = User::factory()->create();
        $stranger = User::factory()->create();
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'laptop']);
        $brand = DeviceBrand::create(['name' => 'Test brand', 'slug' => 'test-brand']);
        $category = RepairCategory::create(['name' => 'Diagnosis', 'slug' => 'diagnosis']);
        $repair = new RepairRequest(['device_type_id' => $type->id, 'device_brand_id' => $brand->id,
            'repair_category_id' => $category->id, 'technician_profile_id' => $profile->id,
            'model' => 'Test model', 'problem_description' => 'An isolated database integrity test.']);
        $repair->forceFill(['customer_id' => $customer->id, 'reference' => 'TEST-'.Str::ulid(), 'status' => 'completed'])->save();
        $data = ['repair_request_id' => $repair->id, 'customer_id' => $customer->id,
            'technician_profile_id' => $profile->id, 'rating' => 5, 'body' => 'An isolated review integrity test.'];
        $this->rejects(fn () => Review::create([...$data, 'customer_id' => $stranger->id]), '23503');
        $this->rejects(fn () => Review::create([...$data, 'technician_profile_id' => $other->id]), '23503');
        Review::create($data);
        $this->assertSame(1, Review::count());
    }

    public function test_database_rejects_unknown_account_states_and_invalid_experience(): void
    {
        $profile = TechnicianProfile::factory()->create();
        $this->rejects(fn () => DB::table('users')->where('id', $profile->user_id)->update(['account_status' => 'unknown']), '23514');
        foreach ([-1, 61] as $years) {
            $this->rejects(fn () => DB::table('technician_profiles')->where('id', $profile->id)->update(['years_experience' => $years]), '23514');
        }
    }

    public function test_upgrade_and_rollback_preserve_existing_users_plans_and_payment_history(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $payment = app(SubscriptionService::class)->request($profile->user, (string) Str::uuid(), '199.00');
        $original = [$profile->user->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), SubscriptionPlan::orderBy('id')->get()->toArray()];
        $migration = require database_path('migrations/2026_11_12_000001_harden_database_integrity.php');
        $migration->down();
        $checks = collect(app(DatabaseIntegrity::class)->checks(DB::connection()))->keyBy('key');
        $this->assertFalse($checks['database_constraints']['ready']);
        $migration->up();
        $this->assertSame($original, [$profile->user->fresh()->getRawOriginal(), $payment->fresh()->getRawOriginal(), SubscriptionPlan::orderBy('id')->get()->toArray()]);
    }

    public function test_invalid_legacy_rows_abort_the_upgrade_without_partial_schema_changes(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $payment = app(SubscriptionService::class)->request($profile->user, (string) Str::uuid(), '199.00');
        $migration = require database_path('migrations/2026_11_12_000001_harden_database_integrity.php');
        $migration->down();
        DB::table('technician_subscriptions')->where('id', $payment->technician_subscription_id)
            ->update(['status' => 'active', 'starts_at' => now(), 'expires_at' => null]);
        $this->rejects(fn () => $migration->up(), '23514');
        $this->assertNull(DB::selectOne("SELECT to_regclass('public.db_estimates_repair') AS index")->index);
        $this->assertNull($payment->subscription->fresh()->expires_at);
        DB::table('technician_subscriptions')->where('id', $payment->technician_subscription_id)->update(['status' => 'pending', 'starts_at' => null]);
        $migration->up();
        foreach (app(DatabaseIntegrity::class)->checks(DB::connection()) as $check) {
            $this->assertTrue($check['ready'], $check['detail']);
        }
    }
}
