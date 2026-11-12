<?php

namespace App\Console\Commands;

use Composer\Semver\Semver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Statamic\Statamic;
use Throwable;
use UnexpectedValueException;

class ProjectDoctor extends Command
{
    protected $signature = 'erepair:doctor {--skip-database : Inspect tools without connecting to PostgreSQL} {--skip-build : Check prerequisites before creating a fresh frontend build}';

    protected $description = 'Report required local tools and project installation state without exposing credentials';

    public function handle(): int
    {
        $frontend = json_decode(File::get(base_path('../frontend/package.json')), true, flags: JSON_THROW_ON_ERROR);
        $nodeRange = $frontend['engines']['node'] ?? null;
        $rows = [];
        $failed = false;
        $record = function (string $component, bool $ready, string $detail) use (&$rows, &$failed): void {
            $rows[] = [$component, $ready ? 'Ready' : 'Missing / unavailable', $detail];
            $failed = $failed || ! $ready;
        };

        $record('PHP', version_compare(PHP_VERSION, '8.4.0', '>='), PHP_VERSION);
        $record('PostgreSQL PDO', extension_loaded('pdo_pgsql'), 'Required by the shared Laravel database connection');
        $record('GD', extension_loaded('gd'), 'Required for CMS image processing');
        foreach (['Node' => ['node', '--version'], 'npm' => ['npm.cmd', '--version']] as $label => $command) {
            if (PHP_OS_FAMILY !== 'Windows' && $label === 'npm') {
                $command = ['npm', '--version'];
            }
            try {
                $process = Process::path(base_path('..'))->timeout(10)->run($command);
                $version = trim($process->output());
                $ready = $process->successful();
                $detail = $ready ? $version : 'Executable could not be run';
                if ($label === 'Node' && $ready) {
                    $ready = is_string($nodeRange) && $this->supportsNodeVersion($version, $nodeRange);
                    $detail .= '; required: '.(is_string($nodeRange) && $nodeRange !== '' ? $nodeRange : 'declare engines.node in frontend/package.json');
                }
                $record($label, $ready, $detail);
            } catch (Throwable) {
                $record($label, false, 'Executable could not be run');
            }
        }

        $record('Statamic', class_exists(Statamic::class), 'Must be installed in this Laravel application');
        $missing = array_filter(array_keys(array_merge($frontend['dependencies'] ?? [], $frontend['devDependencies'] ?? [])),
            fn (string $name) => ! is_file(base_path('../frontend/node_modules/'.$name.'/package.json')));
        $record('Frontend packages', count($missing) === 0, count($missing) === 0 ? 'Declared dependency package files are present' : count($missing).' declared packages are missing');
        if (! $this->option('skip-build')) {
            $record('Frontend build artifact', is_file(base_path('../frontend/dist/index.html')), 'Artifact presence only; rebuild and test after source changes');
        }
        if (! $this->option('skip-database')) {
            $connected = false;
            try {
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::select('select 1');
                    $connected = true;
                }
            } catch (Throwable) {
                // Report only availability, never connection strings or secrets.
            }
            $record('PostgreSQL server', $connected, $connected ? 'Connected to the configured project database' : 'No working project database connection');
        }

        $this->table(['Component', 'Status', 'Detail'], $rows);
        if ($failed) {
            $this->line('Installation is incomplete. See README.md and docs/downloads.md.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function supportsNodeVersion(string $version, string $range): bool
    {
        // Accept stable CLI output only; npm engine ranges do not implicitly
        // admit prereleases, even when their numeric version is above a minimum.
        if ($range === '' || ! preg_match('/^v?(\d+\.\d+\.\d+)$/D', $version, $matches)) {
            return false;
        }

        try {
            return Semver::satisfies($matches[1], $range);
        } catch (UnexpectedValueException) {
            return false;
        }
    }
}
