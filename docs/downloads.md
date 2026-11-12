# Required downloads and laptop setup

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


The laptop has PHP 8.4.12, Composer 2.10.3, Node 24.14.0 and npm 11.9.0. The
current Node release is outside the frontend's declared engine range
`^22.22.2 || ^24.15.0 || >=26.0.0`. Update to a compatible patched Node runtime;
`frontend/.nvmrc` selects Node 26 where supported by the version manager.
`frontend/.npmrc` enables strict engine checks during dependency installation.
The installation doctor and release runner now reject unsupported versions
instead of incorrectly marking them Ready. An agent-side request to the official
Node 26 download host failed; no runtime upgrade was performed.

Earlier November 2 installation counts and React/PWA results below are historical.
The active application is Vue and a regular online website. Current suite results
and remaining browser/production acceptance are in `current-verification.md`.

## Required to finish installation

1. **Registry access for reproducible installs and audits.** Installed dependencies are available locally. Fresh installs/updates still need npm registry access and Composer access to Packagist/distribution hosts. The current agent cannot reach those registries, but the normal terminal successfully installed the required packages.
2. **PostgreSQL is now running locally.** The existing 14.5 binaries successfully initialized and started the project-owned cluster on `127.0.0.1:55432`; migrations, demo seeders and integration tests pass. A separate server download is no longer necessary to continue local development. For production, use a maintained patched server: the official [PostgreSQL Windows download page](https://www.postgresql.org/download/windows/) links to certified installers and portable binaries. The deployment example targets PostgreSQL 18.
3. **PHP PostgreSQL extension.** The existing PHP installation has a working `pdo_pgsql` DLL. The project scripts can enable it for their own process without editing system PHP configuration. Enable it in your web-server PHP configuration when serving through Herd or another host.

Docker Desktop is an **optional alternative** for the local database, not an additional requirement. If already installed and running, `compose.yaml` provisions one PostgreSQL service. It is unavailable in this execution environment; native PostgreSQL avoids that extra installation.

The current Statamic Core setup is free for the single CMS editor, with independent marketplace authentication. No Pro license is required or purchased. See `statamic-integration.md`. Older installation counts below are historical.

## Automated setup already prepared

From a normal project terminal with registry access and PostgreSQL configured:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup.ps1
```

With Docker Desktop instead:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/setup.ps1 -DockerDatabase -SeedDemo
```

Use `-SeedDemo` only on a fresh development database; the existing workspace is already seeded. Do not point seeding or test commands at production. The script preserves existing keys, installs from source lockfiles using `composer install` and `npm ci`, publishes CMS assets, prepares its native migration, migrates and runs builds/tests. It no longer rewrites manifests or automatically updates Statamic. Public editorial assets use Laravel serving without a Windows filesystem link.

For this already-installed laptop, `powershell -ExecutionPolicy Bypass -File scripts/setup.ps1 -ReuseInstalledDependencies` completes setup without package downloads. This mode passed with 102 backend tests, 16 component tests, 46 rule tests and the production build. It checks installed dependencies and requires the existing lockfile. Integration tests run when the guarded `.env.testing` connection is configured. Open `/install` on the running built preview for platform-specific PWA guidance; actual OS installation still needs browser confirmation.

Read-only project diagnosis:

```powershell
cd backend
php -d extension=pdo_pgsql artisan erepair:doctor
```

## What happened in this agent session

On 2026-10-30, npm downloads failed with `EACCES`. An attempt to open the official PostgreSQL page in the default browser was rejected by automatic approval policy because sandbox approval is disabled. No browser opening, installer download or system installation occurred in that attempt. User permission to use the laptop does not change those tool-enforced restrictions.

On 2026-10-31, workspace-local PostgreSQL initialization and direct startup succeeded without changing those restrictions. The backend, migrations, seed data, 89-test backend suite with PostgreSQL integration and actual HTTP workflow are verified, alongside 46 standalone TypeScript rule tests. Full setup and actual npm installation were retried again: Composer still cannot connect to Packagist, and npm registry requests return EACCES. A cached npm version lookup does not establish working downloads. See `current-verification.md` for the latest evidence.

On November 2, installation artifacts from the user's terminal confirmed native Statamic had installed. The duplicate two-factor migration was fixed, native assets were published, and all eight migrations completed. The updated backend suite passes 97 tests with 407 assertions; eight native CMS HTTP checks and the repair HTTP journey also pass. The agent's npm installation still fails with EACCES. The release gate correctly stops on 34 missing frontend packages. To continue just the missing installation from a normal terminal with registry access, use `npm.cmd --prefix frontend install`, then `scripts/verify-release.ps1` for the configured build/test gate. Browser acceptance is still required afterward.

Later on November 2, the user's npm installation completed. TypeScript, 11 component tests, the production/PWA build and 46 rule tests pass. The full backend suite now passes 98 tests with 422 assertions, including an API guest-redirect fix found by the new frontend HTTP check. A real React DOM/API test passed the complete technician-finder-to-review journey. The default release gate passes. The earlier missing-package blockers above are historical; remaining tasks are browser/PWA acceptance, dependency review and production configuration. See `current-verification.md`.
