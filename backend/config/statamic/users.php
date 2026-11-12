<?php

use App\Models\CmsUser;

return [
    'repository' => 'eloquent',
    'repositories' => [
        'eloquent' => ['driver' => 'eloquent', 'model' => CmsUser::class],
    ],
    'guards' => ['cp' => 'cp', 'web' => 'cp'],
    'passwords' => ['resets' => 'cms_resets', 'activations' => 'cms_activations'],
    'impersonate' => ['enabled' => false],
];
