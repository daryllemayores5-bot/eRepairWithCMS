<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/** The single CMS editor; marketplace participants are never CMS identities. */
class CmsUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'password' => 'hashed', 'super' => 'boolean', 'singleton' => 'boolean',
            'preferences' => 'array', 'last_login' => 'datetime',
            'email_verified_at' => 'datetime', 'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
