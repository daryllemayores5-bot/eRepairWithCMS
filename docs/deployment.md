# Deployment plan and environment reference

The installed Vue/Laravel/PostgreSQL/Statamic system passes compilation, component/backend tests, the production build and the real Vue DOM/API repair workflow. The automated release gate passes. Production clearance still requires browser/mobile acceptance, review of the npm build-tool findings in `dependency-audit.md`, and host configuration. `scripts/setup.ps1` automates installation; `scripts/verify-release.ps1` runs the automated gate; optional `-LiveApi` adds preview-dependent delivery/live DOM checks. `scripts/postgres.ps1` supports the local database, and `compose.yaml` supplies an optional PostgreSQL 18 service. No production deployment has been attempted.

Target a PHP 8.4+ host with a maintained PostgreSQL server and Node build stage. One public origin should serve `frontend/dist` with SPA fallback while routing `/api`, `/sanctum`, `/cp` and Statamic assets to `backend/public/index.php`. Ensure SPA fallback does not swallow server routes. Set Laravel's document root to `backend/public`, never the repository root. Native CMS assets must be installed and accessible. Protect storage and `.env` files.

An example single-origin Nginx configuration is in `deployment/nginx.conf.example`; adjust domain, paths, PHP socket and certificates. Set PHP `upload_max_filesize=8M` and `post_max_size=32M` so five 5 MB device images can reach Laravel's validators. The public editorial disk is separate from the private repair disk. The Nginx example could not be validated here because Windows denied access to the temporary configuration.

After host configuration/migrations run `php artisan erepair:production-check`
from `backend`. It inspects the actual configuration/database without exposing
secrets and fails unsafe or demo settings. The local demo intentionally fails.
The verified local backup drill and production recovery requirements are described
in [production readiness](production-readiness.md). The complete implementation
and acceptance map is in [requirements checklist](requirements-checklist.md).
The local runtime is now restricted and its CLI maintenance credentials stay
separate; see [database runtime](database-runtime.md). Both reset flows also pass
real loopback SMTP delivery/token tests; see [mail verification](mail-verification.md).
The production provider and actual public inbox delivery still need configuration.

After the host serves its real HTTPS origin, run the packaged read-only check:
`node scripts/verify-host.mjs https://YOUR_REAL_DOMAIN`. It verifies delivery,
PostgreSQL health, private-route denial, secure session/CORS headers, native CMS
login and legacy worker retirement script without logging in or creating business records. The fourteen
automated fixture tests verify success and refusal paths; they do not establish
that a real host passed. See `deployment/INSTALL.md` for its exact scope.

Local PHP serving uses Laravel's built-in public-disk route (`filesystems.disks.public.serve=true`), avoiding a Windows storage link. The production Nginx public-disk alias works without that link too. Private-disk serving stays disabled. Four backend tests verify public image delivery, missing assets, private/traversal denial and rejection of unsigned uploads; a real HTTP image check also passed without a filesystem link.

Build using committed lockfiles (`composer install --no-dev --optimize-autoloader`, `npm ci`, `npm run build:production`). Verify installation hooks and CMS assets in the release artifact. Back up the database and editorial content/uploads, run reviewed migrations, then cache configuration/routes only after native Statamic compatibility is verified. Maintain a queue worker for database notifications/jobs when introduced and a scheduler only when needed. Use HTTPS and the single-editor Statamic Core configuration; no Pro license is required for its current features.

## Preparing a transferable release

From the workspace root run `npm.cmd --prefix frontend run build:production`, then
`node scripts/prepare-release.mjs`. The explicit production build overrides local
Vite API/CP origins with same-origin paths; it prevents a development `.env` from
embedding this laptop's address in the deployed administrator link. Ordinary
`build` remains available for local configuration. The release gate uses the
explicit production build.

The packager creates a new timestamped directory in `.local/releases`, never
overwriting an existing release. It includes Laravel source, native editorial
definitions/content, Composer lockfile, compiled Vue files, installation
instructions and a SHA-256 file manifest. It excludes vendor/node_modules, local
environments/credentials, database files, uploaded files and runtime caches. It
rejects linked files and localhost addresses with a port/path in compiled assets.
Bare localhost URL-constructor defaults in upstream routing/HTTP libraries are
permitted. This check does not replace a security review.

