<?php

namespace App\Providers;

use App\Http\Middleware\AdministratorOnly;
use App\Mail\BrevoHttpTransport;
use App\Models\CmsUser;
use App\Services\DurableJobFiles;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Statamic\Facades\CP\Nav;
use Statamic\Statamic;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Bus::pipeThrough([DurableJobFiles::class]);
        Mail::extend('brevo-http', fn (array $config) => new BrevoHttpTransport((string) ($config['key'] ?? '')));
        if (config('erepair.deployment_target') === 'render') {
            // The public Vercel reverse proxy is the canonical cookie origin.
            // Never derive absolute CP/password-reset links from forwarded hosts.
            $origin = (string) config('app.url');
            if (! filter_var($origin, FILTER_VALIDATE_URL) || parse_url($origin, PHP_URL_SCHEME) !== 'https') {
                throw new \LogicException('Render deployment requires a canonical HTTPS APP_URL.');
            }
            URL::forceRootUrl(rtrim($origin, '/'));
            URL::forceScheme('https');
        }
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by('api:'.$request->ip()));
        RateLimiter::for('authentication', fn (Request $request) => Limit::perMinute(6)->by('authentication:'.$request->ip()));
        ResetPassword::createUrlUsing(function ($user, string $token) {
            if ($user instanceof CmsUser) {
                return cp_route('password.reset', ['token' => $token, 'email' => $user->email]);
            }

            return rtrim(config('erepair.frontend_url'), '/').'/reset-password?'.http_build_query(['token' => $token, 'email' => $user->email]);
        });
        if (class_exists(Statamic::class)) {
            $cpStyles = resource_path('css/erepair-cp.css');
            $this->publishes([
                $cpStyles => public_path('vendor/erepair/css/operations.css'),
                resource_path('js/revenue-projection.js') => public_path('vendor/erepair/js/revenue-projection.js'),
            ], 'erepair-cp-assets');
            // Statamic 6 compiles Blade content with Vue, which removes style tags.
            // Register CSS in the document head; the rules remain scoped to eRepair.
            $cpStyleUrl = '/vendor/erepair/css/operations.css?v='.substr(hash_file('sha256', $cpStyles), 0, 12);
            if (! in_array($cpStyleUrl, Statamic::availableExternalStyles($this->app['request']), true)) {
                Statamic::externalStyle($cpStyleUrl);
            }
            $this->app->booted(function () {
                $this->app['router']->pushMiddlewareToGroup('statamic.cp.authenticated', AdministratorOnly::class);
            });
            Statamic::externalScript('/vendor/erepair/js/revenue-projection.js');
            Statamic::pushCpRoutes(function () {
                require base_path('routes/cp.php');
            });
            Nav::extend(function ($nav) {
                $nav->create('eRepair Operations')->section('eRepair')->url('erepair')->icon('users')->can('access cp');
                $nav->create('Subscription Management')->section('eRepair')->url('erepair/subscriptions')->icon('users')->can('access cp');
                $nav->create('Revenue Dashboard')->section('eRepair')->url('erepair/revenue')->icon('dashboard')->can('access cp');
            });
        }
    }
}
