# eRepair

Vue 3 / TypeScript technician discovery and repair coordination, backed by the
existing Laravel REST API, native Statamic Core administrator interface and one
PostgreSQL database. Vue is now the active application in `frontend/`.

The frontend uses Composition API and `<script setup>`, Vue Router, Pinia,
TanStack Vue Query, Axios, Zod, Tailwind CSS, Lucide Vue, Vue Sonner and Leaflet.
The original styling, fonts, routes, API payloads and cookie/CSRF authentication
were retained. The backend framework and database schema were not changed.

## Migration and verification

The Vue production build and complete route/authentication suite pass. The real
Vue-to-Laravel journey also passed discovery, login, private photo submission,
technician acceptance, appointment confirmation, estimate/customer approval,
repair completion, review, persisted history and logout. Live acceptance adds
clearly labeled demonstration history without reseeding or replacing existing
records. See [current verification](docs/current-verification.md) and the
[migration record](docs/vue-migration.md) for exact totals and remaining checks.

The original React source remains available for migration regression checks:

- `frontend-react-reference/`: source/configuration/lockfiles and a SHA-256 manifest.
- `.local/retired/frontend-react-runtime/`: the redundant installed application
  and build, archived outside the active project folders during cleanup.

Neither React backup is included in deployment artifacts. Run
`node scripts/verify-react-reference.mjs` to verify the preserved source.

Physical browser/mobile visual acceptance remains open because this environment
could not start a usable browser GPU process. The installation reported five
moderate findings in development test tooling; fresh audit access is restricted.
These findings and Node engine warnings are recorded in
[dependency audit](docs/dependency-audit.md). Public deployment is not claimed.

## Implemented functionality

Database structure, ER diagram and deployment checks are documented in
[database schema](docs/database-schema.md), the generated
[data dictionary](docs/database-data-dictionary.md) and
[database deployment guide](docs/database-deployment.md). Run
`php artisan erepair:database-check` from `backend` for read-only integrity/index
checks; regenerate the dictionary with `php artisan erepair:database-schema --write`.

The optional technician subscription system adds Free, ₱199/30-day Pro and
₱399/30-day Premium plans,
administrator-approved **DEMO payments only**, native subscription/revenue pages,
renewal, scheduled plan changes, expiration, finder badges and paid analytics.
Premium includes daily date-range view/inquiry statistics. No real payment provider is
used. See [subscription setup and capstone demo](docs/subscription-revenue.md).

- Technician discovery, filters, comparison, public profiles, reviews, transparent
  matching, approximate service-area maps and optional geolocation.
- Customer/technician registration, sessions, password recovery, profiles,
  availability, dashboards, appointments and notifications.
- Five-step repair requests, private photos/documents, accept/decline, estimates,
  approval, status/history, technician notes, completion and reviews.
- Native Statamic verification, participant management, repair monitoring,
  catalogs, moderation, reports, activity and published editorial content.
- Independent marketplace and single-editor CMS identities in the same backend
  and database, with ownership policies and protected downloads.
- Regular online web delivery. No PWA installation requirement or offline mode.

## Installation

Requirements: PHP 8.4 with PostgreSQL/GD extensions, Composer 2, PostgreSQL and a
Node release compatible with the locked dependencies. The test-tool CLI packages
require Node `^22.22.2 || ^24.15.0 || >=26.0.0`. `frontend/.nvmrc` selects Node 26
for version-manager setups; `frontend/.npmrc` enforces compatible engines during
installation. This laptop's Node 24.14.0 is outside that range. Existing test/build
results remain evidence, but the corrected doctor/release preflight rejects this
runtime. Install a compatible patched Node release before fresh installation or
release verification. The doctor reads the requirement from the frontend manifest.

From the workspace root, install from the committed/resolved lockfile:

```powershell
npm.cmd --prefix frontend ci --cache .local/npm-cache
npm.cmd --prefix frontend run typecheck
npm.cmd --prefix frontend test
npm.cmd --prefix frontend run build:production
```

For an existing installed laptop, `scripts/setup.ps1 -ReuseInstalledDependencies`
retains environment files and reuses the installed dependencies. Normal setup
installs the Composer/frontend locks. Review `docs/deployment.md` for a fresh
backend installation. Do not overwrite an existing backend environment or key,
reseed an existing database, or seed production.

## Local development

Start the existing local database if needed:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/postgres.ps1
```

Run Laravel and Vue in separate terminals:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1
```

```powershell
npm.cmd --prefix frontend run dev
```

The default frontend is `http://127.0.0.1:5173`; Laravel and native Statamic are
`http://127.0.0.1:8000` and `http://127.0.0.1:8000/cp`. Keep the hostname consistent.
The Vue dev and preview servers proxy `/api` and `/sanctum` to Laravel.

Port 5173 is currently occupied by another application. The isolated Vue demo
uses **http://127.0.0.1:5183** and Laravel port 8001. A separate compiled preview
can run on **http://127.0.0.1:5184** for release verification. To reproduce it:

```powershell
# Backend terminal: origin overrides apply only to this process, not .env.
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Port 8001 -FrontendOrigin http://127.0.0.1:5183
```

```powershell
# Frontend terminal:
$env:BACKEND_URL='http://127.0.0.1:8001'
npm.cmd --prefix frontend run dev -- --port 5183 --strictPort
```

In another terminal, keep the production-build preview separate while editing:

```powershell
$env:BACKEND_URL='http://127.0.0.1:8001'
npm.cmd --prefix frontend run preview -- --port 5184 --strictPort
```

Demo passwords remain in ignored local files. The administrator signs into native Statamic;
customers and technicians sign into the Vue application.

Existing local demo accounts include `customer1@erepair.test`,
`technician1@erepair.test` and `admin@erepair.test`. The private demo password is
stored in `.local/demo-password`; do not publish that file or use demo accounts
in production.

## Verification and release

```powershell
node scripts/check-vue-source.mjs
node scripts/typecheck-vue-core.mjs
powershell -ExecutionPolicy Bypass -File scripts/verify-release.ps1 -LiveApi -FrontendOrigin http://127.0.0.1:5183 -BuiltPreviewOrigin http://127.0.0.1:5184 -BackendOrigin http://127.0.0.1:8001
```

The release verifier uses the dedicated `erepair_test` database for backend
integration tests. Live tests use the loopback demonstration backend and leave
labeled repair history; they must never target production.
`FrontendOrigin` is the trusted cookie/CSRF origin for live Vue requests;
`BuiltPreviewOrigin` serves the compiled assets; `BackendOrigin` supplies the
native CMS checks. The preview defaults to the frontend origin when omitted,
preserving setups that already run the compiled preview on their trusted origin.

The production builder forces same-origin API/CMS URLs. Create a source/static
release with `node scripts/prepare-release.mjs` and verify its SHA-256 manifest.
No local environments, database files, uploads, dependencies or React backups
are included. Follow [deployment](docs/deployment.md),
[Render/Vercel hosting](docs/render-vercel.md),
[production readiness](docs/production-readiness.md) and
[browser acceptance](docs/browser-acceptance.md) before public launch.
