<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function assertDisposablePostgresDatabase(): void
    {
        $connection = DB::connection();
        if (! app()->environment('testing') || $connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== 'erepair_test') {
            throw new \LogicException('Integration tests require the testing environment and disposable erepair_test PostgreSQL database.');
        }
    }
}
