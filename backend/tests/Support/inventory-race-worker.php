<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (getenv('RUN_POSTGRES_TESTS') !== '1' || ! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'erepair_test') {
        throw new LogicException('Inventory race requires the disposable test database.');
    }
    [$script, $userId, $partId, $repairId, $key, $barrier, $worker] = $argv;
    $root = realpath(storage_path('framework/testing'));
    $directory = realpath($barrier);
    if (! $root || ! $directory || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR) || ! in_array($worker, ['0', '1'], true)) {
        throw new LogicException('Invalid inventory test barrier.');
    }
    file_put_contents($directory.'/ready-'.$worker, 'ready');
    $deadline = microtime(true) + 30;
    while (! is_file($directory.'/release')) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Inventory test barrier timed out.');
        }
        usleep(10000);
    }
    $result = app(InventoryService::class)->move(User::findOrFail($userId), (int) $partId, [
        'kind' => 'usage', 'quantity' => 4, 'repair_request_id' => (int) $repairId, 'idempotency_key' => $key,
    ]);
    echo json_encode(['status' => $result['replayed'] ? 'replayed' : 'recorded'], JSON_THROW_ON_ERROR);
} catch (ValidationException) {
    echo json_encode(['status' => 'insufficient'], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    fwrite(STDERR, 'Inventory worker failed: '.get_class($error));
    exit(1);
}
