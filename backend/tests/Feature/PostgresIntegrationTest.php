<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PostgresIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        // Opt in only with a dedicated disposable erepair_test database.
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_POSTGRES_TESTS=1 with a dedicated PostgreSQL test database.');
        }

        parent::setUp();
    }

    public function test_shared_identity_schema_persists_without_public_admin_privileges(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $user = User::factory()->create();
        $user->refresh();
        $this->assertSame('customer', $user->role);
        $this->assertFalse($user->super);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $user->email]);
        $this->getJson('/api/v1/health')->assertOk();
    }
}
