<?php

namespace Tests\Feature;

use App\Services\LocalDatabaseMaintenance;
use RuntimeException;
use Tests\TestCase;

class LocalDatabaseMaintenanceTest extends TestCase
{
    private string $file;

    private array $application = ['driver' => 'pgsql', 'host' => '127.0.0.1', 'port' => 55432, 'database' => 'erepair', 'username' => 'runtime', 'password' => 'runtime-test-password', 'url' => 'must-not-override-explicit-credentials'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'erepair-maintenance-');
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
        parent::tearDown();
    }

    private function credentials(array $changes = []): void
    {
        file_put_contents($this->file, json_encode([...['format' => 1, 'host' => '127.0.0.1', 'port' => 55432, 'username' => 'maintenance', 'password' => 'private-maintenance-test-password'], ...$changes], JSON_THROW_ON_ERROR));
    }

    public function test_maintenance_credentials_do_not_replace_runtime_configuration(): void
    {
        $this->credentials();
        $configuration = app(LocalDatabaseMaintenance::class)->configuration($this->application, $this->file);
        $this->assertSame('maintenance', $configuration['username']);
        $this->assertSame('postgres', $configuration['database']);
        $this->assertNull($configuration['url']);
        $this->assertSame('runtime', $this->application['username']);
    }

    public function test_missing_file_preserves_the_existing_local_drill_connection(): void
    {
        unlink($this->file);
        $configuration = app(LocalDatabaseMaintenance::class)->configuration($this->application, $this->file);
        $this->assertSame('runtime', $configuration['username']);
        $this->assertSame('postgres', $configuration['database']);
    }

    public function test_remote_or_other_database_configurations_are_rejected_before_file_access(): void
    {
        $this->credentials();
        foreach ([['host' => 'remote.school.edu.ph'], ['database' => 'erepair_test'], ['driver' => 'mysql']] as $change) {
            try {
                app(LocalDatabaseMaintenance::class)->configuration([...$this->application, ...$change], $this->file);
                $this->fail('An unsupported maintenance source was accepted.');
            } catch (RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_credentials_for_another_server_are_rejected(): void
    {
        $this->credentials(['port' => 5432]);
        $this->expectException(RuntimeException::class);
        app(LocalDatabaseMaintenance::class)->configuration($this->application, $this->file);
    }

    public function test_empty_maintenance_password_is_not_silently_accepted(): void
    {
        $this->credentials(['password' => '']);
        $this->expectException(RuntimeException::class);
        app(LocalDatabaseMaintenance::class)->configuration($this->application, $this->file);
    }
}
