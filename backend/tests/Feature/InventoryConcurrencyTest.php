<?php

namespace Tests\Feature;

use App\Models\DeviceBrand;
use App\Models\DeviceType;
use App\Models\InventoryMovement;
use App\Models\InventoryPart;
use App\Models\RepairCategory;
use App\Models\RepairRequest;
use App\Models\SubscriptionPlan;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires committed fixtures on the disposable test database.');
        }
        parent::setUp();
        $this->assertDisposablePostgresDatabase();
        $this->assertSame(0, DB::transactionLevel());
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function race(TechnicianProfile $profile, InventoryPart $part, RepairRequest $repair, array $keys): array
    {
        $connection = DB::connection();
        $env = ['RUN_POSTGRES_TESTS' => '1', 'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'erepair_test', 'DB_URL' => '',
            'DB_HOST' => $connection->getConfig('host'), 'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_USERNAME' => $connection->getConfig('username'), 'DB_PASSWORD' => $connection->getConfig('password'),
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array'];
        $barrier = storage_path('framework/testing/inventory-'.Str::uuid());
        mkdir($barrier, 0700, true);
        $processes = [];
        try {
            foreach ($keys as $i => $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/inventory-race-worker.php'), (string) $profile->user_id, (string) $part->id, (string) $repair->id, $key, $barrier, (string) $i], base_path(), $env);
                $process->setTimeout(45);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 25;
            while (! is_file($barrier.'/ready-0') || ! is_file($barrier.'/ready-1')) {
                foreach ($processes as $process) {
                    $this->assertTrue($process->isRunning(), $process->getErrorOutput());
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Inventory workers did not reach the test barrier.');
                }
                usleep(10000);
            }
            file_put_contents($barrier.'/release', 'go');
            $statuses = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $statuses[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
            }
            sort($statuses);

            return $statuses;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (['ready-0', 'ready-1', 'release'] as $name) {
                if (is_file($barrier.'/'.$name)) {
                    unlink($barrier.'/'.$name);
                }
            }
            rmdir($barrier);
        }
    }

    public function test_concurrent_usage_cannot_overspend_stock_and_duplicate_requests_deduct_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $profile = TechnicianProfile::factory()->create();
        $customer = User::factory()->create();
        $slug = 'inventory-race-'.Str::lower(Str::random(8));
        $type = DeviceType::create(['name' => 'Race laptop', 'slug' => $slug]);
        $brand = DeviceBrand::create(['name' => 'Race brand', 'slug' => $slug]);
        $category = RepairCategory::create(['name' => 'Race repair', 'slug' => $slug]);
        $repair = new RepairRequest;
        $repair->forceFill(['reference' => 'RACE-'.Str::ulid(), 'customer_id' => $customer->id, 'technician_profile_id' => $profile->id, 'device_type_id' => $type->id, 'device_brand_id' => $brand->id, 'repair_category_id' => $category->id, 'model' => 'Race fixture', 'problem_description' => 'Test only', 'status' => 'in_repair'])->save();
        $part = InventoryPart::factory()->create(['technician_id' => $profile->id, 'quantity' => 5]);
        $actors = [$admin->id, $profile->user_id, $customer->id];
        try {
            $service = app(SubscriptionService::class);
            $payment = $service->requestPlan($profile->user, (string) Str::uuid(), SubscriptionPlan::where('slug', 'premium')->firstOrFail()->price, 'premium');
            $service->decide($admin, $payment, 'approved');

            $this->assertSame(['insufficient', 'recorded'], $this->race($profile, $part, $repair, [(string) Str::uuid(), (string) Str::uuid()]));
            $this->assertSame(1, $part->fresh()->quantity);
            $this->assertSame(1, InventoryMovement::where('inventory_part_id', $part->id)->where('kind', 'usage')->count());

            app(InventoryService::class)->move($profile->user, $part->id, ['kind' => 'restock', 'quantity' => 4, 'unit_cost' => '450.00', 'idempotency_key' => (string) Str::uuid()]);
            $key = (string) Str::uuid();
            $this->assertSame(['recorded', 'replayed'], $this->race($profile, $part, $repair, [$key, $key]));
            $this->assertSame(1, $part->fresh()->quantity);
            $this->assertSame(1, InventoryMovement::where('idempotency_key', $key)->count());
        } finally {
            DB::transaction(function () use ($part, $repair, $profile, $actors, $category, $brand, $type) {
                DB::table('activity_logs')->whereIn('actor_id', $actors)->delete();
                $part->movements()->delete();
                $part->delete();
                $repair->delete();
                $profile->subscriptions()->delete();
                $profile->delete();
                User::whereIn('id', $actors)->delete();
                $category->delete();
                $brand->delete();
                $type->delete();
            });
        }
    }
}
