<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\CmsUser;
use App\Services\CmsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdministrator extends Command
{
    protected $signature = 'erepair:create-admin';

    protected $description = 'Create the single application administrator and independent Statamic Core login';

    public function handle(): int
    {
        if (User::where('role', 'admin')->exists() || CmsUser::exists()) {
            $this->error('An administrator already exists. Core permits only one CMS editor.');

            return self::FAILURE;
        }
        $attributes = [
            'name' => $this->ask('Administrator name'),
            'email' => strtolower(trim((string) $this->ask('Administrator email'))),
            'password' => $this->secret('Password (at least 12 characters)'),
        ];
        $validator = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        DB::transaction(function () use ($attributes) {
            $user = new User($attributes);
            $user->role = 'admin';
            $user->super = true;
            $user->email_verified_at = now();
            $user->save();
            app(CmsAccount::class)->provision($user);
        });
        $this->info('Administrator created with a separate Statamic Core login using the same initial credentials.');

        return self::SUCCESS;
    }
}
