# Phase 1 verification — 2026-10-30

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


**Status: partial; do not advance to feature-complete claims.** The initial folder was empty and was not a Git repository. No pre-existing source was overwritten. The Laravel starter was extracted from the existing local Composer cache; dependencies were resolved from cached package metadata. Newer stable patch releases may exist online.

## Evidence

| Check | Result |
| --- | --- |
| Tooling | PHP 8.4.12; Composer 2.10.3; Node 24.14.0; npm.cmd 11.9.0 |
| Laravel installation | Installed 13.35.0 and locked dependencies |
| Sanctum installation | Installed 4.3.3; native CSRF route registered |
| Composer validation | Pass: composer.json and composer.lock agree |
| PHP platform requirements | Pass with local process `pdo_pgsql` configuration; extension is disabled in system php.ini |
| PHP formatting | Pint completed on application/config/test changes |
| Backend HTTP tests | **8 pass, 36 assertions; 1 skipped** |
| PostgreSQL test | Skipped; dedicated server/database unavailable |
| Statamic installation | Blocked: package absent from cache; registry network access unavailable |
| Frontend installation | Blocked: required packages absent from cache, first offline failure `@fontsource/inter` |
| Frontend production build | Blocked: `tsc` unavailable without installed dependencies |
| Frontend tests | Blocked: `vitest` unavailable; three authored tests are unrun |
| Dev servers and browser | Not verified; no running frontend or healthy database-backed application |
| PWA installation | Not implemented; scheduled for Phase 7 |

Backend tests exercise the Laravel HTTP kernel, PostgreSQL success/failure branches **with mocks**, non-PostgreSQL rejection, guest 401, explicit user resource fields, no-store headers, Sanctum CSRF cookie issuance, credentialed CORS and Philippine display defaults. They do not prove login, persistence, migration compatibility, CP access or full browser flow.

The actual local database probe returned `:5432 - no response`. A workspace-only PostgreSQL initialization attempt failed with `could not create restricted token: error code 87`. No system PHP configuration was edited and no PostgreSQL server was started. `.local/php/pgsql.ini` provides a working per-process extension override; `.local/` is ignored and not required for installation elsewhere.

## Full-story verification

Story: opening React `/setup` requests Laravel `/api/v1/health`, which queries PostgreSQL and returns a connection state to the page.

| Boundary | State | Evidence |
| --- | --- | --- |
| UI renders | Blocked | Required npm dependencies cannot install |
| Browser → API | Unverified | No frontend/browser runtime available |
| API → PostgreSQL | Unverified live | No PostgreSQL service; mocked branches pass |
| API response format | Tested in HTTP kernel | Success/degraded JSON and privacy assertions pass |
| Response → UI | Authored, unrun tests | Success, error/retry and unknown-route coverage |

The first end-to-end boundary is broken at dependency installation. No end-to-end success is claimed.

## Created / modified files

All application files are new to this initially empty folder. Key application additions and starter adaptations:

- Root: `.gitignore`, `README.md`.
- Frontend configuration: `package.json`, `.env.example`, `index.html`, `components.json`, `vite.config.ts`, `vitest.config.ts`, `tsconfig.json`, `tsconfig.app.json`, `tsconfig.node.json`.
- Frontend source: `src/main.tsx`, `src/App.tsx`, `src/index.css`, `src/components/ui/button.tsx`, `src/services/api.ts`, `src/lib/utils.ts`, `src/lib/format.ts`, `src/App.test.tsx`, `src/test/setup.ts`.
- Backend entry/configuration: `composer.json`, `composer.lock`, `.env.example`, `.env.testing.example`, `artisan`, `public/index.php`, `bootstrap/app.php`, `bootstrap/providers.php`, `config/auth.php`, `config/database.php`, `config/cors.php`, `config/erepair.php`, `config/statamic/users.php`, `config/statamic/editions.php` and standard Laravel logging/session/mail/cache/queue/filesystem configurations.
- Backend application: `app/Models/User.php`, `app/Console/Commands/CreateAdministrator.php`, `app/Http/Controllers/Api/HealthController.php`, `app/Http/Resources/UserResource.php`, `app/Http/Middleware/PreventPrivateResponseCaching.php`, `routes/api.php`, `routes/web.php`, `scripts/install-cms-assets.php`.
- Database: foundational Laravel users/session/cache/jobs migrations, `database/migrations/2026_10_30_000001_prepare_shared_statamic_users.php`, `database/factories/UserFactory.php`, `database/seeders/DatabaseSeeder.php`.
- Tests: `phpunit.xml`, `tests/TestCase.php`, `tests/Feature/FoundationTest.php`, `tests/Feature/PostgresIntegrationTest.php`.
- Documentation: `architecture.md`, `database-schema.md`, `development-plan.md`, `compatibility.md`, `api-documentation.md`, `user-roles.md`, `technician-finder.md`, `repair-workflow.md`, `statamic-integration.md`, `pwa-setup.md`, `deployment.md`, and this report under `docs/`.

The starter also supplied standard Laravel support files and agent bootstrap instructions. Laravel Boost was installed as required by those instructions and generated local development guidelines. Runtime caches, vendor dependencies, local environment keys and temporary cache files are excluded from source control.

## Finish Phase 1

1. Enable `pdo_pgsql` in the CLI/web PHP configuration and provide a working PostgreSQL server.
2. Complete native Statamic installation, compare shared auth schema against the resolved version, and validate `/cp` using the shared administrator.
3. Install frontend dependencies over registry access, commit the generated lockfile, and run typecheck/build/tests. Resolve any compiler or peer-dependency issues before Phase 2.
4. Migrate PostgreSQL and run the opted-in integration test on `erepair_test`.
5. Start both development servers and verify the real `/setup` flow, mobile layout, cookies and CP permissions.

Commands and environment details are in the root README. Phase 2 is identity/domain data; Phase 3 is the working technician finder MVP. Remain on this stack rather than introducing alternate databases or admin panels to work around the environment.
