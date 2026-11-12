<?php

namespace App\Console\Commands;

use App\Services\DatabaseIntegrity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class DatabaseCheck extends Command
{
    protected $signature = 'erepair:database-check {--json : Output named checks without credentials or records}';

    protected $description = 'Read-only PostgreSQL constraint and foreign-key index checks';

    public function handle(DatabaseIntegrity $integrity): int
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql') {
                throw new \LogicException('PostgreSQL is required.');
            }
            $rows = $connection->transaction(function () use ($connection, $integrity): array {
                if ($connection->transactionLevel() === 1) {
                    $connection->statement('SET TRANSACTION READ ONLY');
                }

                return $integrity->checks($connection);
            });
        } catch (Throwable) {
            $rows = [['key' => 'database_inspection', 'ready' => false, 'detail' => 'PostgreSQL inspection failed. Check connectivity and reviewed migrations; no credentials were printed.']];
        }
        $passed = ! in_array(false, array_column($rows, 'ready'), true);
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => $passed, 'checks' => $rows], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Detail'], array_map(fn ($row) => [$row['key'], $row['ready'] ? 'PASS' : 'FAIL', $row['detail']], $rows));
            $this->line('Schema checks only. Production server patches, TLS, privileges and backup recovery require separate acceptance.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
