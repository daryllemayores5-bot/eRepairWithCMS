<?php

namespace Tests\Feature;

use App\Models\SubscriptionDemoPayment;
use App\Models\TechnicianProfile;
use App\Models\User;
use App\Support\SubscriptionMoney;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Real independent PostgreSQL sessions; fixture rows must be committed. */
class SubscriptionConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_POSTGRES_TESTS') !== '1') {
            $this->markTestSkipped('Requires dedicated PostgreSQL integration database.');
        }
        parent::setUp();
        $this->assertDisposablePostgresDatabase();
        $this->assertSame(0, DB::transactionLevel(), 'Concurrent fixtures cannot be hidden inside a parent transaction.');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function race(string $operation, int $userId, int $paymentId, array $keys, array $plans = []): array
    {
        $connection = DB::connection();
        $environment = [
            'RUN_POSTGRES_TESTS' => '1', 'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'erepair_test', 'DB_URL' => '',
            'DB_HOST' => $connection->getConfig('host'), 'DB_PORT' => (string) $connection->getConfig('port'),
            'DB_USERNAME' => $connection->getConfig('username'), 'DB_PASSWORD' => $connection->getConfig('password'),
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array',
        ];
        $barrier = storage_path('framework/testing/subscription-race-'.Str::uuid());
        mkdir($barrier, 0700, true);
        $processes = [];
        try {
            foreach ($keys as $index => $key) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/subscription-race-worker.php'), $operation, (string) $userId, (string) $paymentId, $key, $barrier, (string) $index, $plans[$index] ?? 'pro'], base_path(), $environment);
                $process->setTimeout(45);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 25;
            while (! is_file($barrier.'/ready-0') || ! is_file($barrier.'/ready-1')) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Concurrent worker stopped before the barrier: '.$process->getErrorOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Concurrent workers did not reach the barrier in time.');
                }
                usleep(10000);
            }
            file_put_contents($barrier.'/release', 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (['ready-0', 'ready-1', 'release'] as $file) {
                if (is_file($barrier.'/'.$file)) {
                    unlink($barrier.'/'.$file);
                }
            }
            rmdir($barrier);
        }
    }

    public function test_competing_paid_plans_and_duplicate_approvals_cannot_create_conflicting_periods(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $profile = TechnicianProfile::factory()->approved()->create();
        $userIds = [$admin->id, $profile->user_id];
        try {
            $results = $this->race('request', $profile->user_id, 0, [(string) Str::uuid(), (string) Str::uuid()], ['pro', 'premium']);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame(['conflict', 'pending'], $statuses);
            $first = $profile->demoPayments()->sole();
            $this->race('approve', $admin->id, $first->id, ['', '']);
            $end = $first->subscription->expires_at;
            $next = $first->subscription->plan->slug === 'pro' ? 'premium' : 'pro';
            $switches = $this->race('request', $profile->user_id, 0, [(string) Str::uuid(), (string) Str::uuid()], [$next, $next]);
            $statuses = array_column($switches, 'status');
            sort($statuses);
            $this->assertSame(['conflict', 'pending'], $statuses);
            $payment = $profile->demoPayments()->where('status', 'pending')->sole();
            $this->race('approve', $admin->id, $payment->id, ['', '']);
            $this->assertSame(1, $profile->subscriptions()->where('status', 'active')->count());
            $this->assertSame(1, $profile->subscriptions()->where('status', 'scheduled')->count());
            $this->assertTrue($payment->subscription->starts_at->equalTo($end));
            $this->assertTrue($payment->subscription->expires_at->equalTo($end->addDays(30)));
            $this->assertSame('598.00', SubscriptionMoney::decimal(SubscriptionMoney::cents((string) $profile->demoPayments()->revenue()->sum('amount'))));
        } finally {
            DB::transaction(function () use ($profile, $userIds) {
                DB::table('activity_logs')->whereIn('actor_id', $userIds)->delete();
                $profile->subscriptions()->delete();
                $profile->delete();
                User::whereIn('id', $userIds)->delete();
            });
        }
    }

    public function test_simultaneous_checkouts_approvals_and_renewals_grant_each_period_only_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'super' => true]);
        $profile = TechnicianProfile::factory()->approved()->create();
        $userIds = [$admin->id, $profile->user_id];
        try {
            $key = (string) Str::uuid();
            $requested = $this->race('request', $profile->user_id, 0, [$key, $key]);
            $this->assertSame($requested[0]['id'], $requested[1]['id']);
            $this->assertSame(1, $profile->demoPayments()->count());
            $payment = $profile->demoPayments()->sole();
            $approved = $this->race('approve', $admin->id, $payment->id, ['', '']);
            $this->assertSame(['approved', 'approved'], array_column($approved, 'status'));
            $firstPeriod = $payment->subscription;
            $firstEnd = $firstPeriod->expires_at;
            $this->assertSame(2592000, (int) $firstPeriod->starts_at->diffInSeconds($firstEnd));

            $renewalRequests = $this->race('request', $profile->user_id, 0, [(string) Str::uuid(), (string) Str::uuid()]);
            $statuses = array_column($renewalRequests, 'status');
            sort($statuses);
            $this->assertSame(['conflict', 'pending'], $statuses);
            $renewal = $profile->demoPayments()->where('status', 'pending')->sole();
            $this->race('approve', $admin->id, $renewal->id, ['', '']);
            $this->assertTrue($renewal->subscription->expires_at->equalTo($firstEnd->addDays(30)));
            $this->assertSame(1, $profile->subscriptions()->where('status', 'active')->count());
            $this->assertSame(0, $profile->subscriptions()->where('status', 'pending')->count());
            $this->assertSame(2, $profile->demoPayments()->where('status', 'approved')->count());
            $this->assertSame('398.00', SubscriptionMoney::decimal(SubscriptionMoney::cents((string) $profile->demoPayments()->revenue()->sum('amount'))));
        } finally {
            // Only these newly created test fixture records are removed. The
            // guard above prevents this test from ever using the working DB.
            DB::transaction(function () use ($profile, $userIds) {
                DB::table('activity_logs')->whereIn('actor_id', $userIds)->delete();
                $profile->subscriptions()->delete();
                $profile->delete();
                User::whereIn('id', $userIds)->delete();
            });
        }
        $this->assertSame(0, SubscriptionDemoPayment::where('technician_id', $profile->id)->count());
    }
}
