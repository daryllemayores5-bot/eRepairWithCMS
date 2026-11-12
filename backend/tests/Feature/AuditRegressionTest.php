<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\RepairController;
use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairCategory;
use App\Models\Specialization;
use App\Models\User;
use App\Services\RepairService;
use App\Services\CmsAccount;
use App\Services\TechnicianVerification;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditRegressionTest extends TestCase
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
    }

    private function technician(): array
    {
        $user = User::factory()->create(['role' => 'technician']);
        $profile = $user->technicianProfile()->create(['city' => 'Santa Cruz', 'province' => 'Laguna', 'biography' => 'Experienced laptop battery repair technician.']);

        return [$user, $profile];
    }

    private function repair(): array
    {
        [$technician, $profile] = $this->technician();
        $profile->verification_status = 'approved';
        $profile->save();
        $customer = User::factory()->create();
        $type = DeviceType::create(['name' => 'Laptop', 'slug' => 'laptop']);
        $brand = DeviceBrand::create(['name' => 'Acer', 'slug' => 'acer']);
        $spec = Specialization::create(['name' => 'Battery', 'slug' => 'battery']);
        $category = RepairCategory::create(['name' => 'Battery', 'slug' => 'battery', 'device_type_id' => $type->id, 'specialization_id' => $spec->id]);
        $profile->deviceTypes()->attach($type);
        $profile->specializations()->attach($spec);
        $repair = app(RepairService::class)->create($customer, [
            'technician_profile_id' => $profile->id, 'device_type_id' => $type->id,
            'device_brand_id' => $brand->id, 'repair_category_id' => $category->id,
            'model' => 'Aspire 5', 'problem_description' => 'Battery does not hold charge overnight.',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDays(2)->addHour()->toIso8601String(), 'service_location' => 'Santa Cruz center',
        ]);

        return [$technician, $repair];
    }

    public function test_pending_photo_is_visible_only_to_its_owner(): void
    {
        Storage::fake('local');
        [$owner, $profile] = $this->technician();
        $response = $this->actingAs($owner, 'web')->postJson('/api/v1/technician/photo', ['photo' => UploadedFile::fake()->image('profile.png')])->assertOk();
        $response->assertJsonPath('data.photo_url', url('/api/v1/technician/photo'));
        $this->get('/api/v1/technician/photo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/technicians/'.$profile->id.'/photo')->assertNotFound();
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
        [$other] = $this->technician();
        app('auth')->forgetGuards();
        $this->actingAs($other, 'web')->getJson('/api/v1/technician/photo')->assertNotFound();
        app('auth')->forgetGuards();
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/technician/photo')->assertForbidden();
    }

    public function test_photo_replacement_reads_the_current_database_path(): void
    {
        Storage::fake('local');
        [$owner, $profile] = $this->technician();
        $stale = clone $profile;
        $stale->photo_path = 'technician-photos/stale.png';
        $owner->setRelation('technicianProfile', $stale);
        Storage::disk('local')->put('technician-photos/current.png', 'old-image');
        $profile->update(['photo_path' => 'technician-photos/current.png']);
        $this->actingAs($owner, 'web')->postJson('/api/v1/technician/photo', ['photo' => UploadedFile::fake()->image('new.png')])->assertOk();
        Storage::disk('local')->assertMissing('technician-photos/current.png');
        Storage::disk('local')->assertExists($profile->fresh()->photo_path);
    }

    public function test_cms_editor_can_review_pending_photos_without_making_them_public(): void
    {
        Storage::fake('local');
        [$owner, $profile] = $this->technician();
        $this->actingAs($owner, 'web')->postJson('/api/v1/technician/photo', ['photo' => UploadedFile::fake()->image('pending.png')])->assertOk();
        $path = '/cp/erepair/technicians/'.$profile->id.'/photo';
        app('auth')->shouldUse('web');
        $this->getJson($path)->assertUnauthorized();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $editor = app(CmsAccount::class)->provision($admin);
        $this->actingAs($editor, 'cp')->get($path)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/cp/erepair')->assertOk()->assertSee($path, false);
        $this->getJson('/api/v1/technicians/'.$profile->id.'/photo')->assertNotFound();
    }

    public function test_public_photo_url_does_not_depend_on_the_logged_in_owner(): void
    {
        Storage::fake('local');
        [$owner, $profile] = $this->technician();
        $profile->verification_status = 'approved';
        $profile->save();
        $this->actingAs($owner, 'web')->postJson('/api/v1/technician/photo', ['photo' => UploadedFile::fake()->image('public.png')])->assertOk();
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()
            ->assertJsonPath('data.photo_url', url('/api/v1/technicians/'.$profile->id.'/photo'));
    }

    public function test_note_uses_locked_current_status_instead_of_stale_route_binding(): void
    {
        [$technician, $repair] = $this->repair();
        $stale = clone $repair;
        $repair->status = 'accepted';
        $repair->save();
        $this->actingAs($technician, 'web');
        $request = Request::create('/api/v1/repair-requests/'.$repair->id.'/notes', 'POST', ['remarks' => 'Device inspected.']);
        $request->setUserResolver(fn () => $technician);
        app(RepairController::class)->note($request, $stale, app(RepairService::class));
        $note = $repair->history()->where('remarks', 'Device inspected.')->firstOrFail();
        $this->assertSame('accepted', $note->previous_status);
        $this->assertSame('accepted', $note->new_status);
    }

    public function test_notification_failure_rolls_back_the_note(): void
    {
        [$technician, $repair] = $this->repair();
        $this->mock(Dispatcher::class, function ($mock) {
            $mock->shouldReceive('send')->andThrow(new \RuntimeException('Synthetic delivery failure'));
        });
        $count = $repair->history()->count();
        $this->actingAs($technician, 'web')->postJson('/api/v1/repair-requests/'.$repair->id.'/notes', ['remarks' => 'Atomic note test.'])->assertStatus(500);
        $this->assertSame($count, $repair->history()->count());
    }

    public function test_inactive_catalog_items_cannot_be_added_to_a_technician_profile(): void
    {
        [$owner] = $this->technician();
        $type = DeviceType::create(['name' => 'Retired device', 'slug' => 'retired', 'active' => false]);
        $spec = Specialization::create(['name' => 'Retired service', 'slug' => 'retired', 'active' => false]);
        $this->actingAs($owner, 'web')->patchJson('/api/v1/technician/profile', [
            'biography' => 'Experienced laptop repair technician providing clear estimates.',
            'years_experience' => 5, 'city' => 'Santa Cruz', 'province' => 'Laguna',
            'availability' => 'available', 'device_type_ids' => [$type->id], 'specialization_ids' => [$spec->id],
        ])->assertUnprocessable()->assertJsonValidationErrors(['device_type_ids.0', 'specialization_ids.0']);
    }

    public function test_technician_approval_requires_active_capabilities(): void
    {
        [, $profile] = $this->technician();
        $type = DeviceType::create(['name' => 'Retired device', 'slug' => 'retired', 'active' => false]);
        $spec = Specialization::create(['name' => 'Retired service', 'slug' => 'retired', 'active' => false]);
        $profile->deviceTypes()->attach($type);
        $profile->specializations()->attach($spec);
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TechnicianVerification::class)->decide($admin, $profile, 'approved', null);
    }

    public function test_only_the_cms_editor_can_update_approximate_public_coordinates(): void
    {
        [$owner, $profile] = $this->technician();
        $path = '/cp/erepair/technicians/'.$profile->id.'/location';
        $this->actingAs($owner, 'web')->postJson($path, ['latitude' => 14.28123, 'longitude' => 121.42123])->assertUnauthorized();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp')->post($path, ['latitude' => 14.28123, 'longitude' => 121.42123])->assertRedirect();
        $this->assertSame([14.28, 121.42], $profile->fresh()->publicCoordinates());
        $this->assertSame('pending', $profile->fresh()->verification_status);
        $this->assertDatabaseHas('activity_logs', ['actor_id' => $admin->id, 'action' => 'technician.location_updated', 'subject_id' => $profile->id]);
    }

    public function test_cms_coordinates_require_a_complete_pair_within_geographic_bounds(): void
    {
        [, $profile] = $this->technician();
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp');
        $path = '/cp/erepair/technicians/'.$profile->id.'/location';
        $this->postJson($path, ['latitude' => 91, 'longitude' => 181])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->postJson($path, ['latitude' => 14])->assertUnprocessable()->assertJsonValidationErrors('longitude');
        $this->assertNull($profile->fresh()->publicCoordinates());
    }

    public function test_nearest_sort_uses_the_same_public_points_as_the_map(): void
    {
        [$technician] = $this->repair();
        $first = $technician->technicianProfile;
        $first->update(['latitude' => 14.2849, 'longitude' => 121.4249]);
        [, $second] = $this->technician();
        $second->latitude = 14.2801;
        $second->longitude = 121.4201;
        $second->verification_status = 'approved';
        $second->save();
        $this->getJson('/api/v1/technicians?sort=distance&latitude=14.28&longitude=121.42')->assertOk()
            ->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.1.id', $second->id)
            ->assertJsonPath('data.0.latitude', 14.28)->assertJsonPath('data.1.latitude', 14.28)
            ->assertJsonPath('data.0.longitude', 121.42)->assertJsonPath('data.1.longitude', 121.42);
    }
}
