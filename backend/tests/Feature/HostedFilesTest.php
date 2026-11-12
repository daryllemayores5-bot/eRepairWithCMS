<?php

namespace Tests\Feature;

use App\Http\Middleware\DurableFiles;
use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\RepairAttachment;
use App\Models\RepairCategory;
use App\Models\User;
use App\Services\HostedFiles;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\HostedFileFixtureJob;
use Tests\TestCase;

class HostedFilesTest extends TestCase
{
    use RefreshDatabase;

    private string $sandbox;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertDisposablePostgresDatabase();
    }

    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires disposable PostgreSQL test database.');
        }
        parent::setUp();
        $this->sandbox = storage_path('framework/testing/hosted-'.Str::uuid());
        config(['hosted.enabled' => true, 'hosted.marker' => $this->sandbox.'/revision', 'hosted.roots' => ['content' => $this->sandbox.'/content', 'private' => $this->sandbox.'/private']]);
        File::ensureDirectoryExists($this->sandbox.'/content');
        File::ensureDirectoryExists($this->sandbox.'/private');
    }

    protected function tearDown(): void
    {
        // Only this test's owned UUID directory, never real content or uploads.
        if (isset($this->sandbox)) {
            File::deleteDirectory($this->sandbox);
        }
        parent::tearDown();
    }

    public function test_content_and_binary_uploads_survive_a_lost_filesystem(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', "title: Published\n");
        file_put_contents($this->sandbox.'/private/photo.bin', "\0\xff\x01private");
        $files = app(HostedFiles::class);
        $this->assertTrue($files->initialize());
        unlink($this->sandbox.'/content/page.yaml');
        unlink($this->sandbox.'/private/photo.bin');
        $files->invalidate();
        $files->locked(fn () => $files->restore());
        $this->assertSame("title: Published\n", file_get_contents($this->sandbox.'/content/page.yaml'));
        $this->assertSame("\0\xff\x01private", file_get_contents($this->sandbox.'/private/photo.bin'));
        file_put_contents($this->sandbox.'/content/page.yaml', 'release seed');
        $this->assertFalse($files->initialize());
        $this->assertSame("title: Published\n", file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    public function test_a_deleted_root_is_recovered_even_with_a_current_marker(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        $files = app(HostedFiles::class);
        $files->initialize();
        unlink($this->sandbox.'/content/page.yaml');
        rmdir($this->sandbox.'/content');
        $files->locked(fn () => $files->restore());
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    public function test_deletions_are_durable_and_do_not_resurrect_after_restart(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        $files = app(HostedFiles::class);
        $files->initialize();
        unlink($this->sandbox.'/content/page.yaml');
        $files->locked(fn () => $files->capture());
        file_put_contents($this->sandbox.'/content/page.yaml', 'old release');
        $files->locked(fn () => $files->restore(true));
        $this->assertFileDoesNotExist($this->sandbox.'/content/page.yaml');
    }

    public function test_corrupt_storage_is_rejected_before_local_files_are_changed(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        $files = app(HostedFiles::class);
        $files->initialize();
        DB::table('hosted_files')->update(['payload' => base64_encode('corrupt')]);
        try {
            $files->locked(fn () => $files->restore(true));
            $this->fail('Corrupt payload accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('integrity', $error->getMessage());
        }
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    public function test_path_traversal_is_rejected_before_local_files_are_changed(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        $files = app(HostedFiles::class);
        $files->initialize();
        DB::table('hosted_files')->update(['path' => '../escape', 'path_hash' => hash('sha256', '../escape')]);
        try {
            $files->locked(fn () => $files->restore(true));
            $this->fail('Traversal accepted.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('path', $error->getMessage());
        }
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    public function test_quota_failure_rolls_back_database_writes_and_restores_committed_files(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        $files = app(HostedFiles::class);
        $files->initialize();
        config(['hosted.max_file_bytes' => 8]);
        try {
            app(DurableFiles::class)->handle(Request::create('/cp/collections', 'POST'), function () {
                DB::table('hosted_file_states')->update(['revision' => 99]);
                file_put_contents($this->sandbox.'/content/page.yaml', str_repeat('x', 20));

                return response('ok');
            });
            $this->fail('Quota failure accepted.');
        } catch (HttpException $error) {
            $this->assertSame(503, $error->getStatusCode());
        }
        $this->assertSame(1, (int) DB::table('hosted_file_states')->value('revision'));
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    public function test_disabled_local_mode_does_not_need_storage_tables(): void
    {
        config(['hosted.enabled' => false]);
        $response = app(DurableFiles::class)->handle(Request::create('/api/v1/health'), fn () => response('normal'));
        $this->assertSame('normal', $response->getContent());
        $this->assertSame(0, DB::table('hosted_file_states')->count());
    }

    public function test_nonmultipart_api_deletion_is_committed_to_durable_storage(): void
    {
        file_put_contents($this->sandbox.'/private/photo.bin', 'private photo');
        $files = app(HostedFiles::class);
        $files->initialize();
        $response = app(DurableFiles::class)->handle(Request::create('/api/v1/profile/photo', 'DELETE'), function () {
            unlink($this->sandbox.'/private/photo.bin');

            return response('', 204);
        });
        $this->assertSame(204, $response->getStatusCode());
        $files->locked(fn () => $files->restore(true));
        $this->assertFileDoesNotExist($this->sandbox.'/private/photo.bin');
        $this->assertDatabaseCount('hosted_files', 0);
    }

    public function test_failed_http_mutations_restore_files_without_losing_security_database_updates(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        app(HostedFiles::class)->initialize();
        $response = app(DurableFiles::class)->handle(Request::create('/cp/collections', 'POST'), function () {
            DB::table('hosted_file_states')->update(['updated_at' => '2026-11-01 00:00:00']);
            file_put_contents($this->sandbox.'/content/page.yaml', 'rejected edit');

            return response('validation failed', 422);
        });
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
        $this->assertStringStartsWith('2026-11-01', DB::table('hosted_file_states')->value('updated_at'));
    }

    public function test_file_count_failure_can_recover_the_previous_committed_snapshot(): void
    {
        file_put_contents($this->sandbox.'/content/page.yaml', 'saved');
        app(HostedFiles::class)->initialize();
        config(['hosted.max_files' => 1]);
        try {
            app(DurableFiles::class)->handle(Request::create('/cp/collections', 'POST'), function () {
                file_put_contents($this->sandbox.'/content/extra.yaml', 'over quota');

                return response('ok');
            });
            $this->fail('File count quota was ignored.');
        } catch (HttpException $error) {
            $this->assertSame(503, $error->getStatusCode());
        }
        $this->assertFileDoesNotExist($this->sandbox.'/content/extra.yaml');
        $this->assertSame('saved', file_get_contents($this->sandbox.'/content/page.yaml'));
    }

    private function repairFixture(): array
    {
        $this->seed(CatalogSeeder::class);
        config(['filesystems.disks.local.root' => $this->sandbox.'/private']);
        Storage::forgetDisk('local');
        $customer = User::factory()->create();
        $technician = User::factory()->create(['role' => 'technician']);
        $type = DeviceType::firstOrFail();
        $category = RepairCategory::firstOrFail();
        $profile = $technician->technicianProfile()->create(['city' => 'Santa Cruz', 'province' => 'Laguna', 'availability' => 'available']);
        $profile->forceFill(['verification_status' => 'approved'])->save();
        $profile->deviceTypes()->attach($type);
        $profile->specializations()->attach($category->specialization_id);
        app(HostedFiles::class)->initialize();

        return [$customer, [
            'technician_profile_id' => $profile->id, 'device_type_id' => $type->id,
            'device_brand_id' => DeviceBrand::firstOrFail()->id,
            'repair_category_id' => $category->id, 'model' => 'Test laptop',
            'problem_description' => 'The screen is damaged and needs professional inspection.',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'ends_at' => now()->addDays(2)->addHour()->toIso8601String(),
            'service_location' => 'Santa Cruz municipal center',
            'photos' => [UploadedFile::fake()->image('device.png', 32, 32)],
        ]];
    }

    public function test_real_repair_upload_recovers_through_http_and_keeps_download_authorization(): void
    {
        [$customer, $data] = $this->repairFixture();
        $this->actingAs($customer, 'web')->post('/api/v1/repair-requests', $data, ['Accept' => 'application/json'])->assertCreated();
        $attachment = RepairAttachment::sole();
        $path = Storage::disk('local')->path($attachment->path);
        $expected = hash_file('sha256', $path);
        unlink($path);
        app(HostedFiles::class)->invalidate();
        $download = $this->get('/api/v1/attachments/'.$attachment->id)->assertOk();
        $this->assertSame($expected, hash('sha256', $download->streamedContent()));
        $this->assertStringContainsString('no-store', $download->headers->get('Cache-Control'));
        // Feature requests reuse the container; clear Sanctum's cached guard
        // before simulating a second browser with a different session.
        $this->app['auth']->forgetGuards();
        $stranger = User::factory()->create();
        $this->actingAs($stranger, 'web')->getJson('/api/v1/user')->assertOk()->assertJsonPath('data.id', $stranger->id);
        $this->getJson('/api/v1/attachments/'.$attachment->id)->assertForbidden();
        $this->assertDatabaseCount('repair_requests', 1);
    }

    public function test_real_repair_and_notifications_roll_back_when_upload_persistence_fails(): void
    {
        [$customer, $data] = $this->repairFixture();
        config(['hosted.max_file_bytes' => 8]);
        $this->actingAs($customer, 'web')->post('/api/v1/repair-requests', $data, ['Accept' => 'application/json'])->assertStatus(503);
        foreach (['repair_requests', 'repair_attachments', 'appointments', 'repair_status_histories', 'notifications', 'activity_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_hosted_transaction_has_a_bounded_lock_wait(): void
    {
        $value = app(HostedFiles::class)->locked(fn () => DB::selectOne("select current_setting('lock_timeout') as timeout")->timeout);
        $this->assertSame('5s', $value);
    }

    public function test_dispatched_jobs_persist_files_and_database_changes_together(): void
    {
        $files = app(HostedFiles::class);
        $files->initialize();
        $job = new class($this->sandbox.'/content/job.yaml')
        {
            public function __construct(public string $path) {}

            public function handle(): string
            {
                file_put_contents($this->path, "title: Scheduled content\n");
                User::factory()->create(['email' => 'queued-content@erepair.test']);

                return 'persisted';
            }
        };
        $this->assertSame('persisted', Bus::dispatchSync($job));
        unlink($job->path);
        $files->invalidate();
        $files->locked(fn () => $files->restore(true));
        $this->assertSame("title: Scheduled content\n", file_get_contents($job->path));
        $this->assertDatabaseHas('users', ['email' => 'queued-content@erepair.test']);
    }

    public function test_failed_dispatched_jobs_restore_files_and_roll_back_database_changes(): void
    {
        $path = $this->sandbox.'/content/job.yaml';
        file_put_contents($path, "title: Original\n");
        app(HostedFiles::class)->initialize();
        $job = new class($path)
        {
            public function __construct(public string $path) {}

            public function handle(): void
            {
                file_put_contents($this->path, "title: Uncommitted\n");
                User::factory()->create(['email' => 'failed-job@erepair.test']);
                throw new \RuntimeException('Failed job fixture');
            }
        };
        try {
            Bus::dispatchSync($job);
            $this->fail('The job fixture must fail.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Failed job fixture', $error->getMessage());
        }
        $this->assertSame("title: Original\n", file_get_contents($path));
        $this->assertDatabaseMissing('users', ['email' => 'failed-job@erepair.test']);
    }

    public function test_database_queue_worker_executes_the_job_through_durable_persistence(): void
    {
        config(['queue.default' => 'database']);
        $path = $this->sandbox.'/content/queued.yaml';
        $files = app(HostedFiles::class);
        $files->initialize();
        Bus::dispatch(new HostedFileFixtureJob($path));
        $this->assertDatabaseCount('jobs', 1);
        $this->assertFileDoesNotExist($path);
        $this->artisan('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1, '--sleep' => 0, '--quiet' => true])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 0);
        $this->assertFileExists($path);
        unlink($path);
        $files->invalidate();
        $files->locked(fn () => $files->restore(true));
        $this->assertSame("title: Queued persistence fixture\n", file_get_contents($path));
    }
}
