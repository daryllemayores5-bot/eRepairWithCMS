# Database deployment and upgrade

Use patched PostgreSQL 16+ for production. Do not deploy local PostgreSQL 14.5
binaries, demo accounts, private backups or maintenance credentials. PostgreSQL
14 reaches end of support November 12, 2026; see the
[official versioning policy](https://www.postgresql.org/support/versioning/).
The production preflight rejects majors below 16. Verify current minor patches
with the hosting provider separately.

## New installation

Provision an empty application database and restricted role. Migration access
needs ownership/DDL rights on its own objects, not cluster superuser, create-role,
replication or RLS-bypass flags. Providers may separate migration/runtime roles.
Configure backend credentials privately. Laravel and Statamic share one database.
Use `DB_SSLMODE=verify-full` remotely and a provider-trusted `DB_SSLROOTCERT` where
needed. Never expose database credentials in frontend variables.

Generate the application key once on a new installation, then run:

```text
php artisan erepair:prepare-cms-auth
php artisan migrate --force
php artisan erepair:create-admin
php artisan erepair:database-check
php artisan erepair:production-check
```

Do not run demo seeders or subscription-demo setup in production. Migrations
create Free/Pro/Premium plans without demo accounts or payments. Initialize durable files
if the host enables the existing file mirror, and complete `deployment/INSTALL.md`.

## Existing installation

1. Back up PostgreSQL, editorial content/blueprints, public/private uploads and
   the stable encryption key. Verify restoration in an isolated environment.
2. Rehearse migrations on a restored staging copy with the target PostgreSQL
   version; drain writers for the migration window.
3. Preserve the environment/key. Never run `migrate:fresh`, `db:wipe`, truncation
   or demo reseeding against existing application records.
4. Run reviewed `php artisan migrate --force`, then database/production checks.
   Hardening adds 19 supporting indexes, two composite unique keys, two ownership
   FKs and stricter state/date checks, without rewriting records.
5. PostgreSQL DDL is transactional; a five-second lock timeout stops a busy
   migration. Invalid legacy data aborts the upgrade. Inspect any affected rows
   in staging and review corrections individually. Do not disable constraints.
6. Rehearse authentication, finder, repairs, estimates, appointments, reviews,
   subscription approval/renewal and native CMS. Verify actual host TLS/backups.

## Read-only checks

```text
php artisan migrate:status
php artisan erepair:database-check --json
php artisan erepair:production-check --json
```

Regenerate the schema-only column/constraint/index reference with
`php artisan erepair:database-schema --write`. Without `--write` the command
prints metadata to standard output. It exports no application records.

Schema checks inspect validated constraints, usable FK indexes and subscription
uniqueness without exposing account records or credentials. Production checks
also inspect connection/TLS settings, version baseline, role privileges, pending
migrations, production data and the native Core identity. Passing schema checks
alone does not certify provider patches, capacity or recovery readiness.

## Tests, backups and rollback

`php artisan erepair:backup-restore-check` is limited to the existing loopback demo.
It retains a sensitive ignored backup, restores to its own random temporary
database, compares fingerprints/file hashes and removes only that temporary
database. Production requires encrypted off-host backups and periodic restore
drills, including the stable encryption key.

Integration tests require `RUN_POSTGRES_TESTS=1` and guard `erepair_test`.
`DatabaseIntegrityTest` covers invalid states, ownership mismatches, missing
indexes, record preservation, rollback and atomic failure. Do not aim tests at
the application database.

Retain the previous code and verified backup; prefer forward fixes. Hardening's
`down()` removes only its added keys/indexes/checks and restores the prior
subscription check without deleting rows, but weakens protection. Review schema
compatibility and drain writers before rollback. Never overwrite newer business
records with an old dump without an explicit recovery decision.
