<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\Review;
use App\Models\Specialization;
use App\Models\User;
use App\Services\RepairService;
use App\Services\CmsAccount;
use App\Services\TechnicianVerification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Statamic\Facades\Entry;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ApplicationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    public function test_approved_profile_with_a_customer_account_is_not_public(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $technician->role = 'customer';
        $technician->save();
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires dedicated PostgreSQL integration database.');
        }
        parent::setUp();
        config(['sanctum.stateful' => ['127.0.0.1:5173', '127.0.0.1:8000']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

    private function fixture(): array
    {
        $customer = User::factory()->create();
        $user = User::factory()->create(['role' => 'technician']);
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'laptop']);
        $brand = DeviceBrand::create(['name' => 'Acer', 'slug' => 'acer']);
        $spec = Specialization::create(['name' => 'Battery', 'slug' => 'battery']);
        $category = RepairCategory::create(['name' => 'Battery', 'slug' => 'battery', 'specialization_id' => $spec->id, 'device_type_id' => $type->id]);
        $profile = $user->technicianProfile()->create(['city' => 'Santa Cruz', 'province' => 'Laguna', 'biography' => 'Experienced technician providing battery repairs and clear estimates.']);
        $profile->deviceTypes()->attach($type);
        $profile->specializations()->attach($spec);
        $profile->verification_status = 'approved';
        $profile->save();
        $data = ['technician_profile_id' => $profile->id, 'device_type_id' => $type->id, 'device_brand_id' => $brand->id, 'repair_category_id' => $category->id, 'model' => 'Aspire 5', 'problem_description' => 'Battery does not hold charge even after charging overnight.', 'starts_at' => now()->addDays(2)->toIso8601String(), 'ends_at' => now()->addDays(2)->addHour()->toIso8601String(), 'service_location' => 'Santa Cruz town center'];

        return [$customer, $user, $profile, $data];
    }

    public function test_registration_login_logout_and_role_escalation(): void
    {
        $this->postJson('/api/v1/register', ['name' => 'Ana Reyes', 'email' => 'ana@example.test', 'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!', 'role' => 'customer'])->assertCreated()->assertJsonPath('data.role', 'customer');
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->postJson('/api/v1/login', ['email' => 'ana@example.test', 'password' => 'SafePassword123!'])->assertOk();
        $this->postJson('/api/v1/register', ['name' => 'Bad Admin', 'email' => 'admin2@example.test', 'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!', 'role' => 'admin', 'super' => true])->assertUnprocessable();
    }

    public function test_suspended_participant_can_log_out_without_accessing_private_records(): void
    {
        $user = User::factory()->create(['account_status' => 'suspended']);
        $this->actingAs($user, 'web')->getJson('/api/v1/user')->assertForbidden()
            ->assertJsonPath('code', 'account_inactive');
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_registration_and_login_share_email_normalization(): void
    {
        $this->postJson('/api/v1/register', [
            'name' => 'Ana Reyes', 'email' => ' Ana.Normalized@Example.Test ', 'role' => 'customer',
            'password' => 'SafePassword123!', 'password_confirmation' => 'SafePassword123!',
        ])->assertCreated()->assertJsonPath('data.email', 'ana.normalized@example.test');
        $this->assertDatabaseHas('users', ['email' => 'ana.normalized@example.test', 'role' => 'customer', 'super' => false]);
        $this->postJson('/api/v1/logout')->assertNoContent();
        $this->postJson('/api/v1/login', ['email' => ' ANA.NORMALIZED@EXAMPLE.TEST ', 'password' => 'SafePassword123!'])
            ->assertOk()->assertJsonPath('data.email', 'ana.normalized@example.test');
    }

    public function test_public_browsing_does_not_exhaust_the_login_budget(): void
    {
        [$customer] = $this->fixture();
        for ($request = 0; $request < 8; $request++) {
            $this->getJson('/api/v1/device-types')->assertOk();
        }
        $this->postJson('/api/v1/login', ['email' => $customer->email, 'password' => 'password'])->assertOk();
    }

    public function test_authentication_limit_still_blocks_repeated_failed_logins(): void
    {
        [$customer] = $this->fixture();
        for ($request = 0; $request < 6; $request++) {
            $this->postJson('/api/v1/login', ['email' => $customer->email, 'password' => 'incorrect-password'])->assertUnprocessable();
        }
        $this->postJson('/api/v1/login', ['email' => $customer->email, 'password' => 'incorrect-password'])->assertTooManyRequests();
        $this->getJson('/api/v1/device-types')->assertOk();
    }

    public function test_only_approved_technicians_appear_and_filters_match(): void
    {
        [$customer,$user,$profile,$data] = $this->fixture();
        $this->getJson('/api/v1/technicians?city=Santa%20Cruz&device_type='.$data['device_type_id'])->assertOk()->assertJsonCount(1, 'data');
        $profile->verification_status = 'pending';
        $profile->save();
        $this->getJson('/api/v1/technicians')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
    }

    public function test_repair_creation_and_unrelated_record_protection(): void
    {
        [$customer,$tech,$profile,$data] = $this->fixture();
        $response = $this->actingAs($customer)->postJson('/api/v1/repair-requests', $data)->assertCreated();
        $id = $response->json('data.id');
        $this->assertDatabaseHas('repair_requests', ['id' => $id, 'customer_id' => $customer->id, 'status' => 'pending']);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/repair-requests/'.$id)->assertForbidden();
        $this->actingAs($tech)->getJson('/api/v1/repair-requests/'.$id)->assertOk();
    }

    public function test_estimate_approval_completion_and_unique_review(): void
    {
        [$customer,$tech,$profile,$data] = $this->fixture();
        $repair = app(RepairService::class)->create($customer, $data);
        $url = '/api/v1/repair-requests/'.$repair->id;
        $this->actingAs($tech)->postJson('/api/v1/technician/repair-requests/'.$repair->id.'/accept')->assertOk();
        $appointment = $repair->appointments()->first();
        $this->patchJson('/api/v1/appointments/'.$appointment->id, $data)->assertOk();
        foreach (['scheduled', 'diagnosing'] as $status) {
            $this->patchJson($url.'/status', ['status' => $status])->assertOk();
        }
        $this->postJson($url.'/estimates', ['amount' => '1500.50', 'description' => 'Battery replacement, diagnosis and labor included.'])->assertOk()->assertJsonPath('data.status', 'awaiting_customer_approval');
        $this->patchJson($url.'/status', ['status' => 'in_repair'])->assertUnprocessable();
        $this->actingAs($customer)->postJson($url.'/approve-estimate')->assertOk()->assertJsonPath('data.status', 'in_repair');
        $this->actingAs($tech)->patchJson($url.'/status', ['status' => 'ready_for_pickup'])->assertOk();
        $this->actingAs($customer)->patchJson($url.'/status', ['status' => 'completed'])->assertOk();
        $this->assertDatabaseHas('appointments', ['repair_request_id' => $repair->id, 'status' => 'completed']);
        $review = ['repair_request_id' => $repair->id, 'rating' => 5, 'body' => 'Clear estimate and helpful progress updates.'];
        $this->postJson('/api/v1/reviews', $review)->assertCreated();
        $this->postJson('/api/v1/reviews', $review)->assertUnprocessable();
        $this->getJson('/api/v1/technicians/'.$profile->id.'/reviews')->assertOk()
            ->assertJsonMissingPath('data.0.customer_id')->assertJsonMissingPath('data.0.repair_request_id');
    }

    public function test_only_administrator_can_verify_technicians(): void
    {
        [$customer,$tech,$profile] = $this->fixture();
        $this->expectException(HttpException::class);
        app(TechnicianVerification::class)->decide($customer, $profile, 'approved', null);
    }

    public function test_appointment_conflicts_are_rejected(): void
    {
        [$customer,$tech,$profile,$data] = $this->fixture();
        app(RepairService::class)->create($customer, $data);
        $this->actingAs($customer)->postJson('/api/v1/repair-requests', $data)->assertUnprocessable();
    }

    public function test_administrator_verification_persists_and_enables_public_visibility(): void
    {
        [$customer,$tech,$profile] = $this->fixture();
        $profile->verification_status = 'pending';
        $profile->save();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        app(TechnicianVerification::class)->decide($admin, $profile, 'approved', 'Reviewed the professional profile.');
        $this->assertDatabaseHas('technician_profiles', ['id' => $profile->id, 'verified_by' => $admin->id, 'verification_status' => 'approved']);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk();
    }

    public function test_distance_sort_orders_compatible_public_service_areas(): void
    {
        [$customer,$technician,$near,$data] = $this->fixture();
        $near->update(['latitude' => 14.28, 'longitude' => 121.42]);
        $other = User::factory()->create(['role' => 'technician']);
        $far = $other->technicianProfile()->create(['city' => 'Manila', 'province' => 'Metro Manila', 'latitude' => 14.60, 'longitude' => 120.98, 'biography' => 'A professional offering careful device diagnosis and written estimates.']);
        $far->verification_status = 'approved';
        $far->save();
        $far->deviceTypes()->attach($data['device_type_id']);
        $far->specializations()->sync($near->specializations()->pluck('specializations.id')->all());
        $this->getJson('/api/v1/technicians?'.http_build_query(['device_type' => $data['device_type_id'], 'sort' => 'distance', 'latitude' => 14.28, 'longitude' => 121.42]))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $near->id)->assertJsonPath('data.1.id', $far->id);
    }

    public function test_password_reset_accepts_normalized_email_and_invalidates_stored_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.test']);
        DB::table('sessions')->insert([
            'id' => 'demo-reset-session', 'user_id' => $user->id, 'payload' => 'test-session', 'last_activity' => time(),
        ]);
        $this->postJson('/api/v1/forgot-password', ['email' => 'RESET@example.test'])->assertOk();
        $notice = Notification::sent($user, ResetPassword::class)->first();
        $this->assertNotNull($notice);
        $this->postJson('/api/v1/reset-password', [
            'email' => 'RESET@example.test', 'token' => $notice->token, 'password' => 'NewSafePassword456!', 'password_confirmation' => 'NewSafePassword456!',
        ])->assertOk();
        $this->assertDatabaseMissing('sessions', ['id' => 'demo-reset-session']);
        $this->postJson('/api/v1/login', ['email' => 'reset@example.test', 'password' => 'NewSafePassword456!'])->assertOk();
        $this->postJson('/api/v1/login', ['email' => 'reset@example.test', 'password' => 'password'])->assertUnprocessable();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'reset@example.test']);
    }

    public function test_dashboard_contract_is_scoped_to_the_customer_and_assigned_technician(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $repair = app(RepairService::class)->create($customer, $data);
        $this->actingAs($customer)->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.counts.pending', 1)
            ->assertJsonPath('data.recent.0.id', $repair->id)
            ->assertJsonPath('data.appointments.0.repair_request_id', $repair->id);
        $this->actingAs($technician)->getJson('/api/v1/technician/dashboard')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.recent.0.id', $repair->id);
        $this->actingAs(User::factory()->create())->getJson('/api/v1/dashboard')->assertOk()
            ->assertJsonPath('data.total', 0)->assertJsonCount(0, 'data.recent')->assertJsonCount(0, 'data.appointments');
    }

    public function test_only_the_notification_recipient_can_mark_it_as_read(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        app(RepairService::class)->create($customer, $data);
        $notice = $customer->notifications()->firstOrFail();
        $this->assertNull($notice->read_at);
        $this->actingAs(User::factory()->create())->patchJson('/api/v1/notifications/'.$notice->id.'/read')->assertNotFound();
        $this->actingAs($customer)->patchJson('/api/v1/notifications/'.$notice->id.'/read')->assertNoContent();
        $this->assertNotNull($notice->fresh()->read_at);
    }

    public function test_availability_changes_discovery_without_canceling_existing_work(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $repair = app(RepairService::class)->create($customer, $data);
        $this->actingAs($technician)->patchJson('/api/v1/technician/availability', ['availability' => 'busy'])
            ->assertOk()->assertJsonPath('data.availability', 'busy');
        $this->getJson('/api/v1/technicians?availability=available')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('repair_requests', ['id' => $repair->id, 'status' => 'pending']);
        $this->assertDatabaseHas('appointments', ['repair_request_id' => $repair->id, 'status' => 'proposed']);
        $this->assertDatabaseHas('technician_profiles', ['id' => $profile->id, 'verification_status' => 'approved']);
    }

    public function test_city_and_province_must_match_the_same_service_area(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $profile->update(['city' => 'Santa Cruz', 'province' => 'Marinduque']);
        $profile->serviceAreas()->create(['city' => 'Calamba', 'province' => 'Laguna']);
        $this->getJson('/api/v1/technicians?city=Santa%20Cruz&province=Laguna')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/technicians?city=Calamba&province=Laguna')->assertOk()->assertJsonCount(1, 'data');
        $profile->serviceAreas()->create(['city' => 'Santa Cruz', 'province' => 'Laguna']);
        $this->getJson('/api/v1/technicians?city=Santa%20Cruz&province=Laguna')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_repair_category_search_enforces_its_device_and_specialization(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $category = RepairCategory::findOrFail($data['repair_category_id']);
        $this->getJson('/api/v1/technicians?repair_category='.$category->id)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.matching.reasons.Compatible device', 35)
            ->assertJsonPath('data.0.matching.reasons.Repair specialization', 30);
        $profile->specializations()->detach();
        $this->getJson('/api/v1/technicians?repair_category='.$category->id)->assertOk()->assertJsonCount(0, 'data');
        $profile->specializations()->attach($category->specialization_id);
        $profile->deviceTypes()->detach();
        $this->getJson('/api/v1/technicians?repair_category='.$category->id)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_repair_category_rejects_conflicting_and_inactive_search_criteria(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $otherDevice = DeviceType::create(['name' => 'Mobile', 'slug' => 'mobile']);
        $otherSpecialization = Specialization::create(['name' => 'Screen', 'slug' => 'screen']);
        $base = '/api/v1/technicians?repair_category='.$data['repair_category_id'];
        $this->getJson($base.'&device_type='.$otherDevice->id)->assertUnprocessable()->assertJsonValidationErrors('device_type');
        $this->getJson($base.'&specialization='.$otherSpecialization->id)->assertUnprocessable()->assertJsonValidationErrors('specialization');
        RepairCategory::findOrFail($data['repair_category_id'])->update(['active' => false]);
        $this->getJson($base)->assertUnprocessable()->assertJsonValidationErrors('repair_category');
    }

    public function test_public_profile_rejects_malformed_matching_filters(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $this->getJson('/api/v1/technicians/'.$profile->id.'?city[]=Santa%20Cruz')->assertUnprocessable()->assertJsonValidationErrors('city');
    }

    public function test_customer_rescheduling_requires_confirmation_before_diagnosis(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $repair = app(RepairService::class)->create($customer, $data);
        $appointment = $repair->appointments()->firstOrFail();
        $base = '/api/v1/repair-requests/'.$repair->id;
        $this->actingAs($technician)->postJson('/api/v1/technician/repair-requests/'.$repair->id.'/accept')->assertOk();
        $this->patchJson('/api/v1/appointments/'.$appointment->id, $data)->assertOk();
        $this->patchJson($base.'/status', ['status' => 'scheduled'])->assertOk();
        $data['starts_at'] = now()->addDays(3)->toIso8601String();
        $data['ends_at'] = now()->addDays(3)->addHour()->toIso8601String();
        $this->actingAs($customer)->patchJson('/api/v1/appointments/'.$appointment->id, $data)->assertOk()->assertJsonPath('data.status', 'proposed');
        $this->actingAs($technician)->patchJson($base.'/status', ['status' => 'diagnosing'])->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->assertDatabaseHas('repair_requests', ['id' => $repair->id, 'status' => 'scheduled']);
        $this->patchJson('/api/v1/appointments/'.$appointment->id, $data)->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->patchJson($base.'/status', ['status' => 'diagnosing'])->assertOk();
    }

    public function test_native_cms_operations_render_for_a_shared_database_administrator(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->get('/cp/erepair')->assertOk()->assertSee('eRepair Operations')->assertSee($technician->name);
        $this->get('/cp/erepair/reports.csv')->assertOk()->assertDownload()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_native_operations_deny_customers_technicians_and_suspended_administrators(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        foreach ([$customer, $technician] as $user) {
            $this->actingAs($user, 'web')->get('/cp/erepair')->assertRedirect(cp_route('login'));
            $this->get('/cp/erepair/reports.csv')->assertRedirect(cp_route('login'));
            $this->get('/cp')->assertRedirect(cp_route('login'));
        }
        $suspended = User::factory()->create(['role' => 'admin', 'super' => true, 'account_status' => 'suspended']);
        $this->actingAs(app(CmsAccount::class)->provision($suspended), 'cp')->get('/cp/erepair')->assertForbidden();
        $this->get('/cp/erepair/reports.csv')->assertForbidden();
        $this->get('/cp')->assertForbidden();
        $this->get('/cp/collections')->assertForbidden();
    }

    public function test_native_operations_verify_the_same_technician_used_by_public_discovery(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $profile->verification_status = 'pending';
        $profile->save();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->post('/cp/erepair/technicians/'.$profile->id.'/verify', ['decision' => 'approved', 'notes' => 'Reviewed through the native Control Panel.'])->assertRedirect();
        $this->assertDatabaseHas('technician_profiles', ['id' => $profile->id, 'verification_status' => 'approved', 'verified_by' => $admin->id]);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk();
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $admin->id, 'action' => 'technician.approved']);
    }

    public function test_native_content_exposes_published_editorial_entries(): void
    {
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.hero_title', 'Find the Right Repair Technician for Your Device')
            ->assertJsonFragment(['slug' => 'about'])
            ->assertJsonFragment(['question' => 'How do I choose a technician?']);
    }

    public function test_native_operations_update_customer_status_and_protect_administrator_accounts(): void
    {
        [$customer,$technician,$profile] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->post('/cp/erepair/users/'.$customer->id, ['name' => 'Updated Customer', 'account_status' => 'suspended'])->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $customer->id, 'name' => 'Updated Customer', 'account_status' => 'suspended']);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $admin->id, 'action' => 'user.updated', 'subject_id' => $customer->id]);
        $this->post('/cp/erepair/users/'.$admin->id, ['name' => 'Unexpected change', 'account_status' => 'suspended'])->assertForbidden();
        $this->actingAs($customer->fresh(), 'web')->getJson('/api/v1/user')->assertForbidden();
    }

    public function test_native_catalog_updates_change_the_public_lookup_data(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->post('/cp/erepair/catalog/device-types', ['id' => $data['device_type_id'], 'name' => 'Laptop', 'active' => false])->assertRedirect();
        $this->getJson('/api/v1/device-types')->assertOk()->assertJsonCount(0, 'data');
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $admin->id, 'action' => 'catalog.saved', 'subject_id' => $data['device_type_id']]);
    }

    public function test_native_review_moderation_removes_the_review_from_public_ratings(): void
    {
        [$customer,$technician,$profile,$data] = $this->fixture();
        $repair = app(RepairService::class)->create($customer, $data);
        $repair->status = 'completed';
        $repair->save();
        $review = Review::create(['repair_request_id' => $repair->id, 'customer_id' => $customer->id, 'technician_profile_id' => $profile->id, 'rating' => 5, 'body' => 'Clear communication and careful repairs.']);
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()->assertJsonPath('data.average_rating', 5);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->post('/cp/erepair/reviews/'.$review->id, ['hidden' => true])->assertRedirect();
        $this->getJson('/api/v1/technicians/'.$profile->id.'/reviews')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()->assertJsonPath('data.average_rating', 0);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $admin->id, 'action' => 'review.moderated', 'subject_id' => $review->id]);
    }

    public function test_native_editorial_publish_and_unpublish_change_the_public_content_api(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $slug = 'cms-test-'.strtolower((string) Str::ulid());
        $title = 'Automated CMS publication test';
        try {
            $created = $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->postJson('/cp/collections/announcements/entries/default', [
                '_blueprint' => 'announcement', 'title' => $title, 'slug' => $slug,
                'content' => 'A temporary entry used to verify native publication boundaries.',
                'date' => now()->toIso8601ZuluString('millisecond'), 'published' => false,
            ])->assertOk()->assertJsonPath('saved', true);
            $entry = $created->json('data.id');
            $this->assertNotEmpty($entry);
            $this->getJson('/api/v1/content')->assertOk()->assertJsonMissing(['title' => $title]);
            $base = '/cp/collections/announcements/entries/'.$entry;
            $this->postJson($base.'/publish')->assertOk()->assertJsonPath('saved', true);
            $this->getJson('/api/v1/content')->assertOk()->assertJsonFragment(['title' => $title]);
            $this->postJson($base.'/unpublish')->assertOk()->assertJsonPath('saved', true);
            $this->getJson('/api/v1/content')->assertOk()->assertJsonMissing(['title' => $title]);
        } finally {
            // Remove only the uniquely named entry owned by this test.
            Entry::query()->where('collection', 'announcements')->where('slug', $slug)->first()?->delete();
        }
    }
}
