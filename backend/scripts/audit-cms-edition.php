<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Statamic\Exceptions\StatamicProRequiredException;
use Statamic\Facades\User as CmsUser;
use Statamic\Http\Middleware\CP\CountUsers;
use Statamic\Statamic;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Read-only diagnosis: no environment, repository, accounts or vendor code are
// changed. The edition override exists only in this CLI process and is restored.
$original = config('statamic.editions.pro');
$report = [
    'pro_enabled' => Statamic::pro(),
    'cms_repository_model' => config('statamic.users.repositories.eloquent.model'),
    'cms_guard' => config('statamic.users.guards.cp'),
    'application_accounts' => User::count(),
    'administrators' => User::where('role', 'admin')->count(),
    'statamic_repository_accounts' => CmsUser::count(),
    'native_content_api_enabled' => config('statamic.api.enabled'),
    'native_graphql_enabled' => config('statamic.graphql.enabled'),
    'global_revisions_enabled' => config('statamic.revisions.enabled'),
    'multisite_enabled' => config('statamic.system.multisite'),
    'git_automation_enabled' => config('statamic.git.enabled'),
];

try {
    config(['statamic.editions.pro' => false]);
    (new CountUsers)->handle(Request::create('/cp'), fn () => true);
    $report['native_core_user_check'] = 'passed';
} catch (StatamicProRequiredException $exception) {
    $report['native_core_user_check'] = 'blocked';
    $report['reason'] = $exception->getMessage();
} finally {
    config(['statamic.editions.pro' => $original]);
}

$report['scope'] = 'Technical user-limit check only; not a full license or production-readiness certification.';
echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
exit($report['native_core_user_check'] === 'passed' ? 0 : 2);
