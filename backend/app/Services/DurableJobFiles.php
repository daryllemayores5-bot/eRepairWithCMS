<?php

namespace App\Services;

use Closure;
use Throwable;

/** Apply the hosted file transaction to jobs as well as HTTP mutations. */
class DurableJobFiles
{
    public function handle(object $job, Closure $next): mixed
    {
        if (! config('hosted.enabled')) {
            return $next($job);
        }
        $files = app(HostedFiles::class);
        try {
            return $files->locked(function () use ($files, $job, $next) {
                $files->restore();
                $result = $next($job);
                $files->capture();

                return $result;
            });
        } catch (Throwable $error) {
            $files->invalidate();
            try {
                $files->locked(fn () => $files->restore(true));
            } catch (Throwable) {
                $files->invalidate();
            }
            throw $error;
        }
    }
}
