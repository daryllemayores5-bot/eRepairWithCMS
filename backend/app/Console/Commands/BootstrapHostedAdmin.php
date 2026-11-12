<?php

namespace App\Console\Commands;

use App\Models\CmsUser;
use App\Models\User;
use App\Services\CmsAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BootstrapHostedAdmin extends Command
{
    protected $signature = 'erepair:bootstrap-hosted-admin';

    protected $description = 'Provision the first administrator from private host environment variables; never reset an existing account.';

    public function handle(): int
    {
        $existing = User::where('role', 'admin')->first();
        if ($existing) {
            app(CmsAccount::class)->provision($existing);
            $this->info('Existing administrator retained. Password unchanged.');

            return self::SUCCESS;
        }
        $values = [
            'name' => getenv('EREPAIR_ADMIN_NAME') ?: null,
            'email' => getenv('EREPAIR_ADMIN_EMAIL') ?: null,
            'password' => getenv('EREPAIR_ADMIN_PASSWORD') ?: null,
        ];
        $valid = Validator::make($values, ['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'password' => 'required|string|min:12|max:128']);
        if ($valid->fails() || CmsUser::exists()) {
            $this->error('Configure a new administrator name, unused email and password of at least twelve characters in the private host environment.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($values) {
            // Concurrent starts serialize against the singleton CMS identity.
            DB::select('select pg_advisory_xact_lock(?)', [1279438172]);
            if (User::where('role', 'admin')->exists()) {
                throw new \RuntimeException('Administrator was already provisioned; restart safely.');
            }
            $admin = new User;
            $admin->forceFill($values + ['role' => 'admin', 'super' => true, 'account_status' => 'active', 'email_verified_at' => now()])->save();
            app(CmsAccount::class)->provision($admin);
        });
        $this->info('Single administrator provisioned. Remove bootstrap password from host settings.');

        return self::SUCCESS;
    }
}