Run `node scripts/verify-artifact.mjs <release-directory>` before transfer, then
the included verifier on the destination before editing configuration or running
Composer. Integrity verification rejects altered, missing or unexpected files;
the manifest is not a publisher signature. The package requires Composer downloads
on the host. Follow its `deployment/INSTALL.md` and production environment template;
no local demo records or uploaded files are transferred. Fresh-host installation
and Nginx execution still require acceptance on the actual target server.

On Windows with existing Nginx/PHP-CGI binaries, `scripts/test-nginx.ps1` derives
a temporary loopback configuration from the supplied Nginx example, chooses free
ports, runs HTTP/static/API/CP checks and stops only its own temporary processes.
It preserves the existing development servers and environment. Its attempted run
in this restricted session failed because Nginx could not open the generated
configuration (Windows access denied); no routing or TLS success is claimed.
Do not work around this by disabling browser/OS security. The target host still
needs its own `nginx -t` and acceptance checks.

| Variables | Purpose |
| --- | --- |
| APP_NAME, APP_ENV, APP_URL | eRepair, production, canonical HTTPS origin |
| APP_KEY | Unique Laravel encryption key; keep stable and secret; never overwrite an installed instance |
| APP_DEBUG | false in production; true locally only |
| APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE | English UI and Philippine seed locale |
| FRONTEND_URL | Absolute frontend URL for links and future reset flows |
| DB_CONNECTION | pgsql; no alternate database for administrators |
| DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD | Shared PostgreSQL connection; least-privilege credentials |
| DB_SSLMODE | Provider-required TLS mode; prefer certificate verification when configured |
| DB_SSLROOTCERT | Readable provider CA certificate file for verified remote PostgreSQL TLS |
| SESSION_DRIVER, SESSION_LIFETIME, SESSION_ENCRYPT | database, expiry in minutes, encrypted session payload |
| SESSION_DOMAIN, SESSION_PATH | Host-only cookies normally; explicit parent domain only if using compatible subdomains |
| SESSION_SECURE_COOKIE, SESSION_HTTP_ONLY, SESSION_SAME_SITE | true, true, lax for recommended same-origin production |
| SANCTUM_STATEFUL_DOMAINS | Trusted SPA hosts with ports locally; no URL scheme |
| CORS_ALLOWED_ORIGINS | Exact comma-separated origins, no wildcard when credentials are enabled |
| CACHE_STORE, QUEUE_CONNECTION | file/database initially; configure workers and storage per deployment |
| FILESYSTEM_DISK | local private disk for repair attachments; editorial public assets are separate |
| LOG_CHANNEL, LOG_STACK, LOG_LEVEL | Server logging; exclude credentials, tokens and private attachment data |
| MAIL_MAILER, MAIL_FROM_ADDRESS, MAIL_FROM_NAME | Log locally; real provider configuration before production resets/email |
| MAIL_TIMEOUT, MAIL_REQUIRE_TLS | SMTP timeout defaults to 15 seconds; require verified TLS for remote production SMTP |
| MAIL_URL, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD | Provider SMTP settings; MAIL_URL overrides individual fields when present; keep credentials server-side |
| STATAMIC_PRO_ENABLED, STATAMIC_LICENSE_KEY | Legacy compatibility values; Core is fixed in config, Pro disabled and no license key required |
| STATAMIC_IMPERSONATE_ENABLED | false by default |
| VITE_API_URL | Browser public API prefix, normally /api/v1 |
| BACKEND_URL | Vite development-only proxy destination |
| VITE_BACKEND_PUBLIC_URL | Public origin of native Statamic CP; use the shared HTTPS origin in production |
| DEMO_PASSWORD | Explicit non-production seeder password, minimum 12 characters; never a production credential |
| RUN_POSTGRES_TESTS | Opt in to destructive integration tests on the dedicated erepair_test database only |

`config/erepair.php` fixes display currency to PHP and business time zone to Asia/Manila. Laravel/database timestamps remain UTC. Production acceptance must include two-user ownership isolation, verification visibility, CSRF and session expiry, password resets, upload access controls, estimate/appointment conflicts, backups/restoration and private-response cache inspection.
