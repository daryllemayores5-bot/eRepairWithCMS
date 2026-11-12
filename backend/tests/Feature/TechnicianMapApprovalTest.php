<?php

namespace Tests\Feature;

use App\Models\DeviceType;
use App\Models\Specialization;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\CmsAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TechnicianMapApprovalTest extends TestCase
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
        Http::preventStrayRequests();
        config(['services.geocoding.enabled' => false, 'sanctum.stateful' => ['127.0.0.1:5173']]);
        $this->withHeader('Origin', 'http://127.0.0.1:5173');
    }

    private function profile(): TechnicianProfile
    {
        $user = User::factory()->create(['role' => 'technician']);
        $profile = $user->technicianProfile()->create(['city' => 'Santa Cruz', 'province' => 'Laguna', 'biography' => 'Experienced device repair technician.', 'availability' => 'available']);
        $profile->deviceTypes()->attach(DeviceType::create(['name' => 'Laptop', 'slug' => 'laptop']));
        $profile->specializations()->attach(Specialization::create(['name' => 'Battery', 'slug' => 'battery']));

        return $profile;
    }

    private function editor(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $this->actingAs(app(CmsAccount::class)->provision($admin), 'cp');
    }

    public function test_approval_automatically_publishes_city_coordinates_to_the_finder_and_profile(): void
    {
        $profile = $this->profile();
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
        $this->editor();
        $this->post('/cp/erepair/technicians/'.$profile->id.'/verify', ['decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('technician_profiles', ['id' => $profile->id, 'verification_status' => 'approved', 'latitude' => 14.28, 'longitude' => 121.42]);
        $this->getJson('/api/v1/technicians')->assertOk()->assertJsonPath('data.0.id', $profile->id)->assertJsonPath('data.0.latitude', 14.28)->assertJsonPath('data.0.longitude', 121.42);
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertOk()->assertJsonPath('data.latitude', 14.28);
    }

    public function test_approval_preserves_the_administrators_existing_reference_point(): void
    {
        $profile = $this->profile();
        $profile->update(['latitude' => 14.29, 'longitude' => 121.43]);
        $this->editor();
        $this->post('/cp/erepair/technicians/'.$profile->id.'/verify', ['decision' => 'approved'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([14.29, 121.43], $profile->fresh()->publicCoordinates());
    }

    public function test_unknown_locations_do_not_silently_approve_an_unmapped_technician(): void
    {
        $profile = $this->profile();
        $profile->update(['city' => 'Unrecognized town']);
        $this->editor();
        $this->postJson('/cp/erepair/technicians/'.$profile->id.'/verify', ['decision' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('decision');
        $this->assertSame('pending', $profile->fresh()->verification_status);
        $this->assertNull($profile->fresh()->publicCoordinates());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'technician.approved', 'subject_id' => $profile->id]);
    }

    public function test_rejection_never_publishes_or_geocodes_a_profile(): void
    {
        $profile = $this->profile();
        $this->editor();
        $this->post('/cp/erepair/technicians/'.$profile->id.'/verify', ['decision' => 'rejected'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($profile->fresh()->publicCoordinates());
        $this->getJson('/api/v1/technicians/'.$profile->id)->assertNotFound();
    }

    private function updateValues(TechnicianProfile $profile, string $city): array
    {
        return ['city' => $city, 'province' => 'Laguna', 'biography' => 'Experienced device repair technician.', 'years_experience' => 4, 'availability' => 'available',
            'device_type_ids' => $profile->deviceTypes->modelKeys(), 'specialization_ids' => $profile->specializations->modelKeys()];
    }

    public function test_approved_technician_moving_cities_automatically_moves_the_marker(): void
    {
        $profile = $this->profile();
        $profile->verification_status = 'approved';
        $profile->fill(['latitude' => 14.28, 'longitude' => 121.42])->save();
        $this->actingAs($profile->user, 'web')->patchJson('/api/v1/technician/profile', $this->updateValues($profile, 'Pagsanjan'))
            ->assertOk()->assertJsonPath('data.latitude', 14.27)->assertJsonPath('data.longitude', 121.46);
        $this->assertDatabaseHas('technician_profiles', ['id' => $profile->id, 'city' => 'Pagsanjan', 'verification_status' => 'approved']);
    }

    public function test_unresolved_city_changes_preserve_the_previous_location(): void
    {
        $profile = $this->profile();
        $profile->verification_status = 'approved';
        $profile->fill(['latitude' => 14.28, 'longitude' => 121.42])->save();
        $this->actingAs($profile->user, 'web')->patchJson('/api/v1/technician/profile', $this->updateValues($profile, 'Unrecognized town'))
            ->assertUnprocessable()->assertJsonValidationErrors('city');
        $this->assertSame('Santa Cruz', $profile->fresh()->city);
        $this->assertSame([14.28, 121.42], $profile->fresh()->publicCoordinates());
    }

    public function test_backfill_only_changes_missing_approved_points_and_is_repeatable(): void
    {
        $profile = $this->profile();
        $pending = $profile->replicate();
        $pending->user_id = User::factory()->create(['role' => 'technician'])->id;
        $pending->save();
        $profile->verification_status = 'approved';
        $profile->save();
        $this->artisan('erepair:technician-locations')->expectsOutput('Updated 1 approved technician location(s); 0 require administrator review.')->assertSuccessful();
        $this->assertSame([14.28, 121.42], $profile->fresh()->publicCoordinates());
        $this->assertNull($pending->fresh()->publicCoordinates());
        $profile->update(['latitude' => 14.29, 'longitude' => 121.43]);
        $this->artisan('erepair:technician-locations')->expectsOutput('Updated 0 approved technician location(s); 0 require administrator review.')->assertSuccessful();
        $this->assertSame([14.29, 121.43], $profile->fresh()->publicCoordinates());
        $this->assertDatabaseCount('activity_logs', 1);
    }
}
