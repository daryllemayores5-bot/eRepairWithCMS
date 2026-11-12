<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $connected = false;
        try {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('select 1');
                $connected = true;
            }
        } catch (Throwable $exception) {
            // Never expose database credentials or connection errors publicly.
            report($exception);
        }

        return response()->json(['data' => [
            'application' => 'eRepair',
            'status' => $connected ? 'ok' : 'degraded',
            'database' => $connected ? 'connected' : 'unavailable',
        ]], $connected ? 200 : 503);
    }
}
