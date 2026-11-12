<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Statamic\Statamic;

class PrepareCmsAuthentication extends Command
{
    protected $signature = 'erepair:prepare-cms-auth';

    protected $description = 'Prepare the installed Statamic version\'s native passkey migration without duplicating shared user tables';

    public function handle(): int
    {
        if (! class_exists(Statamic::class)) {
            $this->error('Install Statamic before preparing its native authentication migration.');

            return self::FAILURE;
        }

        // Statamic's auth command also generates user/pivot migrations that this
        // application already owns. Generate into ignored staging, copying only
        // its actual WebAuthn migration into the application's migration set.
        $existing = File::glob(database_path('migrations/*_statamic_webauthn_table.php'));
        if (count($existing) > 0) {
            $this->info('The native Statamic WebAuthn migration is already present.');

            return self::SUCCESS;
        }

        $staging = base_path('../.local/cms-auth-staging/'.Str::uuid());
        File::ensureDirectoryExists($staging);
        $result = $this->call('statamic:auth:migration', ['--path' => $staging, '--no-interaction' => true]);
        if ($result !== self::SUCCESS) {
            return self::FAILURE;
        }

        $generated = File::glob($staging.'/*_statamic_webauthn_table.php');
        if (count($generated) !== 1) {
            $this->error('The installed CMS did not generate the expected WebAuthn migration. No shared tables were changed.');

            return self::FAILURE;
        }

        $target = database_path('migrations/2026_10_30_000004_statamic_webauthn_table.php');
        if (! File::copy($generated[0], $target)) {
            $this->error('Could not copy the native WebAuthn migration.');

            return self::FAILURE;
        }

        $this->info('Native WebAuthn migration prepared. Run php artisan migrate.');

        return self::SUCCESS;
    }
}
