<?php

declare(strict_types=1);

use App\Models\SubscriptionDemoPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (getenv('RUN_POSTGRES_TESTS') !== '1' || ! $app->environment('testing')
        || DB::connection()->getDriverName() !== 'pgsql' || DB::connection()->getDatabaseName() !== 'erepair_test') {
        throw new LogicException('Concurrent workers require the dedicated testing database.');
    }
    [$script, $operation, $userId, $paymentId, $key, $barrier, $worker] = $argv;
    $allowedRoot = realpath(storage_path('framework/testing'));
    $resolvedBarrier = realpath($barrier);
    if (! $allowedRoot || ! $resolvedBarrier || ! str_starts_with($resolvedBarrier, $allowedRoot.DIRECTORY_SEPARATOR)
        || ! in_array($worker, ['0', '1'], true)) {
        throw new LogicException('Invalid testing barrier.');
    }
    file_put_contents($resolvedBarrier.DIRECTORY_SEPARATOR.'ready-'.$worker, 'ready');
    $deadline = microtime(true) + 30;
    while (! is_file($resolvedBarrier.DIRECTORY_SEPARATOR.'release')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Testing barrier timed out.');
        }
        usleep(10000);
    }
    $user = User::findOrFail($userId);
    $service = app(SubscriptionService::class);
    if ($operation === 'request') {
        $plan = $argv[7] ?? 'pro';
        $payment = $service->requestPlan($user, $key, SubscriptionPlan::where('slug', $plan)->firstOrFail()->price, $plan);
    } elseif ($operation === 'approve') {
        $payment = $service->decide($user, SubscriptionDemoPayment::findOrFail($paymentId), 'approved');
    } else {
        throw new LogicException('Unknown concurrent test operation.');
    }
    echo json_encode(['status' => $payment->status, 'id' => $payment->id], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'conflict'], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    // Do not print environment variables, connection settings or SQL bindings.
    fwrite(STDERR, 'Concurrent worker failed: '.get_class($error));
    exit(1);
}
