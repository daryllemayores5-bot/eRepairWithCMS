<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$stage = 'preconditions';
try {
    $source = Illuminate\Support\Facades\DB::connection();
    $configuration = $source->getConfig();
    if ($app->isProduction() || ($configuration['driver'] ?? '') !== 'pgsql' || ($configuration['database'] ?? '') !== 'erepair'
        || ! in_array($configuration['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)
        || ! is_file(base_path('../.local/demo-password')) || ! is_file(base_path('.env'))
        || ! empty(config('database.connections.pgsql.url'))) {
        throw new RuntimeException('This installer is restricted to the existing loopback demo with a normal local environment file.');
    }
    $current = $source->selectOne('select rolsuper from pg_roles where rolname = current_user');
    if (! $current->rolsuper) {
        echo "Application database role is already non-superuser; no credentials or ownership changed.\n";
        exit(0);
    }
    $directory = base_path('../.local');
    $maintenanceFile = $directory.'/postgres-maintenance.json';
    $rollbackFile = $directory.'/database-env-before-hardening';
    if (is_file($maintenanceFile) || is_file($rollbackFile)) {
        throw new RuntimeException('Prior maintenance state needs inspection before another credential change.');
    }
    $role = 'erepair_runtime_'.bin2hex(random_bytes(4));
    $password = bin2hex(random_bytes(32));
    $environment = file_get_contents(base_path('.env'));
    $updated = $environment;
    foreach (['DB_USERNAME' => $role, 'DB_PASSWORD' => $password] as $name => $value) {
        if (preg_match('/^'.$name.'=/m', $updated)) {
            $updated = preg_replace_callback('/^'.$name.'=[^\r\n]*/m', fn () => $name.'='.$value, $updated);
        } else {
            $updated .= PHP_EOL.$name.'='.$value.PHP_EOL;
        }
    }
    $maintenance = ['format' => 1, 'host' => $configuration['host'], 'port' => $configuration['port'], 'username' => $configuration['username'], 'password' => $configuration['password'], 'runtime_role' => $role];
    foreach ([$maintenanceFile => json_encode($maintenance, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), $rollbackFile => $environment] as $file => $contents) {
        $handle = fopen($file, 'x');
        if (! $handle || fwrite($handle, $contents) !== strlen($contents)) {
            throw new RuntimeException('Could not preserve private maintenance/rollback configuration.');
        }
        fclose($handle);
        chmod($file, 0600);
    }
    $counts = [];
    foreach (['users', 'cms_users', 'technician_profiles', 'repair_requests'] as $table) {
        $counts[$table] = $source->table($table)->count();
    }
    $administrator = $source->table('users')->where('role', 'admin')->sole();
    $stage = 'restricted role and application ownership';
    $source->transaction(function () use ($source, $role, $password) {
        $identifier = $source->getQueryGrammar()->wrap($role);
        $quotedPassword = $source->getPdo()->quote($password);
        $source->statement('CREATE ROLE '.$identifier.' LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS PASSWORD '.$quotedPassword);
        $source->statement('ALTER DATABASE "erepair" OWNER TO '.$identifier);
        $source->statement('ALTER SCHEMA "public" OWNER TO '.$identifier);
        $source->statement('REVOKE CREATE ON SCHEMA "public" FROM PUBLIC');
        $source->statement('REVOKE CONNECT ON DATABASE "erepair" FROM PUBLIC');
        // Only this application's public-schema relations are transferred. Do not
        // use REASSIGN OWNED, which can affect unrelated cluster/database objects.
        $objects = $source->select("select c.relname, c.relkind from pg_class c join pg_namespace n on n.oid=c.relnamespace where n.nspname='public' and c.relkind in ('r','p','S','v','m','f') order by case when c.relkind in ('r','p') then 0 else 1 end, c.relname");
        $types = ['r' => 'TABLE', 'p' => 'TABLE', 'S' => 'SEQUENCE', 'v' => 'VIEW', 'm' => 'MATERIALIZED VIEW', 'f' => 'FOREIGN TABLE'];
        foreach ($objects as $object) {
            $table = $source->getQueryGrammar()->wrapTable('public.'.$object->relname);
            $source->statement('ALTER '.$types[$object->relkind].' '.$table.' OWNER TO '.$identifier);
        }
    });
    $stage = 'runtime verification';
    config(['database.connections.runtime_probe' => [...$configuration, 'url' => null, 'username' => $role, 'password' => $password]]);
    $probe = Illuminate\Support\Facades\DB::connection('runtime_probe');
    $privileges = $probe->selectOne('select rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_roles where rolname=current_user');
    foreach ((array) $privileges as $privilege) {
        if ($privilege) {
            throw new RuntimeException('Runtime role has unexpected cluster privileges.');
        }
    }
    foreach ($counts as $table => $count) {
        if ($probe->table($table)->count() !== $count) {
            throw new RuntimeException('Application record counts changed during verification.');
        }
    }
    if ($probe->table('users')->where('id', $administrator->id)->value('password') !== $administrator->password) {
        throw new RuntimeException('Administrator preservation check failed.');
    }
    $stage = 'local application environment update';
    if (file_put_contents(base_path('.env'), $updated, LOCK_EX) !== strlen($updated)) {
        throw new RuntimeException('Application environment could not be written.');
    }
    Illuminate\Support\Facades\Artisan::call('config:clear');
    echo "Local eRepair runtime now uses a separate restricted PostgreSQL login.\n";
    echo "Verified: no superuser, database creation, role creation, replication or RLS bypass privileges.\n";
    echo "Existing accounts, technician profiles, repairs and administrator password were preserved.\n";
    echo "Privileged CLI maintenance and rollback credentials remain only in ignored .local files.\n";
} catch (Throwable) {
    // Never surface CREATE ROLE SQL, connection strings or generated passwords.
    fwrite(STDERR, 'Local database hardening stopped during '.$stage.'. Private rollback/maintenance state is retained for inspection; no application records were deleted.'.PHP_EOL);
    exit(1);
}
