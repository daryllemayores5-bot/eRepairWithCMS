<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_health_checks_postgres_and_prevents_response_caching(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('pgsql');
        DB::shouldReceive('connection')->once()->andReturn($connection);
        DB::shouldReceive('select')->once()->with('select 1')->andReturn([(object) ['value' => 1]]);

        $this->getJson('/api/v1/health')->assertOk()
            ->assertJsonPath('data.database', 'connected')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_failed_database_connection_returns_safe_service_unavailable_response(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new RuntimeException('sensitive database details'));

        $response = $this->getJson('/api/v1/health')->assertServiceUnavailable();
        $response->assertExactJson(['data' => [
            'application' => 'eRepair', 'status' => 'degraded', 'database' => 'unavailable',
        ]]);
        $response->assertDontSee('sensitive database details');
    }

    public function test_an_alternative_database_is_not_reported_as_postgres_success(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        DB::shouldReceive('connection')->once()->andReturn($connection);

        $this->getJson('/api/v1/health')->assertServiceUnavailable();
    }

    public function test_guest_cannot_access_current_user(): void
    {
        $this->getJson('/api/v1/user')->assertUnauthorized()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_api_guests_receive_json_unauthorized_even_when_requesting_html(): void
    {
        foreach (['user', 'repair-requests', 'appointments'] as $endpoint) {
            $this->get('/api/v1/'.$endpoint, ['Accept' => 'text/html'])->assertUnauthorized()
                ->assertHeader('Content-Type', 'application/json')
                ->assertHeader('Cache-Control', 'no-store, private');
        }
    }

    public function test_current_user_resource_hides_authentication_secrets_and_cp_privileges(): void
    {
        $user = User::factory()->make(['role' => 'customer']);
        $user->id = 7;
        $user->super = false;

        $this->actingAs($user)->getJson('/api/v1/user')->assertOk()
            ->assertExactJson(['data' => [
                'id' => 7, 'name' => $user->name, 'email' => $user->email, 'role' => 'customer',
            ]])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_csrf_endpoint_issues_cookie_without_caching(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent()
            ->assertCookie('XSRF-TOKEN')
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_local_origin_can_use_credentialed_requests(): void
    {
        $this->withHeaders([
            'Origin' => 'http://127.0.0.1:5173',
            'Access-Control-Request-Method' => 'GET',
        ])->options('/api/v1/user')->assertNoContent()
            ->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5173')
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }

    public function test_business_defaults_use_philippine_currency_and_time_zone(): void
    {
        $this->assertSame('PHP', config('erepair.currency'));
        $this->assertSame('Asia/Manila', config('erepair.business_timezone'));
        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame('pgsql', config('database.default'));
    }
}
