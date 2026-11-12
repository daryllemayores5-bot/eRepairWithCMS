<?php

namespace Tests\Feature;

use App\Http\Requests\RegisterRequest;
use App\Models\DeviceType;
use App\Models\RepairRequest;
use App\Models\Specialization;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Policies\RepairRequestPolicy;
use App\Services\TechnicianFinder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class DomainSecurityTest extends TestCase
{
    public function test_integration_guard_rejects_a_non_disposable_database_before_schema_reset(): void
    {
        $connection = \Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('production_database');
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->expectException(\LogicException::class);
        $this->assertDisposablePostgresDatabase();
    }

    public function test_customer_cannot_change_technician_availability(): void
    {
        $user = User::factory()->make();
        $user->id = 1;
        $this->actingAs($user)->patchJson('/api/v1/technician/availability', ['availability' => 'available'])->assertForbidden();
    }

    public function test_invalid_availability_is_rejected_before_a_profile_is_updated(): void
    {
        $user = User::factory()->make(['role' => 'technician']);
        $user->id = 1;
        $this->actingAs($user)->patchJson('/api/v1/technician/availability', ['availability' => 'approved'])->assertUnprocessable()->assertJsonValidationErrors('availability');
    }

    public function test_customer_cannot_use_technician_dashboard_endpoint(): void
    {
        $user = User::factory()->make();
        $user->id = 1;
        $this->actingAs($user)->getJson('/api/v1/technician/dashboard')->assertForbidden();
    }

    public function test_suspended_account_cannot_access_current_user(): void
    {
        $user = User::factory()->make(['account_status' => 'suspended']);
        $user->id = 1;
        $this->actingAs($user)->getJson('/api/v1/user')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_policy_rejects_unrelated_and_suspended_users(): void
    {
        $repair = new RepairRequest;
        $repair->customer_id = 1;
        $profile = (new TechnicianProfile)->forceFill(['user_id' => 2]);
        $repair->setRelation('technician', $profile);
        $owner = new User(['name' => 'Owner']);
        $owner->id = 1;
        $owner->role = 'customer';
        $owner->account_status = 'active';
        $stranger = new User;
        $stranger->id = 3;
        $stranger->role = 'customer';
        $stranger->account_status = 'active';
        $policy = new RepairRequestPolicy;
        $this->assertTrue($policy->view($owner, $repair));
        $this->assertFalse($policy->view($stranger, $repair));
        $owner->account_status = 'suspended';
        $this->assertFalse($policy->view($owner, $repair));
    }

    public function test_public_registration_rules_reject_admin_and_super_privileges(): void
    {
        $rules = (new RegisterRequest)->rules();
        $validator = Validator::make(['role' => 'admin', 'super' => true], ['role' => $rules['role'], 'super' => $rules['super']]);
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('role'));
        $this->assertTrue($validator->errors()->has('super'));
    }

    public function test_matching_explains_each_criterion_and_does_not_claim_an_unmatched_location(): void
    {
        $p = (new TechnicianProfile)->forceFill(['city' => 'Santa Cruz', 'availability' => 'available']);
        $p->average_rating = 4.5;
        $device = new DeviceType;
        $device->id = 1;
        $specialty = new Specialization;
        $specialty->id = 2;
        $p->setRelation('deviceTypes', collect([$device]));
        $p->setRelation('specializations', collect([$specialty]));
        $p->setRelation('serviceAreas', collect());
        $score = (new TechnicianFinder)->score($p, ['device_type' => 1, 'specialization' => 2, 'city' => 'Santa Cruz']);
        $this->assertSame(99.5, $score['score']);
        $this->assertCount(5, $score['reasons']);
        $other = (new TechnicianFinder)->score($p, ['city' => 'Manila']);
        $this->assertArrayNotHasKey('Selected service area', $other['reasons']);
    }
}
