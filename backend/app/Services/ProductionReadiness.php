<?php

namespace App\Services;

use App\Models\CmsUser;
use Aws\Ses\SesClient;
use Aws\SesV2\SesV2Client;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Symfony\Component\Mailer\Bridge\Mailgun\Transport\MailgunTransportFactory;
use Symfony\Component\Mailer\Bridge\Postmark\Transport\PostmarkTransportFactory;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/** Read-only checks for the documented single-origin deployment. */
class ProductionReadiness
{
    public function configurationChecks(array $database): array
    {
        $rows = [];
        $add = function (string $key, bool $ready, string $detail) use (&$rows): void {
            // Details are fixed instructions, never environment values or secrets.
            $rows[] = compact('key', 'ready', 'detail');
        };
        $origin = $this->origin(config('app.url'));
        $frontend = $this->origin(config('erepair.frontend_url'));
        $key = (string) config('app.key');
        $key = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : $key;
        $add('environment', config('app.env') === 'production' && config('app.debug') === false, 'Use APP_ENV=production and APP_DEBUG=false.');
        $add('encryption_key', is_string($key) && Encrypter::supported($key, config('app.cipher')), 'Configure a valid stable application encryption key.');
        $add('https_origin', $origin !== null && $origin === $frontend, 'Use the same public HTTPS root origin for APP_URL and FRONTEND_URL.');
        $host = $origin ? parse_url($origin, PHP_URL_HOST) : null;
        $port = $origin ? parse_url($origin, PHP_URL_PORT) : null;
        $authority = $host ? $host.($port ? ':'.$port : '') : null;
        $allowedOrigins = array_values(config('cors.allowed_origins', []));
        $stateful = array_values(config('sanctum.stateful', []));
        $add('cors', $origin !== null && $allowedOrigins === [$origin] && config('cors.supports_credentials') === true && config('cors.allowed_origins_patterns', []) === [], 'Allow only the canonical HTTPS origin with credentialed CORS.');
        $add('sanctum', $authority !== null && $stateful === [$authority], 'Set SANCTUM_STATEFUL_DOMAINS to the canonical SPA host only, without a URL scheme.');
        $add('session', config('session.driver') === 'database' && config('session.secure') === true && config('session.http_only') === true && config('session.encrypt') === true && in_array(config('session.same_site'), ['lax', 'strict'], true) && ! config('session.domain') && config('session.path') === '/', 'Use encrypted database sessions, secure HttpOnly host-only cookies and SameSite lax/strict.');
        $password = (string) ($database['password'] ?? '');
        $add('postgresql', ($database['driver'] ?? null) === 'pgsql' && ! empty($database['database']) && ! empty($database['username']) && strlen($password) >= 12 && ! $this->placeholder($password), 'Use the shared PostgreSQL connection with configured credentials of at least twelve password characters.');
        $dbHost = (string) ($database['host'] ?? '');
        $localDatabase = in_array($dbHost, ['127.0.0.1', '::1', 'localhost'], true) || str_starts_with($dbHost, '/');
        $add('database_tls', $localDatabase || ($database['sslmode'] ?? '') === 'verify-full', 'Use verified PostgreSQL TLS for a remote database, including its trusted CA configuration.');
        $add('mail', $this->mailReady(), 'Configure a supported mail transport, real sender and credentials, bounded SMTP timeout and verified remote TLS; test actual delivery separately.');
        $add('core_edition', config('statamic.editions.pro') === false && config('statamic.users.guards.cp') === 'cp' && config('statamic.users.guards.web') === 'cp' && config('statamic.users.repositories.eloquent.model') === CmsUser::class, 'Keep the independent single-editor Statamic Core repository and guards.');
        $add('core_features', ! config('statamic.api.enabled') && ! config('statamic.graphql.enabled') && ! config('statamic.revisions.enabled') && ! config('statamic.git.enabled') && ! config('statamic.system.multisite'), 'Keep native Pro APIs, revisions, Git automation and multisite disabled.');
        $add('private_disk', config('filesystems.disks.local.serve') === false && config('filesystems.disks.local.visibility', 'private') === 'private', 'Keep direct private-disk serving disabled.');
        if (config('hosted.enabled')) {
            $add('durable_files', config('erepair.single_instance') === true && config('cache.limiter') === 'file', 'Database-backed file mirroring requires one instance and the file rate limiter.');
        }
        if (config('erepair.deployment_target') === 'render') {
            $add('render_storage', config('hosted.enabled') === true, 'Free Render requires durable CMS and upload persistence before serving traffic.');
            $add('render_queue', config('queue.default') === 'database', 'Use the database queue consumed by the supervised Render worker.');
        }

        return $rows;
    }

