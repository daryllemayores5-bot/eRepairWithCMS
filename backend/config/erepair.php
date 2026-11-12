<?php

return [
    'business_timezone' => 'Asia/Manila',
    'currency' => 'PHP',
    'deployment_target' => env('EREPAIR_DEPLOYMENT_TARGET'),
    'single_instance' => (bool) env('EREPAIR_SINGLE_INSTANCE', false),
    'frontend_url' => env('FRONTEND_URL', 'http://127.0.0.1:5173'),
];
