<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\CmsAccount;
use App\Services\SubscriptionRevenue;
use App\Services\SubscriptionService;
use App\Services\TechnicianFinder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PremiumSubscriptionTest extends TestCase
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
        $this->travelTo(now()->setDate(2026, 11, 12)->startOfDay());
        config(['sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

    private function payment(TechnicianProfile $profile, string $slug): SubscriptionDemoPayment
    {
        return app(SubscriptionService::class)->requestPlan($profile->user, (string) Str::uuid(), SubscriptionPlan::where('slug', $slug)->firstOrFail()->price, $slug);
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->first() ?? User::factory()->create(['role' => 'admin', 'super' => true]);
    }

    public function test_premium_migration_is_idempotent_and_preserves_customized_free_and_pro_records(): void
    {
        $before = SubscriptionPlan::whereIn('slug', ['free', 'pro'])->orderBy('id')->get()->toArray();
        $premium = SubscriptionPlan::where('slug', 'premium')->sole();
        $this->assertSame('399.00', $premium->price);
        $this->assertSame(30, $premium->duration_days);
        $this->assertTrue($premium->is_active);
        $premium->update(['description' => 'Administrator customized benefits']);
        $migration = require database_path('migrations/2026_11_12_010442_add_premium_subscription_plan.php');
        $migration->up();
        $migration->up();
        $this->assertSame($before, SubscriptionPlan::whereIn('slug', ['free', 'pro'])->orderBy('id')->get()->toArray());
        $this->assertSame('Administrator customized benefits', $premium->fresh()->description);
        $this->assertSame(1, SubscriptionPlan::where('slug', 'premium')->count());
    }

    public function test_premium_checkout_and_native_approval_publish_the_correct_badge_and_revenue_once(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $request = ['idempotency_key' => (string) Str::uuid(), 'expected_price' => '399.00', 'plan_slug' => 'premium', 'confirm_demo' => true];
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription')->assertOk()->assertJsonCount(3, 'data.plans');
        $this->postJson('/api/v1/technician/subscription/demo-payments', $request)->assertOk()->assertJsonPath('data.amount', '399.00')->assertJsonPath('data.plan_name', 'Premium Technician')->assertJsonPath('data.status', 'pending');
        $payment = SubscriptionDemoPayment::sole();
        $this->assertStringStartsWith('DEMO-', $payment->transaction_reference);
        $this->assertFalse($profile->hasPremiumSubscription());
        $this->postJson('/api/v1/technician/subscription/demo-payments', $request)->assertOk();
        $this->assertDatabaseCount('subscription_demo_payments', 1);
        $this->assertSame('0.00', app(SubscriptionRevenue::class)->summary()['total']);
        $cms = app(CmsAccount::class)->provision($this->admin());
        $path = '/cp/erepair/subscriptions/payments/'.$payment->id;
        $this->actingAs($cms, 'cp')->post($path, ['decision' => 'approved'])->assertRedirect();
        $this->post($path, ['decision' => 'approved'])->assertRedirect();
        $this->post($path, ['decision' => 'rejected'])->assertRedirect();
        $this->assertSame('399.00', app(SubscriptionRevenue::class)->summary()['total']);
        $this->assertSame(1, app(SubscriptionRevenue::class)->summary()['premium']);
        $this->assertSame(0, app(SubscriptionRevenue::class)->summary()['pro']);
        $this->assertTrue($profile->hasPremiumSubscription());
        $this->assertTrue($profile->hasProSubscription());
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()->assertJsonPath('data.subscription_tier', 'premium')->assertJsonPath('data.is_premium', true);
        $this->actingAs($profile->user, 'web')->getJson('/api/v1/technician/subscription')->assertJsonPath('data.current_plan_slug', 'premium')->assertJsonPath('data.payments.data.0.amount', '399.00');
    }

    public static function transitions(): array
    {
        return [
            'Free to Pro' => ['free', 'pro', false, 'active', '199.00'],
            'Free to Premium' => ['free', 'premium', false, 'active', '399.00'],
            'Pro to Premium' => ['pro', 'premium', false, 'scheduled', '598.00'],
            'Premium to Pro after current period' => ['premium', 'pro', false, 'scheduled', '598.00'],
            'Pro renewal' => ['pro', 'pro', false, 'active', '398.00'],
            'Premium renewal' => ['premium', 'premium', false, 'active', '798.00'],
            'Expired Pro to Premium' => ['pro', 'premium', true, 'active', '598.00'],
            'Expired Premium to Pro' => ['premium', 'pro', true, 'active', '598.00'],
        ];
    }

    #[DataProvider('transitions')]
    public function test_plan_changes_and_renewals_preserve_paid_time_and_history(string $before, string $after, bool $expired, string $expectedStatus, string $revenue): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $admin = $this->admin();
        $service = app(SubscriptionService::class);
        $first = null;
        if ($before !== 'free') {
            $first = $this->payment($profile, $before);
            $service->decide($admin, $first, 'approved');
            $originalEnd = $first->subscription->expires_at;
            $this->travel($expired ? 31 : 5)->days();
        }
        $payment = $this->payment($profile, $after);
        $service->decide($admin, $payment, 'approved');
        $cycle = $payment->subscription;
        $this->assertSame($expectedStatus, $cycle->status);
        $this->assertSame('approved', $payment->fresh()->status);
        if ($expectedStatus === 'scheduled') {
            $this->assertTrue($cycle->starts_at->equalTo($originalEnd));
            $this->assertTrue($cycle->expires_at->equalTo($originalEnd->addDays(30)));
            $this->assertSame('active', $first->subscription->fresh()->status);
            $this->assertTrue($first->subscription->fresh()->expires_at->equalTo($originalEnd));
            $this->assertSame($before === 'premium', $profile->hasPremiumSubscription());
            $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription')->assertJsonPath('data.current_plan_slug', $before)->assertJsonPath('data.scheduled_subscription.plan.slug', $after);
            $this->travelTo($originalEnd);
            $this->assertSame($after === 'premium', $profile->hasPremiumSubscription(), 'Timestamp access must not depend on the scheduler running.');
        } elseif ($before === $after && ! $expired) {
            $this->assertTrue($cycle->expires_at->equalTo($originalEnd->addDays(30)));
            $this->assertSame('cancelled', $first->subscription->fresh()->status);
        } else {
            $this->assertTrue($cycle->starts_at->equalTo(now()));
            $this->assertTrue($cycle->expires_at->equalTo(now()->addDays(30)));
        }
        $end = $cycle->expires_at;
        $service->decide($admin, $payment, 'approved');
        $this->assertTrue($cycle->fresh()->expires_at->equalTo($end));
        $this->assertSame($revenue, app(SubscriptionRevenue::class)->summary()['total']);
        $this->assertDatabaseCount('subscription_demo_payments', $first ? 2 : 1);
    }

    public function test_scheduled_upgrade_blocks_conflicts_and_the_scheduler_activates_without_shifting_dates(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $pro = $this->payment($profile, 'pro');
        $service->decide($admin, $pro, 'approved');
        $premium = $this->payment($profile, 'premium');
        $service->decide($admin, $premium, 'approved');
        $starts = $premium->subscription->starts_at;
        $ends = $premium->subscription->expires_at;
        $this->actingAs($profile->user)->postJson('/api/v1/technician/subscription/demo-payments', ['idempotency_key' => (string) Str::uuid(), 'plan_slug' => 'pro', 'expected_price' => '199.00', 'confirm_demo' => true])->assertUnprocessable();
        $this->assertDatabaseCount('subscription_demo_payments', 2);
        $this->travelTo($starts->addDays(2));
        $this->assertTrue($profile->hasPremiumSubscription());
        $this->artisan('erepair:subscriptions-expire')->assertSuccessful();
        $this->artisan('erepair:subscriptions-expire')->assertSuccessful();
        $this->assertSame('expired', $pro->subscription->fresh()->status);
        $this->assertSame('active', $premium->subscription->fresh()->status);
        $this->assertTrue($premium->subscription->fresh()->expires_at->equalTo($ends));
        $this->assertSame(1, $profile->subscriptions()->where('status', 'active')->count());
        $this->travelTo($ends);
        $this->assertFalse($profile->hasProSubscription());
        $this->assertSame('598.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_rejected_premium_does_not_replace_pro_or_increase_revenue(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $service->decide($admin, $this->payment($profile, 'pro'), 'approved');
        $premium = $this->payment($profile, 'premium');
        $service->decide($admin, $premium, 'rejected');
        $service->decide($admin, $premium, 'approved');
        $this->assertTrue($profile->hasProSubscription());
        $this->assertFalse($profile->hasPremiumSubscription());
        $this->assertSame('cancelled', $premium->subscription->status);
        $this->assertSame('rejected', $premium->fresh()->status);
        $this->assertSame('199.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_checkout_rejects_invalid_plans_tampering_and_cross_plan_idempotency_reuse(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $request = ['idempotency_key' => (string) Str::uuid(), 'plan_slug' => 'premium', 'expected_price' => '399.00', 'confirm_demo' => true];
        $this->actingAs(User::factory()->create())->postJson('/api/v1/technician/subscription/demo-payments', $request)->assertForbidden();
        $this->actingAs($profile->user)->postJson('/api/v1/technician/subscription/demo-payments', [...$request, 'plan_slug' => 'free'])->assertUnprocessable();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$request, 'expected_price' => '199.00'])->assertUnprocessable();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$request, 'status' => 'active'])->assertUnprocessable();
        $this->postJson('/api/v1/technician/subscription/demo-payments', $request)->assertOk();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$request, 'plan_slug' => 'pro', 'expected_price' => '199.00'])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->assertDatabaseCount('subscription_demo_payments', 1);
    }

    public function test_pending_premium_quotes_survive_plan_edits_and_deactivation(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $payment = $this->payment($profile, 'premium');
        SubscriptionPlan::where('slug', 'premium')->firstOrFail()->update(['price' => '499.00', 'name' => 'Renamed Premium', 'is_active' => false]);
        app(SubscriptionService::class)->decide($this->admin(), $payment, 'approved');
        $this->assertSame('399.00', $payment->fresh()->amount);
        $this->assertSame('Premium Technician', $payment->fresh()->plan_name);
        $this->assertTrue($profile->hasPremiumSubscription());
        $this->assertSame('399.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_premium_promotion_cannot_bypass_availability_location_sort_or_verification(): void
    {
        $free = TechnicianProfile::factory()->approved()->create(['years_experience' => 10, 'latitude' => 14, 'longitude' => 121]);
        $pro = TechnicianProfile::factory()->approved()->create(['years_experience' => 10, 'latitude' => 15, 'longitude' => 122]);
        $premium = TechnicianProfile::factory()->approved()->create(['years_experience' => 10, 'latitude' => 16, 'longitude' => 123]);
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $service->decide($admin, $this->payment($pro, 'pro'), 'approved');
        $service->decide($admin, $this->payment($premium, 'premium'), 'approved');
        $finder = app(TechnicianFinder::class);
        $this->assertSame([$premium->id, $pro->id, $free->id], $finder->search([])->pluck('id')->all());
        $device = DeviceType::create(['name' => 'Compatible laptop', 'slug' => 'premium-compatible-laptop']);
        $free->deviceTypes()->attach($device);
        $pro->deviceTypes()->attach($device);
        $this->assertSame([$pro->id, $free->id], $finder->search(['device_type' => $device->id])->pluck('id')->all());
        $premium->update(['years_experience' => 1]);
        $this->assertSame([$pro->id, $free->id, $premium->id], $finder->search(['sort' => 'experience'])->pluck('id')->all());
        $this->assertSame([$free->id, $pro->id, $premium->id], $finder->search(['sort' => 'distance', 'latitude' => 14, 'longitude' => 121])->pluck('id')->all());
        $premium->update(['availability' => 'busy']);
        $this->assertSame([$pro->id, $free->id, $premium->id], $finder->search([])->pluck('id')->all());
        $premium->update(['city' => 'Pola', 'province' => 'Oriental Mindoro']);
        $this->assertSame([$pro->id, $free->id], $finder->search(['city' => 'Santa Cruz', 'province' => 'Laguna'])->pluck('id')->all());
        $premium->forceFill(['verification_status' => 'pending'])->save();
        $this->assertSame([$pro->id, $free->id], $finder->search([])->pluck('id')->all());
    }

    public function test_cms_filters_and_revenue_separate_active_pro_premium_and_free_tiers(): void
    {
        $free = TechnicianProfile::factory()->approved()->create();
        $pro = TechnicianProfile::factory()->approved()->create();
        $premium = TechnicianProfile::factory()->approved()->create();
        $admin = $this->admin();
        $service = app(SubscriptionService::class);
        $service->decide($admin, $this->payment($pro, 'pro'), 'approved');
        $service->decide($admin, $this->payment($premium, 'premium'), 'approved');
        $summary = app(SubscriptionRevenue::class)->summary();
        $this->assertSame(1, $summary['free']);
        $this->assertSame(1, $summary['pro']);
        $this->assertSame(1, $summary['premium']);
        $this->assertSame(['pro' => '199.00', 'premium' => '399.00'], $summary['by_plan']);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->get('/cp/erepair/subscriptions?tier=premium')
            ->assertOk()->assertViewHas('technicians', fn ($rows) => $rows->pluck('id')->all() === [$premium->id]);
        $this->get('/cp/erepair/subscriptions?tier=pro')->assertViewHas('technicians', fn ($rows) => $rows->pluck('id')->all() === [$pro->id]);
        $this->get('/cp/erepair/subscriptions?tier=free')->assertViewHas('technicians', fn ($rows) => $rows->pluck('id')->all() === [$free->id]);
        $this->get('/cp/erepair/revenue?paying_technicians=10&projection_plan=premium')->assertOk()->assertSee('₱3,990.00')->assertSee('₱47,880.00')->assertSee('Active Premium Technicians');
        $this->assertSame('598.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_approval_after_the_existing_plan_expires_starts_a_full_premium_period_immediately(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $pro = $this->payment($profile, 'pro');
        $service->decide($admin, $pro, 'approved');
        $payment = $this->payment($profile, 'premium');
        $this->travel(35)->days();
        $service->decide($admin, $payment, 'approved');
        $this->assertSame('active', $payment->subscription->status);
        $this->assertTrue($payment->subscription->starts_at->equalTo(now()));
        $this->assertTrue($payment->subscription->expires_at->equalTo(now()->addDays(30)));
        $this->assertSame('expired', $pro->subscription->fresh()->status);
    }

    public function test_scheduled_periods_require_dates_and_database_uniqueness_prevents_a_second_queued_plan(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $service->decide($admin, $this->payment($profile, 'pro'), 'approved');
        $premium = $this->payment($profile, 'premium');
        $service->decide($admin, $premium, 'approved');
        try {
            DB::transaction(fn () => $premium->subscription->update(['expires_at' => null]));
            $this->fail('A scheduled period without expiration must be rejected.');
        } catch (QueryException $error) {
            $this->assertSame('23514', $error->errorInfo[0]);
        }
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $profile->subscriptions()->create(['subscription_plan_id' => $premium->subscription->subscription_plan_id,
            'status' => 'scheduled', 'starts_at' => now()->addDays(30), 'expires_at' => now()->addDays(60)]));
    }

    public function test_a_missed_scheduled_period_never_grants_expired_access_or_changes_simulated_revenue(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $service->decide($admin, $this->payment($profile, 'pro'), 'approved');
        $premium = $this->payment($profile, 'premium');
        $service->decide($admin, $premium, 'approved');
        $this->travelTo($premium->subscription->expires_at);
        $this->assertFalse($profile->hasPremiumSubscription());
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription/analytics?range_days=7')->assertForbidden();
        $this->getJson('/api/v1/technician/subscription')->assertJsonPath('data.current_plan_slug', 'free')->assertJsonPath('data.status', 'expired');
        $this->assertSame(2, app(SubscriptionRevenue::class)->summary()['expired']);
        $this->artisan('erepair:subscriptions-expire')->assertSuccessful();
        $this->assertSame(0, $profile->subscriptions()->whereIn('status', ['active', 'scheduled'])->count());
        $this->assertSame('598.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_premium_date_ranges_count_real_daily_views_and_inquiries_in_philippine_time(): void
    {
        $profile = TechnicianProfile::factory()->approved()->create();
        $other = TechnicianProfile::factory()->approved()->create();
        $service = app(SubscriptionService::class);
        $admin = $this->admin();
        $service->decide($admin, $this->payment($profile, 'premium'), 'approved');
        $service->decide($admin, $this->payment($other, 'pro'), 'approved');
        DB::table('technician_profile_views')->insert([
            ['technician_id' => $profile->id, 'viewed_on' => '2026-11-12', 'visitor_hash' => str_repeat('a', 64)],
            ['technician_id' => $profile->id, 'viewed_on' => '2026-11-06', 'visitor_hash' => str_repeat('b', 64)],
            ['technician_id' => $profile->id, 'viewed_on' => '2026-11-05', 'visitor_hash' => str_repeat('c', 64)],
            ['technician_id' => $other->id, 'viewed_on' => '2026-11-12', 'visitor_hash' => str_repeat('d', 64)],
        ]);
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'premium-laptop']);
        $brand = DeviceBrand::create(['name' => 'Acer', 'slug' => 'premium-acer']);
        $category = RepairCategory::create(['name' => 'Battery', 'slug' => 'premium-battery']);
        foreach (['2026-11-05 16:00:00+00', '2026-11-05 15:59:59+00'] as $index => $date) {
            RepairRequest::forceCreate(['customer_id' => User::factory()->create()->id, 'technician_profile_id' => $profile->id,
                'device_type_id' => $type->id, 'device_brand_id' => $brand->id, 'repair_category_id' => $category->id,
                'model' => 'Premium analytics fixture', 'problem_description' => 'Actual inquiry fixture.', 'status' => 'pending', 'reference' => 'PREMIUM-ANALYTICS-'.$index, 'created_at' => $date]);
        }
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription/analytics?range_days=7')->assertOk()
            ->assertJsonCount(7, 'data.premium.daily')->assertJsonPath('data.premium.from', '2026-11-06')
            ->assertJsonPath('data.premium.views', 2)->assertJsonPath('data.premium.inquiries', 1)
            ->assertJsonPath('data.premium.previous_period.views', 1)->assertJsonPath('data.premium.previous_period.inquiries', 1)
            ->assertJsonPath('data.premium.daily.0.inquiries', 1)->assertJsonPath('data.premium.daily.1.views', 0);
        $this->getJson('/api/v1/technician/subscription/analytics?range_days=90')->assertOk()->assertJsonCount(90, 'data.premium.daily');
        $this->getJson('/api/v1/technician/subscription/analytics?range_days=365')->assertUnprocessable();
        $this->actingAs($other->user)->getJson('/api/v1/technician/subscription/analytics?range_days=7')->assertForbidden();
        $this->getJson('/api/v1/technician/subscription/analytics')->assertOk()->assertJsonMissingPath('data.premium');
    }
}