    private function mailReady(): bool
    {
        try {
            $sender = (string) config('mail.from.address');
            $config = config('mail.mailers.'.config('mail.default'), []);
            if (! is_array($config) || ! filter_var($sender, FILTER_VALIDATE_EMAIL) || $this->placeholder($sender)) {
                return false;
            }
            // Match Laravel MailManager's effective URL precedence without connecting.
            if (isset($config['url'])) {
                $config = array_merge($config, (new ConfigurationUrlParser)->parseConfiguration($config));
                $config['transport'] = Arr::pull($config, 'driver');
            }

            return match ($config['transport'] ?? null) {
                'brevo-http' => $this->configured($config['key'] ?? null),
                'smtp' => $this->smtpReady($config),
                'postmark' => class_exists(PostmarkTransportFactory::class)
                    && $this->configured($config['token'] ?? $config['key'] ?? config('services.postmark.token') ?? config('services.postmark.key')),
                'resend' => class_exists(\Resend::class)
                    && $this->configured($config['key'] ?? config('services.resend.key')),
                'mailgun' => $this->mailgunReady($config),
                'ses', 'ses-v2' => $this->sesReady($config),
                'sendmail' => $this->sendmailReady($config['path'] ?? config('mail.sendmail')),
                default => false,
            };
        } catch (Throwable) {
            // Parser/provider exceptions may contain credentials. Never return them.
            return false;
        }
    }

    private function smtpReady(array $config): bool
    {
        $host = trim((string) ($config['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1', '::1'], true);
        $port = filter_var($config['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $timeout = $config['timeout'] ?? null;
        if ($host === '' || (! $local && $this->placeholder($host)) || $port === false
            || ! is_numeric($timeout) || $timeout <= 0 || $timeout > 60) {
            return false;
        }
        $scheme = $config['scheme'] ?? null;
        $scheme = $scheme ?: ($port === 465 ? 'smtps' : 'smtp');
        $verifyPeer = ($config['verify_peer'] ?? null) === '' || filter_var($config['verify_peer'] ?? true, FILTER_VALIDATE_BOOL);
        $autoTls = ($config['auto_tls'] ?? null) === '' || filter_var($config['auto_tls'] ?? true, FILTER_VALIDATE_BOOL);
        $requireTls = filter_var($config['require_tls'] ?? false, FILTER_VALIDATE_BOOL);
        if (! in_array($scheme, ['smtp', 'smtps'], true) || ! $verifyPeer
            || (! $local && $scheme !== 'smtps' && (! $autoTls || ! $requireTls))) {
            return false;
        }
        $username = $config['username'] ?? null;
        $password = $config['password'] ?? null;

        // Anonymous local/managed relays are supported; partial/placeholder auth is not.
        return (($username === null || $username === '') && ($password === null || $password === ''))
            || ($this->configured($username) && $this->configured($password));
    }

    private function mailgunReady(array $config): bool
    {
        $provider = isset($config['secret']) ? $config : config('services.mailgun', []);

        return class_exists(MailgunTransportFactory::class)
            && $this->configured($provider['secret'] ?? null) && $this->configured($provider['domain'] ?? null);
    }

    private function sesReady(array $config): bool
    {
        $provider = array_merge(config('services.ses', []), $config);
        $available = ($config['transport'] ?? null) === 'ses'
            ? class_exists(SesClient::class) : class_exists(SesV2Client::class);
        if (! $available || ! $this->configured($provider['region'] ?? null)) {
            return false;
        }

        // No static pair means the SDK's IAM/default credential chain is deliberate.
        return (empty($provider['key']) && empty($provider['secret']))
            || ($this->configured($provider['key'] ?? null) && $this->configured($provider['secret'] ?? null));
    }

    private function sendmailReady(mixed $path): bool
    {
        if (! $this->configured($path) || ! preg_match('/^(?:"([^"]+)"|\'([^\']+)\'|(\S+))/', trim($path), $parts)) {
            return false;
        }
        $binary = $parts[1] ?: ($parts[2] ?: $parts[3]);

        return (is_file($binary) && is_executable($binary)) || (new ExecutableFinder)->find($binary) !== null;
    }

    private function configured(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '' && ! $this->placeholder($value);
    }

    private function origin(mixed $value): ?string
    {
        if (! is_string($value) || ! filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($value);
        $host = strtolower($parts['host'] ?? '');
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || ! str_contains($host, '.') || filter_var($host, FILTER_VALIDATE_IP)
            || $this->placeholder($host) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        return 'https://'.$host.(isset($parts['port']) && $parts['port'] !== 443 ? ':'.$parts['port'] : '');
    }

    private function placeholder(string $value): bool
    {
        return (bool) preg_match('/change[_-]?me|localhost|(?:^|[.@])example\.(?:com|org|net)$|\.(?:test|invalid|example|local)$/i', $value);
    }
}
