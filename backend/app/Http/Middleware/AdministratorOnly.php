<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\CmsUser;
use App\Services\CmsAccount;
use Illuminate\Http\Request;

class AdministratorOnly
{
    public function handle(Request $request, Closure $next)
    {
        $user = CmsAccount::identity($request->user('cp'));
        $operator = $user instanceof CmsUser ? $user->operator()->first() : null;
        abort_unless($user instanceof CmsUser && $user->super && $operator && $operator->role === 'admin' && $operator->super && $operator->account_status === 'active', 403);

        return $next($request);
    }
}
