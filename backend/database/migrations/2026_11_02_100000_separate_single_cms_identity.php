<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $administrators = DB::table('users')->where('role', 'admin')->get();
        if ($administrators->count() > 1) {
            throw new RuntimeException('Resolve multiple application administrators before installing the single-editor CMS.');
        }
        Schema::create('cms_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->boolean('singleton')->default(true)->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('super')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('avatar')->nullable();
            $table->json('preferences')->nullable();
            $table->timestamp('last_login')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        DB::statement('ALTER TABLE cms_users ADD CONSTRAINT cms_users_single_editor CHECK (singleton = true)');
        foreach (['cms_password_reset_tokens', 'cms_password_activation_tokens'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
        if ($operator = $administrators->first()) {
            if (! $operator->super) {
                throw new RuntimeException('The existing application administrator must be a super administrator.');
            }
            // Preserve the original identity and password; copy only the CMS
            // login to its own repository. Do not migrate marketplace accounts.
            DB::table('cms_users')->insert([
                'user_id' => $operator->id, 'singleton' => true,
                'name' => $operator->name, 'email' => $operator->email,
                'password' => $operator->password, 'super' => true,
                'email_verified_at' => $operator->email_verified_at,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_password_activation_tokens');
        Schema::dropIfExists('cms_password_reset_tokens');
        Schema::dropIfExists('cms_users');
    }
};
