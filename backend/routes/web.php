<?php

use Illuminate\Support\Facades\Route;

// Statamic owns its /cp and content routes; do not add a catch-all route here.
Route::get('/', fn () => response()->json([
    'application' => 'eRepair Laravel backend',
    'frontend' => config('erepair.frontend_url'),
    'api' => '/api/v1',
]));
