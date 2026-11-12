<?php

return [
    // Bounded single-instance persistence for hosts with an ephemeral filesystem.
    'enabled' => (bool) env('EREPAIR_DURABLE_FILES', false),
    'quota_bytes' => min(256, max(8, (int) env('EREPAIR_FILE_QUOTA_MB', 128))) * 1024 * 1024,
    'max_file_bytes' => 8 * 1024 * 1024,
    'max_files' => 2000,
    'marker' => storage_path('framework/hosted-files-revision.json'),
    'roots' => [
        'content' => base_path('content'),
        'blueprints' => resource_path('blueprints'),
        'fieldsets' => resource_path('fieldsets'),
        'private' => storage_path('app/private'),
        'public' => storage_path('app/public'),
        'asset_meta' => storage_path('statamic/assets'),
    ],
];
