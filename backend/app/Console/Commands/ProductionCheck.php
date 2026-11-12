<?php

namespace App\Console\Commands;

use App\Models\CmsUser;
use App\Models\RepairRequest;
use App\Models\User;
use App\Services\DatabaseIntegrity;
use App\Services\ProductionReadiness;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Statamic\Facades\User as CmsRepository;
use Throwable;

class ProductionCheck extends Command
{
    protected $signature = 'erepair:production-check {--skip-database : Inspect configuration only, without querying database records} {--json : Output named checks without configuration values or secrets}';

    protected $description = 'Read-only production configuration, migration, identity and demo-data checks';

    public function handle(ProductionReadiness $readiness): int
    {
        try {
            // Resolve DB_URL using Laravel, but never print its credentials.
            $connection = DB::connection();
            $rows = $readiness->configurationChecks($connection->getConfig());
        } catch (Throwable) {
            if ($this->option('json')) {
                $this->line(json_encode(['passed' => false, 'database_checked' => false, 'checks' => [['key' => 'configuration', 'ready' => false, 'detail' => 'Configuration could not be inspected. No connection values were printed.']]], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->error('Configuration could not be inspected. No connection values were printed.');
            }

            return self::FAILURE;
        }
        $add = function (string $key, bool $ready, string $detail) use (&$rows): void {
            $rows[] = compact('key', 'ready', 'detail');
        };
        $private = realpath(storage_path('app/private'));
        $public = realpath(public_path());
        $outsidePublic = $private !== false && $public !== false && ! str_starts_with(strtolower($private.DIRECTORY_SEPARATOR), strtolower($public.DIRECTORY_SEPARATOR));
        $add('storage', $outsidePublic && is_writable($private) && is_writable(storage_path('app/public')) && is_writable(storage_path('framework/views')) && is_writable(base_path('bootstrap/cache')) && is_writable(base_path('content')) && is_writable(resource_path('blueprints')), 'Provide persistent writable storage/content/blueprints and keep repair files outside the web root.');
        $add('php_runtime', version_compare(PHP_VERSION, '8.4', '>=') && extension_loaded('pdo_pgsql') && extension_loaded('gd'), 'Use supported PHP 8.4+ with PostgreSQL PDO and GD.');
        if (! $this->option('skip-database')) {
            try {
                $connection->select('select 1');
                $add('database_connection', $connection->getDriverName() === 'pgsql', 'Connect successfully to the actual PostgreSQL application database.');
                $version = (int) $connection->selectOne("SELECT current_setting('server_version_num') AS version")->version;
                $add('database_version', $version >= 160000, 'Use PostgreSQL 16 or newer for deployment and apply current provider security patches. The local PostgreSQL 14.5 demo runtime is not a production target.');
                $rows = array_merge($rows, app(DatabaseIntegrity::class)->checks($connection));
                $role = $connection->selectOne('select rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_roles where rolname = current_user');
                $restrictedRole = collect(['rolsuper', 'rolcreatedb', 'rolcreaterole', 'rolreplication', 'rolbypassrls'])
                    ->every(fn ($attribute) => $role && isset($role->{$attribute}) && $role->{$attribute} === false);
                $add('database_role', $restrictedRole, 'Run without PostgreSQL superuser, database/role creation, replication or RLS-bypass flags.');
                $migrator = app('migrator');
                $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
                $add('migrations', $pending === [], 'Apply all reviewed application migrations before serving traffic.');
                if (config('hosted.enabled')) {
                    $add('durable_files_initialized', DB::table('hosted_file_states')->where('id', 1)->exists(), 'Initialize durable CMS/upload storage before serving requests.');
                }
                $cms = CmsUser::with('operator')->first();
                $add('cms_editor', CmsRepository::count() === 1 && CmsUser::count() === 1 && $cms?->super && $cms?->operator?->role === 'admin' && $cms?->operator?->super && $cms?->operator?->account_status === 'active', 'Provision exactly one active linked CMS administrator.');
                $hasDemo = User::where('email', 'like', '%@erepair.test')->exists() || RepairRequest::where('reference', 'like', 'DEMO-%')->exists();
                $add('production_data', ! $hasDemo, 'Use a fresh production database; do not deploy demo accounts or repair records.');
            } catch (Throwable) {
                $add('database_inspection', false, 'Database/schema inspection failed; resolve connection and migration prerequisites.');
            }
        }
        $passed = ! in_array(false, array_column($rows, 'ready'), true);
        if ($this->option('json')) {
            $this->line(json_encode(['passed' => $passed, 'database_checked' => ! $this->option('skip-database'), 'checks' => $rows, 'scope' => 'Configuration checks only; browser, HTTPS/mail delivery, backups and dependency review require separate acceptance.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Check', 'Status', 'Requirement'], array_map(fn ($row) => [$row['key'], $row['ready'] ? 'PASS' : 'FAIL', $row['detail']], $rows));
            $this->line('This does not certify browser/mobile behavior, HTTPS/mail delivery, backups or dependency-audit acceptance.');
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
