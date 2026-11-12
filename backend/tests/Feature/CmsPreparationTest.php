<?php

namespace Tests\Feature;

use Statamic\Statamic;
use Tests\TestCase;

class CmsPreparationTest extends TestCase
{
    public function test_preparation_matches_the_native_cms_installation_state(): void
    {
        if (class_exists(Statamic::class)) {
            $this->artisan('erepair:prepare-cms-auth')
                ->expectsOutputToContain('The native Statamic WebAuthn migration is already present.')
                ->assertSuccessful();

            return;
        }

        $this->artisan('erepair:prepare-cms-auth')
            ->expectsOutputToContain('Install Statamic before preparing its native authentication migration.')
            ->assertFailed();
    }
}
