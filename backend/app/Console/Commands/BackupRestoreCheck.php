<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use App\Services\LocalDatabaseMaintenance;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class BackupRestoreCheck extends Command
{
    protected $signature = 'erepair:backup-restore-check {--pg-bin= : Optional PostgreSQL binary directory}';

    protected $description = 'Back up the loopback demo database/files and verify restoration into an isolated temporary database';

    public function handle(): int
    {
        $source = DB::connection();
        $configuration = $source->getConfig();
        if (app()->isProduction() || ($configuration['driver'] ?? '') !== 'pgsql'
            || ($configuration['database'] ?? '') !== 'erepair'
            || ! in_array($configuration['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
            || ! is_file(base_path('../.local/demo-password'))) {
            $this->error('This drill is limited to the existing loopback erepair demo database, outside production.');

            return self::FAILURE;
        }
        $finder = new ExecutableFinder;
        $binaries = [];
        foreach (['pg_dump', 'pg_restore'] as $tool) {
            $binary = $this->option('pg-bin')
                ? rtrim($this->option('pg-bin'), '/\\').DIRECTORY_SEPARATOR.$tool.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '')
                : $finder->find($tool);
            if (! $binary || ! is_file($binary)) {
                $this->error('Install matching PostgreSQL client tools or supply --pg-bin.');

                return self::FAILURE;
            }
            $binaries[$tool] = $binary;
        }
        $directory = base_path('../.local/backups/restore-check-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)));
        File::makeDirectory($directory, 0700, true);
        $target = 'erepair_restore_check_'.bin2hex(random_bytes(6));
        $created = false;
        $failure = false;
        $stage = 'database snapshot';
        $result = [];
        // Passwords stay in the child environment, never shell command arguments.
        $environment = ['PGPASSWORD' => (string) $configuration['password'], 'PGCONNECT_TIMEOUT' => '10', 'PGSSLMODE' => $configuration['sslmode'] ?? 'prefer'];
        if (! empty($configuration['sslrootcert'])) {
            $environment['PGSSLROOTCERT'] = $configuration['sslrootcert'];
        }
        $connectionArguments = ['--host='.$configuration['host'], '--port='.$configuration['port'], '--username='.$configuration['username'], '--no-password'];
        try {
            $source->transaction(function () use ($source, $binaries, $connectionArguments, $configuration, $directory, $environment, &$result) {
                $source->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
                $snapshot = $source->selectOne('select pg_export_snapshot() as id')->id;
                $result['tables'] = $this->fingerprints($source);
                $this->runProcess([$binaries['pg_dump'], ...$connectionArguments, '--dbname='.$configuration['database'], '--format=custom', '--no-owner', '--no-acl', '--snapshot='.$snapshot, '--file='.$directory.'/database.dump'], $environment);
            });
            $stage = 'isolated restore database creation';
            $maintenance = app(LocalDatabaseMaintenance::class)->configuration($configuration, base_path('../.local/postgres-maintenance.json'));
            config(['database.connections.backup_admin' => $maintenance]);
            config(['database.connections.backup_restore' => [...$configuration, 'url' => null, 'database' => $target]]);
            $admin = DB::connection('backup_admin');
            // The target is generated internally and can never name an existing
            // application/test database. No caller-supplied restore name exists.
            $owner = $admin->getQueryGrammar()->wrap($configuration['username']);
            $admin->statement('CREATE DATABASE "'.$target.'" OWNER '.$owner.' TEMPLATE template0');
            $created = true;
            $stage = 'database restore';
            $this->runProcess([$binaries['pg_restore'], ...$connectionArguments, '--dbname='.$target, '--exit-on-error', '--no-owner', '--no-acl', $directory.'/database.dump'], $environment);
            $stage = 'restored database comparison';
            if ($result['tables'] !== $this->fingerprints(DB::connection('backup_restore'))) {
                throw new RuntimeException('Restored data does not match its exported snapshot.');
            }
            $stage = 'editorial and upload file backup';
            $files = [];
            foreach (['content', 'resources/blueprints', 'storage/app/private', 'storage/app/public'] as $relative) {
                $this->copyVerified(base_path($relative), $directory.'/files/'.$relative, $relative, $files);
            }
            // Exercise reading/restoring each backed-up file into a new staging
            // tree; never overwrite the working application or its uploads.
            $stage = 'restored file comparison';
            $restoredFiles = [];
            $this->copyVerified($directory.'/files', $directory.'/restored-files', '', $restoredFiles);
            ksort($files);
            ksort($restoredFiles);
            if ($files !== $restoredFiles) {
                throw new RuntimeException('Restored files differ from their backup.');
            }
            $result['files'] = $files;
            $result['database_dump_sha256'] = hash_file('sha256', $directory.'/database.dump');
        } catch (Throwable) {
            // Raw database/process errors can expose usernames/connection details.
            $this->error('Backup/restore verification failed during '.$stage.'. Original application data was not replaced.');
            $failure = true;
        } finally {
            DB::disconnect('backup_restore');
            if ($created) {
                try {
                    if (! preg_match('/^erepair_restore_check_[a-f0-9]{12}$/', $target)) {
                        throw new RuntimeException('Unexpected restore target.');
                    }
                    DB::connection('backup_admin')->statement('DROP DATABASE "'.$target.'"');
                } catch (Throwable) {
                    $this->error('Temporary restore database cleanup failed; inspect the isolated restore database before rerunning.');
                    $failure = true;
                }
            }
            DB::disconnect('backup_admin');
        }
        if ($failure) {
            $this->line('Incomplete local backup retained for inspection: '.$directory);

            return self::FAILURE;
        }
        $result['created_at'] = gmdate(DATE_ATOM);
        $result['scope'] = 'Loopback demo snapshot and file restore drill; production backups/encryption-key recovery require separate acceptance.';
        File::put($directory.'/verification.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL);
        $this->info('Database snapshot restored with matching fingerprints for '.count($result['tables']).' tables.');
        $this->info('Editorial/private/public files restored with '.count($result['files']).' matching SHA-256 hashes.');
        $this->info('Only the newly created temporary restore database was removed.');
        $this->line('Sensitive demo backup retained outside public/release directories: '.$directory);

        return self::SUCCESS;
    }

    private function runProcess(array $command, array $environment): void
    {
        $process = new Process($command, base_path(), $environment);
        $process->setTimeout(120);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('PostgreSQL client operation failed.');
        }
    }

    private function fingerprints($connection): array
    {
        $result = [];
        foreach ($connection->select("select tablename from pg_tables where schemaname = 'public' order by tablename") as $table) {
            $identifier = $connection->getQueryGrammar()->wrapTable('public.'.$table->tablename);
            $row = $connection->selectOne("select count(*) as rows, md5(coalesce(string_agg(row_to_json(t)::text, chr(10) order by row_to_json(t)::text), '')) as fingerprint from $identifier t");
            $result[$table->tablename] = ['rows' => (int) $row->rows, 'fingerprint' => $row->fingerprint];
        }

        return $result;
    }

    private function copyVerified(string $source, string $destination, string $relative, array &$manifest): void
    {
        if (is_link($source) || ! is_dir($source)) {
            throw new RuntimeException('Expected a regular backup source directory.');
        }
        File::makeDirectory($destination, 0700, true, true);
        foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $entry) {
            if ($entry->isLink()) {
                throw new RuntimeException('Linked files are not followed during backup.');
            }
            $name = ltrim($relative.'/'.$entry->getFilename(), '/');
            $output = $destination.'/'.$entry->getFilename();
            if ($entry->isDir()) {
                $this->copyVerified($entry->getPathname(), $output, $name, $manifest);
            } elseif ($entry->isFile()) {
                $hash = hash_file('sha256', $entry->getPathname());
                if (! copy($entry->getPathname(), $output) || hash_file('sha256', $output) !== $hash) {
                    throw new RuntimeException('File changed or could not be backed up.');
                }
                $manifest[$name] = $hash;
            } else {
                throw new RuntimeException('Unsupported backup file.');
            }
        }
    }
}
