<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class DatabaseSchema extends Command
{
    protected $signature = 'erepair:database-schema {--write : Write the schema-only dictionary to docs/database-data-dictionary.md}';

    protected $description = 'Export PostgreSQL column, constraint and index metadata without application records';

    public function handle(): int
    {
        try {
            $connection = DB::connection();
            if ($connection->getDriverName() !== 'pgsql') {
                throw new \LogicException('PostgreSQL is required.');
            }
            $document = $connection->transaction(function () use ($connection): string {
                if ($connection->transactionLevel() === 1) {
                    $connection->statement('SET TRANSACTION READ ONLY');
                }
                $tables = $connection->select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename");
                $lines = ['# Database data dictionary', '', 'Schema metadata only; no application records, credentials or password hashes are exported.',
                    '', 'Generated from the applied PostgreSQL public schema on '.now('UTC')->format('Y-m-d H:i:s').' UTC.',
                    count($tables).' tables. Regenerate with `php artisan erepair:database-schema --write`.',
                    'Use migrations for installation; this document is not a database restore script.', ''];
                foreach ($tables as $table) {
                    $columns = $connection->select(<<<'SQL'
                        SELECT a.attname AS name, format_type(a.atttypid, a.atttypmod) AS type, a.attnotnull AS required
                        FROM pg_attribute a JOIN pg_class c ON c.oid = a.attrelid JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE n.nspname = 'public' AND c.relname = ? AND a.attnum > 0 AND NOT a.attisdropped
                        ORDER BY a.attnum
                        SQL, [$table->tablename]);
                    $constraints = $connection->select(<<<'SQL'
                        SELECT k.conname AS name, pg_get_constraintdef(k.oid) AS definition
                        FROM pg_constraint k JOIN pg_class c ON c.oid = k.conrelid JOIN pg_namespace n ON n.oid = c.relnamespace
                        WHERE n.nspname = 'public' AND c.relname = ? ORDER BY k.conname
                        SQL, [$table->tablename]);
                    $indexes = $connection->select("SELECT indexname AS name, indexdef AS definition FROM pg_indexes WHERE schemaname = 'public' AND tablename = ? ORDER BY indexname", [$table->tablename]);
                    array_push($lines, '## '.$table->tablename, '', '| Column | PostgreSQL type | Nullable |', '| --- | --- | --- |');
                    foreach ($columns as $column) {
                        $lines[] = '| `'.$column->name.'` | `'.$column->type.'` | '.($column->required ? 'No' : 'Yes').' |';
                    }
                    array_push($lines, '', '### Constraints', '');
                    foreach ($constraints as $constraint) {
                        $lines[] = '- `'.$constraint->name.'`: `'.$constraint->definition.'`';
                    }
                    array_push($lines, '', '### Indexes', '');
                    foreach ($indexes as $index) {
                        $lines[] = '- `'.$index->name.'`: `'.$index->definition.'`';
                    }
                    $lines[] = '';
                }

                return implode("\n", $lines)."\n";
            });
            if ($this->option('write')) {
                $path = base_path('../docs/database-data-dictionary.md');
                File::ensureDirectoryExists(dirname($path));
                File::put($path, $document);
                $this->info('Schema-only dictionary written to docs/database-data-dictionary.md.');
            } else {
                $this->output->write($document);
            }
        } catch (Throwable) {
            $this->error('Schema export failed. Check PostgreSQL connectivity and documentation-directory access; no connection values were printed.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
