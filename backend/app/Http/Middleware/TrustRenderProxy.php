<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

class TrustRenderProxy extends TrustProxies
{
    public function handle(Request $request, Closure $next)
    {
        if (config('erepair.deployment_target') === 'render') {
            // Render terminates TLS. Keep Host/IP untrusted; canonical URLs are
            // configured explicitly and auth throttling stays conservative.
            $this->proxies = '*';
            $this->headers = Request::HEADER_X_FORWARDED_PROTO;
        }

        return parent::handle($request, $next);
    }
}
