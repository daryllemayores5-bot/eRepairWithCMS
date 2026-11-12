<?php

namespace App\Services;

use RuntimeException;

/** Privileged local CLI credentials are separate from the web application's .env. */
class LocalDatabaseMaintenance
{
    public function configuration(array $application, string $file): array
    {
        if (($application['driver'] ?? '') !== 'pgsql' || ($application['database'] ?? '') !== 'erepair'
            || ! in_array($application['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new RuntimeException('Maintenance credentials are limited to the loopback demo database.');
        }
        $result = [...$application, 'url' => null, 'database' => 'postgres'];
        if (! is_file($file)) {
            return $result;
        }
        $credentials = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
        if (($credentials['format'] ?? null) !== 1 || ($credentials['host'] ?? null) !== $application['host']
            || (string) ($credentials['port'] ?? '') !== (string) $application['port']
            || ! is_string($credentials['username'] ?? null) || $credentials['username'] === ''
            || ! is_string($credentials['password'] ?? null) || $credentials['password'] === '') {
            throw new RuntimeException('Local maintenance configuration is invalid or belongs to another server.');
        }

        return [...$result, 'username' => $credentials['username'], 'password' => $credentials['password']];
    }
}
