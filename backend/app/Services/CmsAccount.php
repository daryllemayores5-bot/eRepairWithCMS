<?php

namespace App\Services;

use App\Models\CmsUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

class CmsAccount
{
    public static function identity(mixed $user): ?CmsUser
    {
        if ($user instanceof \Statamic\Auth\Eloquent\User) {
            $user = $user->model();
        }

        return $user instanceof CmsUser ? $user : null;
    }

    public function provision(User $operator): CmsUser
    {
        if ($operator->role !== 'admin' || ! $operator->super) {
            throw new LogicException('Only the application administrator can own the CMS identity.');
        }

        return DB::transaction(function () use ($operator) {
            $existing = CmsUser::first();
            if ($existing) {
                if ($existing->user_id !== $operator->id) {
                    throw new LogicException('Statamic Core supports only the existing CMS administrator.');
                }

                return $existing;
            }

            $user = new CmsUser;
            $user->forceFill([
                'user_id' => $operator->id, 'singleton' => true,
                'name' => $operator->name, 'email' => $operator->email,
                'password' => $operator->getRawOriginal('password'),
                'super' => true, 'email_verified_at' => $operator->email_verified_at,
            ])->save();

            return $user;
        });
    }
}
