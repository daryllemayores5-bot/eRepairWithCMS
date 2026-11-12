# Installing the eRepair release package

For the split Vercel frontend + Render backend profile, use
`docs/render-vercel.md` and the included `deployment/render/` files instead.
The included `scripts/configure-vercel.mjs` detects this compiled release and
generates static hosting commands without requiring absent frontend source or
an npm install. In the development checkout it retains the locked source build.
The release's `.vercelignore` excludes backend source, environments, private
runtime material and tools while allowing the compiled frontend assets.
Run it only with the real configured Render HTTPS origin, after verifying the
release manifest. Generating configuration intentionally changes the snapshot.
The instructions below target a persistent single-server filesystem.

This package contains the built Vue web application, Laravel/Statamic application source and
pinned Composer dependencies. It excludes installed packages, local credentials,
database files, uploaded photographs, logs and cached configuration. Composer
downloads and a configured PHP/PostgreSQL host are required. No production server
is included. Statamic Core needs no paid Pro license for the single-editor setup.
The SHA-256 manifest checks integrity, not trust
in the publisher. Transfer the package over a trusted channel.

## Before enabling public traffic

1. Finish the browser/mobile checks in `docs/browser-acceptance.md` and review
   `docs/dependency-audit.md`. This package does not certify those checks passed.
2. Provision PHP 8.4+ with required Laravel/Statamic extensions, `pdo_pgsql` and GD,
   Composer 2, current patched PostgreSQL 16+, HTTPS and PHP-FPM. Node 22.12+ is
   needed to verify this artifact; the built frontend needs no Node server.
3. Run `node scripts/verify-artifact.mjs` **before changing this package**. Any
   missing, changed, unexpected or linked file fails verification. Copying
   credentials or generating runtime files intentionally changes this snapshot.
4. Place the release outside any repository-root document root. The Nginx example
   expects `/srv/erepair`, serves `frontend/dist`, and forwards backend paths only
   to `backend/public/index.php`. Adjust paths, domain, certificates and PHP socket.
5. On a new installation copy `deployment/.env.production.example` to
   `backend/.env` and replace every `CHANGE_ME`, including the real mail provider.
   No Statamic license key is needed for Core. Configure database TLS and the host's trusted CA
   as required by your provider. Use one shared HTTPS origin and database.
   For an upgrade, retain the existing environment and encryption key.
   Keep `MAIL_TIMEOUT=15` and `MAIL_REQUIRE_TLS=true` for remote SMTP with
   STARTTLS, or use implicit TLS on port 465. `MAIL_URL`, if supplied, overrides
   individual SMTP fields. Optional API mail transports require their SDK and
   provider credentials; the package does not install them automatically.
6. In `backend`, run `composer install --no-dev --prefer-dist --no-interaction
   --optimize-autoloader`. Installation publishes native CMS assets and the versioned eRepair operations stylesheet. Run
   `composer check-platform-reqs --no-dev`. Allow the PHP user to write only
   `storage`, `bootstrap/cache`, native editorial `content` and the required
   editorial blueprint directories. Do not grant blanket write access or use
   world-writable permissions. Use a single application node with persistent
   shared content/uploads unless distributed storage is explicitly configured.
7. **New installation only:** run `php artisan key:generate`, then
   `php artisan erepair:prepare-cms-auth`, `php artisan migrate --force`, and
   `php artisan erepair:create-admin` to enter credentials privately.
   Never run demo seeders in production. On upgrades, take a verified backup of
   PostgreSQL, editorial content and both upload disks before reviewed migrations;
   preserve existing users, uploads and the stable application key.
   After reviewed migrations, run `php artisan erepair:database-check` and
   `php artisan erepair:production-check`. See `docs/database-deployment.md` for
   transactional hardening, record-preservation checks and upgrade/rollback
   precautions. The schema dictionary is metadata only, not a restore script.
   The Core migration copies the one existing administrator into its independent
   CMS identity while preserving all application records. It stops if multiple
   application administrators need reconciliation. Native CP credentials and
   marketplace credentials start the same but subsequently reset independently.
   `erepair:create-admin` refuses a second editor. Do not deploy an old Pro package.
8. Run `php artisan config:cache`. Set PHP `upload_max_filesize=8M` and
   `post_max_size=32M`, test the edited Nginx configuration with `nginx -t`, then
   enable the site. This Nginx example has not been executed on this laptop.
   Verify HTTPS, cookies/CSRF, the CP, public assets, private-file denial and actual
   password-reset delivery before accepting real users.
   Run `php artisan erepair:production-check` and resolve each failing requirement.
   Use a dedicated PostgreSQL role without superuser privileges, configure the
   provider CA path through DB_SSLROOTCERT for remote database TLS, and keep demo
   accounts off the production database. See `docs/production-readiness.md`.
9. Verify `/api/v1/health` and the full customer/technician workflow in a supported
   browser. Verify online navigation, private-response cache controls and legacy worker retirement. Record these results before promotion.

After the real HTTPS origin is reachable, run from the release root:

```text
node scripts/verify-host.mjs https://YOUR_REAL_DOMAIN
```

This read-only check verifies SPA/deep links, same-origin scripts/styles and MIME
types, absence of install metadata and legacy-worker retirement headers, actual PostgreSQL health,
private-API denial, Sanctum/CORS/secure host-only cookies, native CMS login and
sensitive-file denial. It follows no redirects, validates TLS normally, sends no
passwords and creates no repairs, uploads or mail. CSRF initializes an anonymous
session but does not authenticate it. It is not a substitute for browser/mobile,
real mail delivery or restore acceptance. No actual target host is configured in
the source workspace; the check's fixtures do not certify a public deployment.

## Backups and upgrades

Back up PostgreSQL, `backend/content`, `backend/resources/blueprints`,
`backend/storage/app/private`, `backend/storage/app/public` and the protected
environment/encryption key. Exercise restoration to an isolated environment.
The source project's fresh local backup drill restored 32 demo tables and
43 editorial/upload files successfully with restricted runtime credentials. It does not replace encrypted off-host
production backups and a target-environment recovery exercise.
Local maintenance/rollback secrets under `.local` are intentionally excluded from
this package. Do not copy them or the demonstration environment to production.
The source suite also verifies both reset flows over local SMTP; real production
provider credentials and inbox delivery remain required.
Retain the previous release. Code rollback must not discard new uploads/content
or blindly reverse database migrations; confirm schema compatibility first.
Do not deploy this laptop's demo database or local PostgreSQL 14.5 binaries.

Database repair notifications are synchronous. Native CMS scheduled jobs use the
database queue. On a general PHP host, supervise `php artisan queue:work database`
and configure Laravel's once-per-minute `php artisan schedule:run` cron entry.
The Render image includes Supervisor for PHP-FPM, Nginx, the scheduler and the
database queue worker, with grouped shutdown and automatic restarts. Its PHP
runtime includes pcntl for worker timeouts/signals. Hosted job mutations use the
same PostgreSQL file lock/mirror transaction as HTTP mutations.
Do not use Vite preview or `artisan serve` as a production web server.
