# Vercel + Render deployment

This profile keeps Vue on Vercel and the **one Laravel/Statamic backend** on
Render, connected to **one external PostgreSQL database** such as Neon. It does
not switch databases, create a Vue administrator panel or enable Statamic Pro.
Statamic Core still has one linked CMS editor. PWA installation remains removed.

## Current status

November 11 deployment hardening: the image now supervises foreground PHP-FPM,
Nginx, `schedule:work` and a bounded database queue worker. The Render blueprint
explicitly selects the database queue. Initial configuration preflight runs
before migrations; full database/data preflight still runs before processes
start. Nginx and PHP-FPM configuration checks run before Supervisor starts.
Worker job file changes now use the same durable mirror transaction and
PostgreSQL advisory lock as HTTP changes, so native scheduled CMS work does not
bypass persistence or race request-time restores.

The deployment remains **not publicly cleared**. The connected Vercel project was
rechecked: no deployment or domains. Render and Neon remain unconnected; no
production database/mail credentials or canonical public origin are present.
Docker is absent locally, browser rendering remains blocked and fresh npm/
Composer advisory endpoints remain unreachable. These facts are blockers, not
successful acceptance checks.

The supervised scheduler runs only while the Render instance runs. Free web
services sleep after inactivity; expiration timestamps still deny Pro access
on every request even if scheduled bookkeeping paused. Use continuously running
hosting for uninterrupted background work. See [Render Free limitations](https://render.com/docs/free),
[Laravel scheduling](https://laravel.com/framework/docs/scheduling) and
[Supervisor configuration](https://docs.supervisord.org/configuration.html).

The connected Vercel project `erepair-verification` has no deployment or domains
as inspected on November 5, 2026. No Render service, external database or mail
credentials have been supplied. These files prepare deployment; they do not
represent a published or production-accepted system. Docker is unavailable on
the development laptop, so the container must still be built and tested on the
host. Do not publish the frontend alone against a missing backend.

The local checkout is linked to the verified Vercel project through ignored
`.vercel/project.json`. The project has no configured environment variables or
installed database integration. Render and Neon are available connections but
are not yet installed/authorized. Account authorization cannot be supplied by
application code. The full local release gate and native CMS HTTP checks passed
again on November 5; public hosting and external delivery remain unverified.

Render explicitly describes Free instances as previews/hobby services, not
production hosting. They sleep after inactivity, can restart and lose local
files. SMTP ports 25/465/587 are blocked and free Render Postgres expires after
30 days. Use external PostgreSQL for a longer-lived capstone demonstration.
Free provider quotas and availability still apply; this is not a promise of
unlimited, permanent or production-grade free hosting.

Sources: [Render Free limitations](https://render.com/docs/free),
[Render PHP/Docker](https://render.com/docs/deploy-php-laravel-docker),
[Vercel external rewrites](https://vercel.com/docs/routing/rewrites).

## Public origin and authentication

The generated SPA fallback excludes dot paths, backend/deployment/tooling paths
and missing static assets. Valid client routes, including My Subscription, keep
their normal fallback. Backend proxy rules still precede it. This uses Vercel's
[captured negative-lookahead rewrite syntax](https://vercel.com/docs/project-configuration/vercel-json).
Review/regenerate older generated configurations when adopting the rule; the
generator deliberately refuses to overwrite a previously reviewed `vercel.json`.
The packaged HTTPS host verifier checks anonymous denial for subscription,
analytics and revenue administration and delivery of the projection script.

Choose the final Vercel project domain as the single canonical HTTPS origin.
Set both `APP_URL` and `FRONTEND_URL` to that exact origin, without a path.
Set `SANCTUM_STATEFUL_DOMAINS` to its hostname (no scheme) and
`CORS_ALLOWED_ORIGINS` to the origin. Leave `SESSION_DOMAIN` unset. Sessions
use encrypted PostgreSQL storage with secure, HttpOnly, SameSite=lax cookies.

Vercel externally rewrites `/api`, `/sanctum`, `/cp`, `/vendor`, `/storage`,
`/content`, `/img` and `/up` to Render, before Vue Router's fallback. Browser
requests therefore keep one origin, including CSRF and native Statamic login.
Private/backend paths have no-store headers. This uses no Vercel Function for
PHP or upload proxying. Laravel forces generated URLs to the configured public
origin. Only the forwarded TLS scheme is trusted, not forwarded Host or IP.
Throttling therefore conservatively groups traffic behind the proxy; tune a
validated trusted-proxy IP policy before higher-traffic production use.

1. Create the external PostgreSQL project in a region near the Render service.
   Use a fresh database and a dedicated application role with a strong password,
   no SUPERUSER/CREATEDB/CREATEROLE/REPLICATION/BYPASSRLS flags, and permission to
   own/migrate the application schema. Do not use the provider's privileged owner
   as the runtime role. Do not upload the local demo database.
2. Keep certificate verification enabled: `DB_SSLMODE=verify-full`. The image has
   CA certificates; provide `DB_SSLROOTCERT=/etc/ssl/certs/ca-certificates.crt`
   when the provider requires an explicit CA path. Use the provider's actual TLS
   hostname. Review connection-URL query parameters: Laravel's URL configuration
   takes precedence over separate settings; do not downgrade SSL in `DB_URL`.
3. Review `render.yaml` and create a **Free** Docker web service from the repository
   root. The Dockerfile is `deployment/render/Dockerfile`. No paid disk/database
   is included in the blueprint. Configure every `sync: false` value privately
   in Render. Keep a stable valid Laravel `APP_KEY` (`php artisan key:generate
   --show` on a trusted private terminal); never regenerate it on restart.
4. Configure the canonical origin, database, mail and first administrator values
   before starting Render. Do not place secret values in Git, screenshots or chat.
   A new administrator password must have at least twelve characters; use a strong
   unique value. A customer's existing email cannot be promoted by bootstrap.
5. Startup applies additive migrations, seeds only the reference catalog, initializes
   or restores durable files, and provisions the first administrator. Existing
   accounts/passwords remain unchanged. Remove `EREPAIR_ADMIN_PASSWORD` from host
   settings after successful provisioning. Keep automatic deploys disabled until
   release verification passes. Review startup/migration logs if health fails.
   Startup runs `erepair:production-check --json` before opening the server and
   exits if required configuration is invalid. It does not treat an audit,
   external mail delivery or browser checks as verified.
6. After Render supplies its actual `https://<service>.onrender.com` origin, run
   `node scripts/configure-vercel.mjs https://<service>.onrender.com`. This writes
   `vercel.json` exclusively, refuses credentials/invalid origins and does not
   overwrite an existing reviewed configuration. Commit/review it before import.
   In a compiled release package (without `frontend/package.json`) the same helper
   verifies the built entry file and skips npm installation/build. Deploy that
   package root as static output; the backend rewrites and privacy headers stay
   identical. Source checkouts retain the locked npm build. Never generate an
   origin for a backend service that does not actually exist.
7. Import the **repository root** into the intended Vercel project. The generated
   config runs `npm --prefix frontend ci`, then the same-origin production build,
   and serves `frontend/dist`. Source builds require a patched Node release
   satisfying `frontend/package.json` (`^22.22.2 || ^24.15.0 || >=26.0.0`);
   `frontend/.nvmrc` selects Node 26 and npm enforces engine compatibility. The
   `.vercelignore` keeps backend secrets and runtime uploads out of the frontend
   deployment. Production must be publicly accessible after acceptance; the
   currently connected project's preview protection is enabled.

Use the full source checkout for Vercel's npm build. The transferable release ZIP
contains the already-built frontend and backend source, not the full Vue
development checkout; it remains suitable for the documented single-server
installation and backend container context.

## Durable files on an ephemeral service

`EREPAIR_DURABLE_FILES=true`, `EREPAIR_SINGLE_INSTANCE=true` and
`CACHE_LIMITER=file` enable a bounded single-instance PostgreSQL file mirror.
The `hosted_files` and `hosted_file_states` tables live in the same application
database. They persist native CMS content, blueprints, fieldsets, private uploads,
public assets and asset metadata. Private bytes are **not** exposed under `/storage`;
repair downloads still pass the existing authorization policies.

`erepair:initialize-hosted-files` copies release files only on the first start.
On later starts the database wins, including deletions; a release seed never
replaces a published CMS edit. The application rehydrates its native filesystem
and clears Stache as needed. Checksums, path validation, file limits and a
transaction advisory lock protect synchronization. Successful unsafe HTTP
requests capture changes, including API deletions without a file upload field.
Failed persistence rolls back transactional application writes and restores
committed files. A safe 503 is returned when durable storage cannot be maintained.

Database lock waits are limited to five seconds, so a stalled request does not
block the whole single-instance service indefinitely. Busy requests may return
503 and should be retried after the blocking request finishes.

Default raw-file quota: **128 MiB**, at most **2,000 files/directories**, at most
**8 MiB per file**. Base64 adds about one third to payload size; PostgreSQL row,
index, WAL and other application data use additional database space. This is
appropriate for a bounded capstone demo, not a substitute for object storage at
scale. All requests serialize with this profile; run only one instance. The file
rate limiter is outside database rollback but resets with the ephemeral instance.
Do not edit managed files manually while the service is running. Out-of-band
CLI writes are not automatically captured. Do not change enabled roots or quotas
below existing usage without a reviewed migration/recovery plan.

Back up PostgreSQL (including the new file tables), stable APP_KEY and provider
configuration to independent secure storage. Test restoring into a disposable
database and a clean container. Database snapshots alone are not an off-host
backup policy. A clean hosted restart/redeploy must visibly retain an edited FAQ,
a public CMS asset and a private repair attachment before launch is accepted.

## Password reset over HTTPS

Use `MAIL_MAILER=brevo-http`, a private `BREVO_API_KEY`, and an actual verified
`MAIL_FROM_ADDRESS`. The adapter uses the installed HTTP client and Brevo's fixed
HTTPS endpoint, a five-second connect timeout and a fifteen-second total timeout.
It does not retry ambiguous sends or log secret provider bodies. The application's
password-reset notifications support HTML/text; mail attachments are explicitly
rejected by this adapter. Existing database repair notifications are unchanged.

Create/verify the sender in your own Brevo account and inspect current free-plan
limits. Tests fake the HTTP provider; a real customer and CMS password-reset email
must be received and its canonical HTTPS link exercised separately.
See [Brevo transactional API](https://developers.brevo.com/reference/send-transac-email).

## Acceptance before public launch

- Build the actual container and verify PHP extensions/native Statamic assets.
- Run `php artisan erepair:production-check --json` against hosted configuration.
  Never relax checks or delete local data just to obtain a green result.
- Run `node scripts/verify-host.mjs <canonical-https-origin>` (see its CLI usage).
  Confirm security headers, native CP CSS/JS and no private-response CDN caching.
- Exercise customer registration/login, technician approval, finder/map/profile,
  repair creation/photos, appointments, estimates/approval, progress, completion
  and review through the public Vercel origin. Test a representative 5 MiB upload
  through the external rewrite and a free-service cold start; do not infer these
  from local tests. Vercel's external proxy timeout can still limit slow requests.
- Verify CMS login/edit/publish, password resets, restart persistence, unauthorized
  attachment rejection, mobile navigation and signed-out privacy.
- Run fresh `composer audit` and `npm audit` with registry access; review actual
  advisories and dependencies. A fresh clean dependency install and the Linux
  container build remain unverified on this restricted laptop.

Keep this profile separate from the existing single-server Nginx installation
in `deployment/INSTALL.md`. The old profile uses persistent filesystem storage;
it must not be copied unchanged onto a free ephemeral service.
