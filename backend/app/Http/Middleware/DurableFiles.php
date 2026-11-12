<?php

namespace App\Http\Middleware;

use App\Services\HostedFiles;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DurableFiles
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('hosted.enabled')) {
            return $next($request);
        }
        $files = app(HostedFiles::class);
        try {
            return $files->locked(function () use ($request, $next, $files) {
                $files->restore();
                try {
                    $response = $next($request);
                } catch (Throwable $error) {
                    // Render inside the transaction so failed-auth rate limiting is
                    // retained. The hosted profile uses the nontransactional file limiter.
                    $handler = app(ExceptionHandler::class);
                    $handler->report($error);
                    $response = $handler->render($request, $error);
                }
                // Deletes and profile replacements can change files without an
                // uploaded multipart field, so include every unsafe method.
                $mayWriteFiles = ! $request->isMethodSafe();
                if ($mayWriteFiles) {
                    if ($response->getStatusCode() < 400) {
                        $files->capture();
                    } else {
                        $files->restore(true);
                    }
                }

                return $response;
            });
        } catch (Throwable $error) {
            $files->invalidate();
            // File persistence and operational writes share the transaction. On
            // failure, restore the previous committed mirror without losing DB data.
            try {
                $files->locked(fn () => $files->restore(true));
            } catch (Throwable) {
                $files->invalidate();
            }
            report($error);
            abort(503, 'Persistent application storage is unavailable. Please retry.');
        }
    }
}
