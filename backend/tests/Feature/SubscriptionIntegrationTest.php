<?php

namespace Tests\Feature;

use App\Models\CmsUser;
use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\TechnicianSubscription;
use App\Models\User;
use App\Services\CmsAccount;
use App\Services\SubscriptionRevenue;
use App\Services\SubscriptionService;
use App\Services\TechnicianFinder;
use App\Support\SubscriptionMoney;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionIntegrationTest extends TestCase
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
        $this->travelTo(now()->setDate(2026, 11, 11)->startOfDay());
    }

    private function technician(): TechnicianProfile
    {
        return TechnicianProfile::factory()->approved()->create();
    }

    private function editor(): CmsUser
    {
        return app(CmsAccount::class)->provision(User::factory()->create(['role' => 'admin', 'super' => true]));
    }

    private function requestPayment(TechnicianProfile $profile): SubscriptionDemoPayment
    {
        return app(SubscriptionService::class)->request($profile->user, (string) Str::uuid(), SubscriptionPlan::where('slug', 'pro')->firstOrFail()->price);
    }

    public function test_free_plan_is_default_and_customer_and_free_repairs_remain_accessible(): void
    {
        $profile = $this->technician();
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription')->assertOk()
            ->assertJsonPath('data.current_plan', 'Free')->assertJsonPath('data.is_pro', false)->assertJsonPath('data.payments.total', 0);
        $this->getJson('/api/v1/dashboard')->assertOk();
        $this->getJson('/api/v1/repair-requests')->assertOk();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/technicians')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/technician/subscription')->assertForbidden();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [])->assertForbidden();
        $this->assertSame(0, TechnicianSubscription::count());
    }

    public function test_upgrade_creates_a_real_pending_demo_payment_and_prevents_duplicate_requests(): void
    {
        $profile = $this->technician();
        $data = ['idempotency_key' => (string) Str::uuid(), 'expected_price' => '199.00', 'confirm_demo' => true];
        $this->actingAs($profile->user)->postJson('/api/v1/technician/subscription/demo-payments', $data)->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.is_demo', true)->assertJsonPath('data.amount', '199.00');
        $this->postJson('/api/v1/technician/subscription/demo-payments', $data)->assertOk();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$data, 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
        $this->assertSame(1, SubscriptionDemoPayment::count());
        $this->assertStringStartsWith('DEMO-', SubscriptionDemoPayment::sole()->transaction_reference);
        $this->assertFalse($profile->hasProSubscription());
        $this->assertSame('0.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_request_rejects_price_changes_forged_activation_and_missing_confirmation(): void
    {
        $profile = $this->technician();
        $data = ['idempotency_key' => (string) Str::uuid(), 'expected_price' => '1.00', 'confirm_demo' => true];
        $this->actingAs($profile->user)->postJson('/api/v1/technician/subscription/demo-payments', $data)->assertUnprocessable();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$data, 'expected_price' => '199.00', 'status' => 'active'])->assertUnprocessable();
        $this->postJson('/api/v1/technician/subscription/demo-payments', [...$data, 'expected_price' => '199.00', 'confirm_demo' => false])->assertUnprocessable();
        $this->assertSame(0, SubscriptionDemoPayment::count());
    }

    public function test_subscription_references_are_consistent_and_searchable_without_changing_audit_references(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $reference = 'SUB-'.str_pad((string) $payment->id, 6, '0', STR_PAD_LEFT);
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription')->assertOk()
            ->assertJsonPath('data.pending_payment.display_reference', $reference)
            ->assertJsonPath('data.payments.data.0.display_reference', $reference);
        $this->assertStringStartsWith('DEMO-', $payment->transaction_reference);
        $other = $this->requestPayment($this->technician());
        $this->actingAs($this->editor(), 'cp')->get('/cp/erepair/subscriptions?search='.$reference)
            ->assertOk()->assertSee($reference)->assertSee($payment->transaction_reference)
            ->assertDontSee($other->transaction_reference);
    }

    public function test_plan_copy_migration_preserves_admin_customizations(): void
    {
        $migration = require database_path('migrations/2026_11_11_000002_update_subscription_plan_copy.php');
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $pro->update(['description' => 'Custom technician benefits']);
        $migration->up();
        $this->assertSame('Custom technician benefits', $pro->fresh()->description);
        $pro->update(['description' => 'Optional subscription benefits. DEMO payments only; no real money is processed.']);
        $migration->up();
        $this->assertSame('Reach more customers with featured visibility, a Pro subscription badge and profile analytics.', $pro->fresh()->description);
    }

    public function test_native_admin_approval_activates_pro_and_counts_exactly_one_payment(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $cms = $this->editor();
        $url = '/cp/erepair/subscriptions/payments/'.$payment->id;
        $this->actingAs($cms, 'cp')->post($url, ['decision' => 'approved'])->assertRedirect();
        $this->assertTrue($profile->hasProSubscription());
        $payment->refresh();
        $expires = $payment->subscription->expires_at;
        $this->assertSame($cms->user_id, $payment->approved_by);
        $this->assertTrue($expires->equalTo(now()->addDays(30)));
        $this->post($url, ['decision' => 'approved'])->assertRedirect();
        $this->post($url, ['decision' => 'rejected'])->assertRedirect();
        $this->assertTrue($payment->subscription->fresh()->expires_at->equalTo($expires));
        $this->assertSame('approved', $payment->fresh()->status);
        $this->assertSame('199.00', app(SubscriptionRevenue::class)->summary()['total']);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()->assertJsonPath('data.is_pro', true)->assertJsonPath('data.verified', true);
    }

    public function test_rejection_grants_no_benefits_and_allows_a_new_request(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        app(SubscriptionService::class)->decide($this->editor()->operator, $payment, 'rejected');
        $this->assertFalse($profile->hasProSubscription());
        $this->assertNotNull($payment->fresh()->rejected_at);
        $this->assertSame('cancelled', $payment->subscription->status);
        $this->assertNotSame($payment->id, $this->requestPayment($profile)->id);
        $this->assertSame('0.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_marketplace_users_cannot_review_payments(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $cms = $this->editor();
        $this->actingAs($profile->user, 'web')->post('/cp/erepair/subscriptions/payments/'.$payment->id, ['decision' => 'approved'])->assertRedirect('/cp/auth/login');
        $this->get('/cp/erepair/revenue')->assertRedirect('/cp/auth/login');
        $this->assertSame('pending', $payment->fresh()->status);
        $this->expectException(AuthorizationException::class);
        app(SubscriptionService::class)->decide($profile->user, $payment, 'approved');
    }

    public function test_suspended_administrator_cannot_review_payments_in_an_existing_cp_session(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $cms = $this->editor();
        $this->actingAs($cms, 'cp')->get('/cp/erepair/subscriptions')->assertOk();
        $cms->operator()->update(['account_status' => 'suspended']);
        $this->postJson('/cp/erepair/subscriptions/payments/'.$payment->id, ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_active_renewal_preserves_remaining_time_and_idempotent_approval(): void
    {
        $profile = $this->technician();
        $admin = $this->editor()->operator;
        $first = $this->requestPayment($profile);
        $service = app(SubscriptionService::class);
        $service->decide($admin, $first, 'approved');
        $originalEnd = $first->subscription->expires_at;
        $this->travel(5)->days();
        $renewal = $this->requestPayment($profile);
        $this->assertTrue($profile->hasProSubscription());
        $this->assertTrue($first->subscription->fresh()->expires_at->equalTo($originalEnd));
        $service->decide($admin, $renewal, 'approved');
        $service->decide($admin, $renewal, 'approved');
        $this->assertTrue($renewal->subscription->expires_at->equalTo($originalEnd->addDays(30)));
        $this->assertSame(1, $profile->subscriptions()->where('status', 'active')->count());
        $this->assertSame('cancelled', $first->subscription->fresh()->status);
        $this->assertSame('398.00', app(SubscriptionRevenue::class)->summary()['total']);
    }

    public function test_expiration_checks_timestamp_before_scheduler_and_renewal_restores_access(): void
    {
        $profile = $this->technician();
        $admin = $this->editor()->operator;
        $service = app(SubscriptionService::class);
        $payment = $this->requestPayment($profile);
        $service->decide($admin, $payment, 'approved');
        $this->travel(30)->days();
        $this->assertFalse($profile->hasProSubscription());
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertJsonPath('data.is_pro', false);
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription')->assertJsonPath('data.status', 'expired')->assertJsonPath('data.days_remaining', 0)->assertJsonPath('data.expires_at', $payment->subscription->expires_at->toJSON());
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription/analytics')->assertForbidden();
        $this->artisan('erepair:subscriptions-expire')->assertSuccessful();
        $this->assertSame('expired', $payment->subscription->fresh()->status);
        $this->assertSame('199.00', app(SubscriptionRevenue::class)->summary()['total']);
        $renewal = $this->requestPayment($profile);
        $service->decide($admin, $renewal, 'approved');
        $this->assertTrue($renewal->subscription->expires_at->equalTo(now()->addDays(30)));
        $this->assertTrue($profile->hasProSubscription());
        $this->getJson('/api/v1/repair-requests')->assertOk();
    }

    public function test_plan_edits_preserve_pending_quotes_and_free_price_cannot_change(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $cms = $this->editor();
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $data = ['name' => 'Pro Technician', 'description' => 'Optional DEMO benefits', 'price' => '249.50', 'features' => "All Free features\nProfile analytics", 'is_active' => '1'];
        $this->actingAs($cms, 'cp')->post('/cp/erepair/subscriptions/plans/'.$pro->id, $data)->assertRedirect();
        app(SubscriptionService::class)->decide($cms->operator, $payment, 'approved');
        $this->assertSame('199.00', $payment->fresh()->amount);
        $this->assertSame('249.50', app(SubscriptionRevenue::class)->projection(1)['monthly']);
        $free = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        $this->post('/cp/erepair/subscriptions/plans/'.$free->id, $data)->assertSessionHasErrors('price');
        $this->assertSame('0.00', $free->fresh()->price);
    }

    public function test_revenue_uses_approval_dates_philippine_boundaries_zero_months_and_exact_centavos(): void
    {
        $admin = $this->editor()->operator;
        $service = app(SubscriptionService::class);
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $pro->update(['price' => '199.10']);
        $a = $this->requestPayment($this->technician());
        $service->decide($admin, $a, 'approved');
        $a->update(['approved_at' => '2026-10-31 16:00:00+00']); // Nov 1 PH
        $b = $this->requestPayment($this->technician());
        $service->decide($admin, $b, 'approved');
        $b->update(['approved_at' => '2026-10-31 15:59:59+00']); // Oct PH
        $service->decide($admin, $this->requestPayment($this->technician()), 'rejected');
        $this->requestPayment($this->technician());
        $summary = app(SubscriptionRevenue::class)->summary();
        $this->assertSame('398.20', $summary['total']);
        $this->assertSame('199.10', $summary['month']);
        $this->assertSame('398.20', $summary['year']);
        $this->assertCount(12, $summary['months']);
        $this->assertSame('0.00', $summary['months'][0]['amount']);
        $this->assertSame('199.10', $summary['months'][10]['amount']);
        $this->assertSame('199.10', $summary['months'][11]['amount']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['rejected']);
    }

    public function test_projection_is_separate_from_approved_demo_revenue(): void
    {
        $revenue = app(SubscriptionRevenue::class);
        $this->assertSame('1990.00', $revenue->projection(10)['monthly']);
        $this->assertSame('9950.00', $revenue->projection(50)['monthly']);
        $this->assertSame('119400.00', $revenue->projection(50)['annual']);
        $this->assertSame('19900.00', $revenue->projection(100)['monthly']);
        $this->assertSame('0.00', $revenue->summary()['total']);
        $this->assertSame('₱1,990.00', SubscriptionMoney::format('1990.00'));
    }

    public function test_finder_preserves_free_visibility_filters_and_distance_with_pro_only_as_tie_breaker(): void
    {
        $free = $this->technician();
        $pro = $this->technician();
        $free->update(['years_experience' => 10, 'latitude' => 14.0, 'longitude' => 121.0]);
        $pro->update(['years_experience' => 10, 'latitude' => 15.0, 'longitude' => 122.0]);
        app(SubscriptionService::class)->decide($this->editor()->operator, $this->requestPayment($pro), 'approved');
        $finder = app(TechnicianFinder::class);
        $this->assertSame([$pro->id, $free->id], $finder->search(['city' => 'Santa Cruz'])->pluck('id')->all());
        $this->assertSame([$free->id, $pro->id], $finder->search(['sort' => 'distance', 'latitude' => 14, 'longitude' => 121])->pluck('id')->all());
        $pro->update(['availability' => 'busy']);
        $this->assertSame([$free->id, $pro->id], $finder->search([])->pluck('id')->all());
        $this->assertSame([$free->id], $finder->search(['availability' => 'available'])->pluck('id')->all());
        $this->assertSame(0, $finder->search(['city' => 'Unrelated city'])->total());
        $pro->forceFill(['verification_status' => 'pending'])->save();
        $this->assertSame([$free->id], $finder->search([])->pluck('id')->all());
    }

    public function test_profile_views_are_real_deduplicated_private_and_pro_analytics_enforced(): void
    {
        $profile = $this->technician();
        $other = $this->technician();
        $this->postJson('/api/v1/technicians/'.$profile->id.'/view')->assertNoContent();
        $this->postJson('/api/v1/technicians/'.$profile->id.'/view')->assertNoContent();
        $this->assertSame(1, DB::table('technician_profile_views')->count());
        $row = (array) DB::table('technician_profile_views')->first();
        $this->assertSame(['id', 'technician_id', 'viewed_on', 'visitor_hash'], array_keys($row));
        $this->assertSame(64, strlen($row['visitor_hash']));
        $this->actingAs($profile->user)->postJson('/api/v1/technicians/'.$profile->id.'/view')->assertNoContent();
        $this->assertSame(1, DB::table('technician_profile_views')->count());
        $this->getJson('/api/v1/technician/subscription/analytics')->assertForbidden();
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'analytics-laptop']);
        $brand = DeviceBrand::create(['name' => 'Acer', 'slug' => 'analytics-acer']);
        $category = RepairCategory::create(['name' => 'Battery', 'slug' => 'analytics-battery']);
        RepairRequest::forceCreate(['customer_id' => User::factory()->create()->id, 'technician_profile_id' => $profile->id, 'device_type_id' => $type->id, 'device_brand_id' => $brand->id, 'repair_category_id' => $category->id, 'model' => 'DEMO analytics fixture', 'problem_description' => 'Battery inquiry in subscription analytics test.', 'status' => 'pending', 'reference' => 'DEMO-ANALYTICS-TEST']);
        app(SubscriptionService::class)->decide($this->editor()->operator, $this->requestPayment($profile), 'approved');
        $this->actingAs($profile->user)->getJson('/api/v1/technician/subscription/analytics')->assertOk()->assertJsonPath('data.total_profile_views', 1)->assertJsonPath('data.total_customer_inquiries', 1)->assertJsonCount(6, 'data.months')->assertJsonPath('data.months.5.inquiries', 1);
        $this->actingAs($other->user)->getJson('/api/v1/technician/subscription')->assertJsonCount(0, 'data.payments.data');
        $this->getJson('/api/v1/technician/subscription/analytics')->assertForbidden();
    }

    public function test_core_routes_management_revenue_filters_and_scripts_remain_compatible(): void
    {
        $this->requestPayment($this->technician());
        $cms = $this->editor();
        $this->actingAs($cms, 'cp')->get('/cp/erepair/subscriptions')->assertOk()->assertSee('DEMO / SIMULATED REVENUE')->assertSee('Approve DEMO payment');
        $this->get('/cp/erepair/subscriptions?status=rejected')->assertOk()->assertSee('No DEMO payments match');
        $this->get('/cp/erepair/revenue?paying_technicians=50')->assertOk()->assertSee('₱9,950.00')->assertSee('₱119,400.00');
        $this->get('/cp/erepair/revenue?paying_technicians=-1')->assertSessionHasErrors('paying_technicians');
        $this->get('/cp/erepair')->assertOk();
        $this->get('/cp/collections')->assertOk();
        $this->assertFalse(config('statamic.editions.pro'));
        $this->assertSame(1, CmsUser::count());
    }

    public function test_database_pending_uniqueness_rejects_bypasses_outside_the_service(): void
    {
        $profile = $this->technician();
        $payment = $this->requestPayment($profile);
        $this->expectException(QueryException::class);
        DB::transaction(fn () => $profile->subscriptions()->create(['subscription_plan_id' => $payment->subscription->subscription_plan_id, 'status' => 'pending']));
    }

    public function test_demo_setup_is_idempotent_and_reset_preserves_users_repairs_and_manual_payments(): void
    {
        $profile = $this->technician();
        $profile->user->update(['email' => 'subscriptions-demo@erepair.test']);
        $this->editor();
        $users = User::count();
        $this->artisan('erepair:subscriptions-demo', ['--technician' => $profile->id])->assertSuccessful();
        $this->artisan('erepair:subscriptions-demo', ['--technician' => $profile->id])->assertSuccessful();
        $this->assertSame(4, SubscriptionDemoPayment::count());
        $this->assertSame('398.00', app(SubscriptionRevenue::class)->summary()['total']);
        $this->artisan('erepair:subscriptions-demo', ['action' => 'reset', '--technician' => $profile->id, '--confirm' => true])->assertSuccessful();
        $this->assertSame(0, SubscriptionDemoPayment::count());
        $this->assertSame($users, User::count());
        $manual = $this->requestPayment($profile);
        $this->artisan('erepair:subscriptions-demo', ['action' => 'reset', '--technician' => $profile->id, '--confirm' => true])->assertSuccessful();
        $this->assertNotNull($manual->fresh());
        $this->app->detectEnvironment(fn () => 'production');
        $this->artisan('erepair:subscriptions-demo', ['--technician' => $profile->id])->assertFailed();
    }

    public function test_interrupted_demo_setup_rolls_back_every_sample_and_its_audit_entries(): void
    {
        $profile = $this->technician();
        $profile->user->update(['email' => 'atomic-subscriptions@erepair.test']);
        $this->editor();
        $this->app->instance(SubscriptionService::class, new class extends SubscriptionService
        {
            private int $requests = 0;

            public function request(User $user, string $key, string $expectedPrice, bool $seeded = false): SubscriptionDemoPayment
            {
                if (++$this->requests === 2) {
                    throw new \RuntimeException('Interrupted demo setup fixture');
                }

                return parent::request($user, $key, $expectedPrice, $seeded);
            }
        });
        try {
            Artisan::call('erepair:subscriptions-demo', ['--technician' => $profile->id]);
            $this->fail('The interruption fixture must stop setup.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Interrupted demo setup fixture', $error->getMessage());
        }
        $this->assertSame(0, $profile->subscriptions()->count());
        $this->assertSame(0, $profile->demoPayments()->count());
        $this->assertSame(0, DB::table('activity_logs')->where('action', 'like', 'subscription.%')->count());
        $this->assertNotNull($profile->fresh());
    }

    public function test_demo_expiration_requires_the_exact_current_cycle_and_preserves_approved_revenue(): void
    {
        $profile = $this->technician();
        $profile->user->update(['email' => 'expiration-subscriptions@erepair.test']);
        $payment = $this->requestPayment($profile);
        app(SubscriptionService::class)->decide($this->editor()->operator, $payment, 'approved');
        $this->artisan('erepair:subscriptions-demo', ['action' => 'expire', '--technician' => $profile->id, '--reference' => 'DEMO-WRONG'])->assertFailed();
        $this->assertTrue($profile->hasProSubscription());
        $this->artisan('erepair:subscriptions-demo', ['action' => 'expire', '--technician' => $profile->id, '--reference' => $payment->transaction_reference])->assertSuccessful();
        $this->assertFalse($profile->hasProSubscription());
        $this->assertSame('199.00', app(SubscriptionRevenue::class)->summary()['total']);
        $this->artisan('erepair:subscriptions-demo', ['action' => 'expire', '--technician' => $profile->id, '--reference' => $payment->transaction_reference])->assertFailed();
        $this->assertSame('approved', $payment->fresh()->status);
    }
}
