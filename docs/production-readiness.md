# Production configuration and backup acceptance

The automated build gate and production acceptance are separate checks. The local
demo is intentionally not configured as a public production instance. Do not make
it appear production-ready by changing flags or removing existing user records.
Provision a fresh deployment database and configure the actual host instead.

## Read-only production check

The Render entrypoint first runs configuration-only preflight before migrations,
then full database preflight before serving traffic. Its Supervisor configuration
starts PHP-FPM, Nginx, the Laravel scheduler and a database queue worker. The
Render-specific `render_queue` check requires `QUEUE_CONNECTION=database` so
dispatched native CMS jobs reach that worker. General hosts must supply the
equivalent worker and once-per-minute scheduler cron configuration.

After configuring the destination environment, installing dependencies, migrating
and creating the one administrator, run from `backend`:

```text
php artisan config:cache
php artisan erepair:production-check
```

The command exits nonzero if required checks fail. `--json` emits named checks
without passwords, encryption keys, email addresses or connection values.
`--skip-database` inspects configuration/runtime only and explicitly marks database
inspection as skipped; it cannot establish database or complete deployment readiness.
Configuration-inspection failures also produce safe JSON under `--json`, without
serializing connection exceptions or credentials.

Checks cover production/debug flags, encryption-key validity, matching root HTTPS
origins, exact credentialed CORS/Sanctum hosts, secure encrypted host-only sessions,
PostgreSQL credentials, verified remote-database TLS, a real mail transport/sender,
free Core features and independent CMS guards, private disk serving, writable
persistent directories, PHP extensions, actual database connectivity, non-superuser
database access, pending migrations, one active CMS editor and absence of demo data.
The check reads configuration and database records without changing them.

Mail inspection resolves `MAIL_URL` overrides before validating SMTP, rejects
missing optional provider SDKs/keys and checks bounded timeouts plus verified,
required TLS for remote SMTP. The production template supplies `MAIL_TIMEOUT=15`
and `MAIL_REQUIRE_TLS=true`. This check sends no email; actual target-provider
delivery remains a separate acceptance step. See `mail-verification.md`.

The role check now requires all five inspected attributes to be false: superuser,
CREATEDB, CREATEROLE, replication and BYPASSRLS. Incomplete inspection fails closed.
This checks attributes, not every possible inherited privilege or database ACL.
The local restricted login passes. See `deployment-readiness.md` for current
application evidence and the actual remaining production-clearance requirements.

The current laptop correctly fails production checks for its local/demo settings:
development environment, HTTP origins, development cookies/trusted hosts, log mail,
demonstration accounts. The earlier superuser runtime has now been replaced with
a separate restricted login; the database-role check passes. Core, migrations, private
storage, database connectivity and the single CMS editor pass. Do not promote this
local environment file or database to a public host.

Use a dedicated PostgreSQL role with ownership/permissions for this application's
schema and migrations, not a database superuser. Example administration commands
on a fresh target, run by its database administrator:

```text
createuser --pwprompt --no-superuser --no-createdb --no-createrole erepair
createdb --owner=erepair erepair
```

These commands prompt privately and have not been run against a production host.
Do not run them over an existing deployment. Managed database providers may require
their own provisioning flow. For remote PostgreSQL configure `DB_SSLMODE=verify-full`
and `DB_SSLROOTCERT` with the readable provider CA file path. The Laravel connector
now accepts that path. Keep credentials and CA/private-key material on the host;
never put secrets in frontend environment variables.

Passing this command does **not** prove actual HTTPS/certificate routing, mail
delivery, browser layout/accessibility/maps, legacy-cache retirement, production backup restoration or dependency-advisory acceptance.
Those checks still require the target infrastructure and supported browsers.

## Verified local backup/restore drill

From `backend`, with matching PostgreSQL client binaries on PATH, run:

```text
php artisan erepair:backup-restore-check
```

Use `--pg-bin` to supply the directory containing `pg_dump` and `pg_restore` if
necessary. The command refuses production, remote hosts, non-PostgreSQL connections,
other database names and environments without the existing local demo marker.
It is a constrained demonstration drill, not a production backup scheduler.

The command exports a repeatable-read snapshot, records fingerprints/counts for
all public tables, makes a custom-format dump and restores it into a newly created
random `erepair_restore_check_*` database. It compares the restored fingerprints,
then removes only that database it created itself. PostgreSQL credentials are
passed through the child process environment, not shell arguments or output.
Failures never replace the working application's database or uploads.

It also copies native editorial content, blueprints, private repair uploads and
public editorial uploads, rejecting symbolic links. It restores those copies to a
separate staging tree and verifies SHA-256 hashes. Copies stay in ignored
`.local/backups`; their contents are sensitive and are excluded from deployment
artifacts. Incomplete attempts are retained rather than silently deleted.

The latest drill after restricting runtime credentials passed: **32 database tables**
matched the exported snapshot and **43 files** matched after restoration. The temporary restore database
was removed. This validates this demo snapshot and file set, not every future backup.

Before production, implement encrypted off-host backups and retention with your
hosting/database provider. Separately retain the stable application encryption key
and protected environment in a secure recovery store; this drill does not copy
them. Exercise restoration on isolated production-like infrastructure using that
key, the matching release, persistent CMS content and both upload disks. Verify
decryption, login and repair workflows before declaring recovery acceptance.

The local drill now reads separate, ignored maintenance credentials only for
creating/removing its own temporary database. The web login has no CREATEDB grant.
See [database runtime](database-runtime.md) and [SMTP verification](mail-verification.md)
for the verified privilege separation and actual local mail-delivery tests.
