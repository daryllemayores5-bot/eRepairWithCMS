<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardActivityTest extends TestCase
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
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
        $this->travelTo(CarbonImmutable::parse('2026-11-03 00:30:00', 'Asia/Manila'));
    }

    public function test_activity_respects_customer_scope_and_manila_midnight_boundaries(): void
    {
        $customer = User::factory()->create();
        $other = User::factory()->create();
        $technician = User::factory()->create(['role' => 'technician']);
        $profile = $technician->technicianProfile()->create(['city' => 'Santa Cruz', 'province' => 'Laguna']);
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'laptop']);
        $brand = DeviceBrand::create(['name' => 'Acer', 'slug' => 'acer']);
        $category = RepairCategory::create(['name' => 'Battery', 'slug' => 'battery']);
        foreach ([[$customer, '2026-11-02 23:59:00'], [$customer, '2026-11-03 00:01:00'], [$other, '2026-11-03 00:02:00'], [$customer, '2026-10-20 23:59:00']] as $index => [$owner, $date]) {
            RepairRequest::forceCreate([
                'reference' => 'ER-ACTIVITY-'.$index, 'customer_id' => $owner->id,
                'technician_profile_id' => $profile->id, 'device_type_id' => $type->id,
                'device_brand_id' => $brand->id, 'repair_category_id' => $category->id,
                'model' => 'Aspire', 'problem_description' => 'Battery no longer holds a charge.',
                'status' => 'pending', 'created_at' => CarbonImmutable::parse($date, 'Asia/Manila')->utc(),
            ]);
        }
        $response = $this->actingAs($customer, 'web')->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.total', 3)->assertJsonCount(14, 'data.activity')
            ->assertJsonPath('data.activity.0.date', '2026-10-21')
            ->assertJsonPath('data.activity.12.date', '2026-11-02')->assertJsonPath('data.activity.12.count', 1)
            ->assertJsonPath('data.activity.13.date', '2026-11-03')->assertJsonPath('data.activity.13.count', 1);
        $this->assertSame(2, array_sum(array_column($response->json('data.activity'), 'count')));
        app('auth')->forgetGuards();
        $tech = $this->actingAs($technician, 'web')->getJson('/api/v1/technician/dashboard')->assertOk()
            ->assertJsonPath('data.total', 4)->assertJsonPath('data.activity.13.count', 2);
        $this->assertSame(3, array_sum(array_column($tech->json('data.activity'), 'count')));
    }

    public function test_empty_account_has_fourteen_real_zero_days_and_no_other_users_activity(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.total', 0)->assertJsonCount(14, 'data.activity')
            ->assertJsonPath('data.activity.0.count', 0)->assertJsonPath('data.activity.13.count', 0);
    }
}
