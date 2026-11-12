<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->account_status !== 'active') {
            return response()->json(['message' => 'Your account is inactive.', 'code' => 'account_inactive'], 403);
        }

        return $next($request);
    }
}
