<?php

namespace Tests\Feature;

use App\Services\DatabaseIntegrity;
use App\Services\ProductionReadiness;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Tests\TestCase;

class ProductionReadinessTest extends TestCase
{
    private array $database;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.env' => 'production', 'app.debug' => false,
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.url' => 'https://erepair.school.edu.ph',
            'erepair.frontend_url' => 'https://erepair.school.edu.ph/',
            'cors.allowed_origins' => ['https://erepair.school.edu.ph'],
            'cors.allowed_origins_patterns' => [], 'cors.supports_credentials' => true,
            'sanctum.stateful' => ['erepair.school.edu.ph'],
            'session.driver' => 'database', 'session.secure' => true,
            'session.http_only' => true, 'session.encrypt' => true,
            'session.same_site' => 'lax', 'session.domain' => null, 'session.path' => '/',
            'mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.school.edu.ph', 'mail.from.address' => 'repairs@school.edu.ph',
            'mail.mailers.smtp.require_tls' => true,
        ]);
        $this->database = ['driver' => 'pgsql', 'host' => 'database.school.edu.ph', 'database' => 'erepair', 'username' => 'erepair', 'password' => 'PrivateDatabasePass123!', 'sslmode' => 'verify-full'];
    }

    private function flags(): array
    {
        return array_column(app(ProductionReadiness::class)->configurationChecks($this->database), 'ready', 'key');
    }

    public function test_documented_single_origin_configuration_passes(): void
    {
        $this->assertNotContains(false, $this->flags());
    }

    public function test_free_render_requires_durable_single_instance_storage_and_file_limiter(): void
    {
        config(['erepair.deployment_target' => 'render', 'hosted.enabled' => false]);
        $this->assertFalse($this->flags()['render_storage']);
        config(['hosted.enabled' => true, 'erepair.single_instance' => false, 'cache.limiter' => 'database']);
        $this->assertFalse($this->flags()['durable_files']);
        config(['erepair.single_instance' => true, 'cache.limiter' => 'file']);
        $this->assertTrue($this->flags()['durable_files']);
        $this->assertTrue($this->flags()['render_storage']);
    }

    public function test_https_mail_requires_private_api_credentials(): void
    {
        config(['mail.default' => 'brevo-http', 'mail.mailers.brevo-http.key' => null]);
        $this->assertFalse($this->flags()['mail']);
        config(['mail.mailers.brevo-http.key' => 'private-provider-credential']);
        $this->assertTrue($this->flags()['mail']);
    }

    public function test_render_queue_must_match_the_supervised_database_worker(): void
    {
        config(['erepair.deployment_target' => 'render', 'queue.default' => 'sync']);
        $this->assertFalse($this->flags()['render_queue']);
        config(['queue.default' => 'database']);
        $this->assertTrue($this->flags()['render_queue']);
    }

    public function test_local_debug_configuration_is_not_a_production_pass(): void
    {
        config(['app.env' => 'local', 'app.debug' => true]);
        $this->assertFalse($this->flags()['environment']);
    }

    public function test_placeholder_and_non_https_origins_fail(): void
    {
        foreach (['http://erepair.school.edu.ph', 'https://CHANGE_ME.example.com', 'https://localhost', 'https://127.0.0.1', 'https://user:secret@erepair.school.edu.ph', 'https://erepair.school.edu.ph/api'] as $origin) {
            config(['app.url' => $origin, 'erepair.frontend_url' => $origin]);
            $this->assertFalse($this->flags()['https_origin']);
        }
    }

    public function test_different_frontend_origin_is_rejected(): void
    {
        config(['erepair.frontend_url' => 'https://other.school.edu.ph']);
        $this->assertFalse($this->flags()['https_origin']);
    }

    public function test_wildcard_or_development_cors_and_sanctum_hosts_are_rejected(): void
    {
        config(['cors.allowed_origins' => ['*'], 'sanctum.stateful' => ['erepair.school.edu.ph', 'localhost:5173']]);
        $this->assertFalse($this->flags()['cors']);
        $this->assertFalse($this->flags()['sanctum']);
    }

    public function test_insecure_or_shared_domain_session_cookies_are_rejected(): void
    {
        config(['session.secure' => false]);
        $this->assertFalse($this->flags()['session']);
        config(['session.secure' => true, 'session.domain' => '.school.edu.ph']);
        $this->assertFalse($this->flags()['session']);
    }

    public function test_remote_database_requires_verified_tls_but_loopback_does_not(): void
    {
        $this->database['sslmode'] = 'prefer';
        $this->assertFalse($this->flags()['database_tls']);
        $this->database['host'] = '127.0.0.1';
        $this->assertTrue($this->flags()['database_tls']);
    }

    public function test_log_mailer_invalid_key_and_placeholder_database_password_are_rejected(): void
    {
        config(['mail.default' => 'log', 'app.key' => 'invalid']);
        $this->database['password'] = 'CHANGE_ME_password';
        $this->assertFalse($this->flags()['mail']);
        $this->assertFalse($this->flags()['encryption_key']);
        $this->assertFalse($this->flags()['postgresql']);
    }

    public function test_api_mail_providers_without_credentials_or_dependencies_are_not_ready(): void
    {
        foreach (['postmark', 'resend', 'mailgun'] as $provider) {
            config(['mail.default' => $provider, 'mail.mailers.'.$provider => ['transport' => $provider], 'services.'.$provider => []]);
            $this->assertFalse($this->flags()['mail'], 'An unconfigured provider must not pass: '.$provider);
        }
    }

    public function test_smtp_url_overrides_cannot_hide_placeholder_configuration(): void
    {
        config(['mail.mailers.smtp.url' => 'smtp://fixture-user:FixtureSecret123@CHANGE_ME.example.com:587?require_tls=true']);
        $this->assertFalse($this->flags()['mail']);
    }

    public function test_valid_smtp_url_is_resolved_without_disclosing_its_credentials(): void
    {
        $url = 'smtp://fixture-user:FixtureSecret123@smtp.school.edu.ph:587?require_tls=true&timeout=10';
        config(['mail.mailers.smtp.host' => 'CHANGE_ME', 'mail.mailers.smtp.url' => $url]);
        $this->assertTrue($this->flags()['mail']);
        $encoded = json_encode(app(ProductionReadiness::class)->configurationChecks($this->database));
        $this->assertStringNotContainsString($url, $encoded);
        $this->assertStringNotContainsString('FixtureSecret123', $encoded);
        // Building a transport does not connect or send email.
        $manager = app('mail.manager');
        $manager->purge('smtp');
        $transport = $manager->mailer('smtp')->getSymfonyTransport();
        $this->assertInstanceOf(EsmtpTransport::class, $transport);
        $this->assertTrue($transport->isTlsRequired());
        $this->assertSame('smtp.school.edu.ph', $transport->getStream()->getHost());
        $this->assertSame(587, $transport->getStream()->getPort());
        $this->assertSame(10.0, $transport->getStream()->getTimeout());
    }

    public function test_smtp_placeholder_credentials_and_unbounded_timeout_are_rejected(): void
    {
        config(['mail.mailers.smtp.username' => 'CHANGE_ME', 'mail.mailers.smtp.password' => 'CHANGE_ME']);
        $this->assertFalse($this->flags()['mail']);
        config(['mail.mailers.smtp.username' => null, 'mail.mailers.smtp.password' => null, 'mail.mailers.smtp.timeout' => 0]);
        $this->assertFalse($this->flags()['mail']);
    }

    public function test_remote_smtp_requires_verified_tls_and_a_valid_port(): void
    {
        config(['mail.mailers.smtp.require_tls' => false]);
        $this->assertFalse($this->flags()['mail']);
        config(['mail.mailers.smtp.require_tls' => true, 'mail.mailers.smtp.verify_peer' => false]);
        $this->assertFalse($this->flags()['mail']);
        config(['mail.mailers.smtp.verify_peer' => true, 'mail.mailers.smtp.port' => 0]);
        $this->assertFalse($this->flags()['mail']);
        config(['mail.mailers.smtp.port' => 587, 'mail.mailers.smtp.scheme' => 'https']);
        $this->assertFalse($this->flags()['mail']);
    }

    public function test_invalid_mail_url_fails_closed_without_leaking_credentials(): void
    {
        config(['mail.mailers.smtp.url' => 'smtp://fixture-user:FixtureSecret123@smtp.school.edu.ph:invalid-port']);
        $this->assertFalse($this->flags()['mail']);
        $report = json_encode(app(ProductionReadiness::class)->configurationChecks($this->database));
        $this->assertStringNotContainsString('FixtureSecret123', $report);
    }

    public function test_local_anonymous_relay_is_supported_and_implicit_tls_is_recognized(): void
    {
        config(['mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.require_tls' => false, 'mail.mailers.smtp.username' => '', 'mail.mailers.smtp.password' => '']);
        $this->assertTrue($this->flags()['mail']);
        config(['mail.mailers.smtp.host' => 'smtp.school.edu.ph', 'mail.mailers.smtp.port' => 465, 'mail.mailers.smtp.scheme' => null]);
        $this->assertTrue($this->flags()['mail']);
    }

    public function test_enabling_private_disk_serving_or_pro_features_fails(): void
    {
        config(['filesystems.disks.local.serve' => true, 'statamic.graphql.enabled' => true, 'statamic.editions.pro' => true]);
        $this->assertFalse($this->flags()['private_disk']);
        $this->assertFalse($this->flags()['core_features']);
        $this->assertFalse($this->flags()['core_edition']);
    }

    public function test_results_never_contain_configuration_secrets(): void
    {
        $encoded = json_encode(app(ProductionReadiness::class)->configurationChecks($this->database));
        $this->assertStringNotContainsString($this->database['password'], $encoded);
        $this->assertStringNotContainsString(config('app.key'), $encoded);
        $this->assertStringNotContainsString('erepair.school.edu.ph', $encoded);
    }

    public function test_configuration_only_command_emits_machine_readable_scoped_results(): void
    {
        config(['database.connections.pgsql' => [...config('database.connections.pgsql'), ...$this->database]]);
        $this->artisan('erepair:production-check', ['--skip-database' => true, '--json' => true])
            ->expectsOutputToContain('"database_checked": false')->assertSuccessful();
    }

    public function test_backup_drill_refuses_the_test_or_production_database_before_running_tools(): void
    {
        $this->artisan('erepair:backup-restore-check')
            ->expectsOutputToContain('limited to the existing loopback erepair demo database')->assertFailed();
    }

    public function test_configuration_failure_remains_safe_machine_readable_json(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new \RuntimeException('private-connection-details'));
        $this->assertSame(1, Artisan::call('erepair:production-check', ['--json' => true]));
        $output = Artisan::output();
        $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($report['passed']);
        $this->assertFalse($report['database_checked']);
        $this->assertSame('configuration', $report['checks'][0]['key']);
        $this->assertStringNotContainsString('private-connection-details', $output);
    }

    private function roleReport(object $role): array
    {
        $connection = \Mockery::mock(Connection::class);
        $connection->shouldReceive('getConfig')->andReturn($this->database);
        $connection->shouldReceive('select')->with('select 1')->andReturn([]);
        $connection->shouldReceive('getDriverName')->andReturn('pgsql');
        $connection->shouldReceive('selectOne')->with("SELECT current_setting('server_version_num') AS version")->andReturn((object) ['version' => 160015]);
        $connection->shouldReceive('selectOne')->with('select rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_roles where rolname = current_user')->andReturn($role);
        $integrity = \Mockery::mock(DatabaseIntegrity::class);
        $integrity->shouldReceive('checks')->with($connection)->andReturn([]);
        $this->app->instance(DatabaseIntegrity::class, $integrity);
        $manager = \Mockery::mock(DatabaseManager::class);
        $manager->shouldReceive('connection')->andReturn($connection);
        DB::swap($manager);
        $migrator = \Mockery::mock();
        $migrator->shouldReceive('getMigrationFiles')->andReturn([]);
        // Stop after the role check: this unit fixture must never query real users.
        $migrator->shouldReceive('getRepository')->andThrow(new \RuntimeException('Synthetic migration stop'));
        $this->app->instance('migrator', $migrator);
        Artisan::call('erepair:production-check', ['--json' => true]);

        return array_column(json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)['checks'], 'ready', 'key');
    }

    public function test_preflight_rejects_cluster_privileges_without_superuser_access(): void
    {
        $flags = ['rolsuper' => false, 'rolcreatedb' => false, 'rolcreaterole' => false, 'rolreplication' => false, 'rolbypassrls' => false];
        foreach (array_keys($flags) as $attribute) {
            $report = $this->roleReport((object) [...$flags, $attribute => true]);
            $this->assertFalse($report['database_role'], 'A privileged runtime role must fail: '.$attribute);
        }
    }

    public function test_preflight_accepts_restricted_flags_and_rejects_incomplete_role_inspection(): void
    {
        $report = $this->roleReport((object) ['rolsuper' => false, 'rolcreatedb' => false, 'rolcreaterole' => false, 'rolreplication' => false, 'rolbypassrls' => false]);
        $this->assertTrue($report['database_role']);
        $report = $this->roleReport((object) ['rolsuper' => false]);
        $this->assertFalse($report['database_role']);
    }
}
