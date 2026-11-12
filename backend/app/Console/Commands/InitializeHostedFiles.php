<?php

namespace App\Console\Commands;

use App\Services\HostedFiles;
use Illuminate\Console\Command;

class InitializeHostedFiles extends Command
{
    protected $signature = 'erepair:initialize-hosted-files';

    protected $description = 'Initialize or restore the opt-in PostgreSQL file mirror without overwriting durable content';

    public function handle(HostedFiles $files): int
    {
        if (! config('hosted.enabled')) {
            $this->error('Durable files are disabled. No files or database records were changed.');

            return self::FAILURE;
        }
        if (! config('erepair.single_instance') || config('cache.limiter') !== 'file') {
            $this->error('Durable file mirroring requires EREPAIR_SINGLE_INSTANCE=true and CACHE_LIMITER=file.');

            return self::FAILURE;
        }
        $created = $files->initialize();
        $this->info($created ? 'Durable file storage initialized.' : 'Existing durable files restored.');

        return self::SUCCESS;
    }
}
