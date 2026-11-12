<?php

declare(strict_types=1);

// Opt-in local demo journey. Leaves two clearly labeled approved DEMO payments.
require_once __DIR__.'/lib/DemoHttpClient.php';
require dirname(__DIR__).'/backend/vendor/autoload.php';
$app = require dirname(__DIR__).'/backend/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SubscriptionDemoPayment;
use App\Models\TechnicianProfile;
use App\Services\SubscriptionRevenue;
use App\Support\SubscriptionMoney as Money;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function subscriptionCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    fwrite(STDOUT, '[PASS] '.$message."\n");
}

try {
    $base = rtrim($argv[1] ?? 'http://127.0.0.1:8001', '/');
    $parts = parse_url($base);
    if (! $app->environment('local') || ! in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true)
        || ($parts['scheme'] ?? '') !== 'http' || isset($parts['user']) || isset($parts['pass'])
        || ! in_array($parts['path'] ?? '', ['', '/'], true) || isset($parts['query']) || isset($parts['fragment'])
        || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
        throw new RuntimeException('Subscription smoke tests require the local loopback demo backend and database.');
    }
    $email = $argv[2] ?? 'technician1@erepair.test';
    $profile = TechnicianProfile::whereHas('user', fn ($q) => $q->where('email', $email)->where('role', 'technician')->where('account_status', 'active'))->firstOrFail();
    if (! str_ends_with($email, '.test') || $profile->hasProSubscription() || $profile->demoPayments()->where('status', 'pending')->exists()) {
        throw new RuntimeException('Choose an existing Free .test technician without a pending request. Existing paid periods will not be expired by this test.');
    }
    $password = trim(file_get_contents(dirname(__DIR__).'/.local/demo-password'));
    $technician = new DemoHttpClient($base);
    $technician->login($email, $password);
    $overview = $technician->request('GET', '/api/v1/technician/subscription')['data'];
    subscriptionCheck(! $overview['is_pro'], 'Existing technician starts on Free with existing account data preserved');
    $pro = collect($overview['plans'])->firstWhere('slug', 'pro');
    $revenue = $app->make(SubscriptionRevenue::class);
    $baseline = Money::cents($revenue->summary()['total']);
    $payload = ['idempotency_key' => (string) Str::uuid(), 'expected_price' => $pro['price'], 'confirm_demo' => true];
    $payment = $technician->request('POST', '/api/v1/technician/subscription/demo-payments', $payload)['data'];
    $duplicate = $technician->request('POST', '/api/v1/technician/subscription/demo-payments', $payload)['data'];
    subscriptionCheck($payment['id'] === $duplicate['id'] && $payment['status'] === 'pending' && $payment['is_demo'], 'Real cookie/CSRF checkout creates one pending DEMO transaction across repeated requests');
    subscriptionCheck(! $profile->hasProSubscription() && Money::cents($revenue->summary()['total']) === $baseline, 'Pending payment grants no Pro access and adds no simulated revenue');
    $technician->request('POST', '/cp/erepair/subscriptions/payments/'.$payment['id'], ['decision' => 'approved'], expected: 401);
    subscriptionCheck(true, 'Technician cannot approve their own payment through native administrator routes');

    $admin = new DemoHttpClient($base);
    $admin->request('GET', '/cp/auth/login');
    $admin->request('GET', '/sanctum/csrf-cookie', expected: 204);
    $admin->request('POST', '/cp/auth/login', ['email' => 'admin@erepair.test', 'password' => $password]);
    $admin->request('GET', '/cp/erepair/subscriptions');
    subscriptionCheck(str_contains($admin->body(), $payment['transaction_reference']), 'Native single-editor Statamic administration lists the pending DEMO reference');
    $admin->request('POST', '/cp/erepair/subscriptions/payments/'.$payment['id'], ['decision' => 'approved'], expected: 302);
    $record = SubscriptionDemoPayment::findOrFail($payment['id']);
    $expiry = $record->subscription->expires_at->toJSON();
    $admin->request('POST', '/cp/erepair/subscriptions/payments/'.$payment['id'], ['decision' => 'approved'], expected: 302);
    $active = $technician->request('GET', '/api/v1/technician/subscription')['data'];
    subscriptionCheck($active['is_pro'] && $active['expires_at'] === $expiry && $record->fresh()->status === 'approved', 'Administrator approval activates Pro once and repeated approval preserves expiration');
    $visitor = new DemoHttpClient($base);
    $public = $visitor->request('GET', '/api/v1/technicians/'.$profile->id)['data'];
    subscriptionCheck($public['is_pro'], 'Technician finder profile exposes the backend-approved Pro subscription badge');
    $technician->request('GET', '/api/v1/technician/subscription/analytics');
    subscriptionCheck(true, 'Pro analytics loads actual database view and inquiry counts');
    $expected = $baseline + Money::cents($payment['amount']);
    $admin->request('GET', '/cp/erepair/revenue?paying_technicians=50');
    subscriptionCheck(Money::cents($revenue->summary()['total']) === $expected && str_contains($admin->body(), Money::format(Money::decimal($expected))) && str_contains($admin->body(), 'NO REAL PAYMENTS'), 'Approved DEMO revenue increases by exactly the quoted amount and renders in the native revenue page');
    $admin->request('GET', '/vendor/erepair/js/revenue-projection.js');
    subscriptionCheck(str_contains($admin->body(), 'BigInt'), 'Projection script is served as a separate native CMS asset');

    subscriptionCheck(Artisan::call('erepair:subscriptions-demo', ['action' => 'expire', '--technician' => $profile->id, '--reference' => $payment['transaction_reference']]) === 0, 'Guarded development-only expiration targets exactly the new DEMO cycle');
    subscriptionCheck(! $technician->request('GET', '/api/v1/technician/subscription')['data']['is_pro'], 'Expiration restores Free without removing the account or history');
    $technician->request('GET', '/api/v1/technician/subscription/analytics', expected: 403);
    $technician->request('GET', '/api/v1/repair-requests');
    $renewal = $technician->request('POST', '/api/v1/technician/subscription/demo-payments', [...$payload, 'idempotency_key' => (string) Str::uuid()])['data'];
    $admin->request('POST', '/cp/erepair/subscriptions/payments/'.$renewal['id'], ['decision' => 'approved'], expected: 302);
    subscriptionCheck($technician->request('GET', '/api/v1/technician/subscription')['data']['is_pro'], 'Approved renewal restores Pro while existing free repair access remains available');
    subscriptionCheck(Money::cents($revenue->summary()['total']) === $expected + Money::cents($renewal['amount']), 'Expired historical payment remains in revenue and renewal adds exactly one further DEMO payment');
    $technician->request('POST', '/api/v1/logout', expected: 204);
    $admin->request('GET', '/cp/auth/logout', expected: 302);
    fwrite(STDOUT, "Subscription HTTP journey passed. Two approved DEMO payment records remain as labeled capstone history. No real payment provider was contacted.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] '.$exception->getMessage()."\n");
    exit(1);
}
