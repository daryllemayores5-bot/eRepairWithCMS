# Deployment readiness ? Vue migration, November 11, 2026

## Current build-runtime gate: November 12, 2026

The doctor/release prerequisite now uses the actual frontend Node engine range,
with strict npm installation and a Node 26 version-manager selection. The old
hardcoded minimum incorrectly accepted this laptop's v24.14.0. Its rejection is
now verified before JavaScript release tests begin; do not bypass that failure
or treat older complete-run results as current release clearance. A Node upgrade
could not be downloaded from this environment. All 274 backend tests / 1,343
assertions pass, including 22 new runtime diagnostics; see the latest evidence
in `current-verification.md`. Production host, database, mail, browser/container,
audits and recovery requirements below remain open.

## Whole-system verification: November 12, 2026

The full local release runner with live APIs passes: 248 backend tests / 1,265
assertions, 195 Vue tests, 83 standalone checks, Composer checks, type checking,
production build, five built-preview HTTP stages, the actual repair-to-review
journey and 15 native CMS HTTP stages. A fresh isolated recovery matched 38
tables and 65 files before the live journey; no working database reset occurred.
The journey retained its labeled demo repair and associated history.

**Not cleared for public deployment:** the eight production preflight failures
remain environment/HTTPS/CORS/Sanctum/session/mail/PostgreSQL version/demo data.
Fresh registry audits could not connect; local Node 24.14.0 does not meet the
declared frontend engine range; Docker/Podman are absent; browser startup was
rejected by automatic approval review because sandbox approval is unavailable.
Actual public host, mail, mobile/browser, container and production recovery checks
remain open. See the first section of `current-verification.md` for the evidence
matrix and ignored private log locations. The older results below are historical.

## Latest database and release recheck: November 12, 2026

All 14 migrations are applied; constraint/index checks and the restricted runtime
role pass. An isolated post-Premium backup recovery matched 38 tables and 65
files. All seven existing record fingerprints matched before CMS HTTP testing;
afterward the six business sets remain identical and only the CMS editor's
`last_login` changed, verified against the recovery snapshot. The current
local checks pass 248 backend tests / 1,265 assertions, 185 frontend tests, full
type checking/production build and 15 native CMS HTTP stages. The artifact now
includes the installer-linked database deployment/schema/dictionary documents;
its seven regression tests pass. Evidence and remaining external gates are in
`current-verification.md`. These results do not clear the production host,
PostgreSQL version, HTTPS/mail, physical browser, image build or dependency audit
requirements below.

## Premium subscription follow-up: November 12, 2026

The additive Premium migration is applied locally, with **248 backend tests /
1,265 assertions**, **176 frontend tests**, production type checking/build,
nine artifact/native-CMS tests and database integrity/index checks passing.
The project still has 38 tables; all 14 migrations are applied. Existing core
record fingerprints, Free/Pro plans and payment history were preserved. The
pre-upgrade recovery drill matched 38 tables and 65 files. The existing scheduler
now also activates due scheduled paid-plan changes; request-time timestamps
enforce the entitlement boundary even when cron is late. See
[subscription lifecycle](subscription-revenue.md). These are local checks, not
physical browser or public-host acceptance.

## Database hardening follow-up: November 12, 2026

The complete backend suite passes **216 tests / 1,018 assertions** after the
non-destructive hardening migration. The actual application schema has 38 tables,
35 foreign keys and passing constraint/FK-index checks; 21 business/identity table
fingerprints matched the pre-upgrade backup exactly. Documentation includes an
updated ER diagram and generated schema-only dictionary. Production startup now
also checks schema integrity/indexes and rejects PostgreSQL versions below 16.
The laptop's PostgreSQL 14.5/demo configuration is not a production target. Use
the new [database deployment guide](database-deployment.md), a patched provider
database, verified TLS and an actual target-environment recovery exercise.
These backend changes do not replace the remaining browser/mail/host/audit checks.

## Earlier deployment preparation: subscriptions and background processes

The **complete local release gate now passes in one invocation with exit status
0**: Composer checks, 82 standalone rule/tooling tests, doctor, 203 backend tests /
931 assertions, full type checking, 166 Vue tests, production build, preview
delivery, the real repair-to-review journey and fifteen native CMS HTTP stages.
The corrected runner handles Windows native-command stderr and separate dev/
preview/backend origins. See `.local/deployment-final-release-gate.log` and
`current-verification.md` for evidence. This does not clear the external gates
in the table below.

Source-checkout command for the current isolated local servers:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/verify-release.ps1 -LiveApi -FrontendOrigin http://127.0.0.1:5183 -BuiltPreviewOrigin http://127.0.0.1:5184 -BackendOrigin http://127.0.0.1:8001
```

Live verification uses the existing local demo accounts and leaves labeled
repair/review history. Never run it against production. Keep the Vue dev server,
built preview and scoped backend running on those ports before invoking it.

The host-verifier follow-up additionally passes **43 release/routing/host-fixture
checks**, **15 real native CMS HTTP stages** and five expanded built-preview
HTTP stages. Public probes now include the subscription/analytics APIs and admin
revenue routes, with strict JSON/private-cache denials and projection-asset
delivery. Generated SPA fallback rules exclude private filesystem paths and
static asset misses. Existing generated configurations must be reviewed and
regenerated when adopting the new rule; the helper still refuses overwrites.

The application release is prepared locally, but **public production clearance
is still blocked**. Do not publish the frontend against a missing backend or
change the laptop's development settings to manufacture a preflight pass.

Final local results: **203 backend tests / 931 assertions**, **15 deployment,
artifact and native CMS styling tests**, full frontend type checking/build,
source checks and installed dependency consistency pass. The unchanged Vue UI
suite baseline is 166 tests. All eleven native CMS HTTP checks passed again.
An isolated recovery matched 38 tables and 59 files, preserving the working
database, uploads and subscription history.

The deployment audit found and fixed missing runtime processes. The Render image
now includes Supervisor and pcntl and manages foreground PHP-FPM, Nginx, Laravel's
scheduler and the database queue worker. Startup validates configuration before
migrations, performs full database preflight before any service starts, and checks
Nginx/PHP-FPM configuration. Queue workers run as the application user, have
bounded retries/timeouts, restart automatically and stop with their child process
groups. The application uses the existing database file mirror transaction for
background jobs, including rollback/restoration on a failed job. No subscription
activation or payment behavior was changed by this deployment hardening.

| Requirement | Current evidence / remaining condition |
| --- | --- |
| Application functionality | The subscription, repair and CMS suites pass; final totals are in `current-verification.md` |
| Background persistence | Successful/failed dispatched-job regression tests pass; actual database queue processing is also covered |
| Container configuration | Startup/process supervision fixtures and shell syntax pass; an actual Linux image build remains unverified because Docker/Podman are absent |
| Vercel connection | Existing `erepair-verification` project rechecked: Node 26, no deployment/domains, provider-domain SSO protection enabled; actual public-origin access must be verified |
| Backend/database hosting | Render and Neon plugins exist but are not connected; no production service/database credentials are present |
| Production preflight | Local read-only run correctly fails environment, HTTPS origin, CORS, Sanctum, secure sessions, real mail and production-only data; safe output is in `.local/deployment-readiness-preflight.json` |
| Dependency security | Fresh npm and Composer audits cannot reach their registries; open development-tool advisories are not cleared |
| Physical acceptance | Chrome cannot render in this environment; desktop/mobile/dialog/screen-reader checks remain unverified |
| Actual host acceptance | Requires the real HTTPS backend/origin, restricted PostgreSQL/TLS, verified mail, durable storage and off-host backup restoration |

The full production environment template and deployment instructions are in
`deployment/.env.production.example`, `deployment/INSTALL.md` and
`docs/render-vercel.md`. Keep populated secrets on the destination only. Use a
fresh deployment database; preserve the existing demonstration database and key.
After provisioning, generate Vercel routing using the **actual** backend origin,
run the full production preflight, build/start the container and run the packaged
HTTPS host verifier before promotion. No external deployment, connection change,
domain purchase or paid resource was performed during readiness preparation.

Render Free sleeps after inactivity, so background bookkeeping pauses while the
instance is asleep. Pro expiry is still enforced by timestamps on every request.
Continuously running production background work requires a host that remains on.
Sources: [Render Free limitations](https://render.com/docs/free),
[Laravel scheduling](https://laravel.com/framework/docs/scheduling),
[Supervisor configuration](https://docs.supervisord.org/configuration.html).

Vue is active in frontend/. Full type checking, the original route/authentication
inventory, the production build, static/API delivery and the real repair-to-review
journey pass. Final canonical release-gate evidence is in current-verification.md.
The original React source and installed runtime are retained and excluded from
both Vercel upload and Docker/release contexts.

Local activation is not public production clearance. Browser/mobile visual
acceptance, real target-host HTTPS/mail/storage/recovery, patched runtimes and
fresh dependency-advisory review remain required. Five moderate development-tool
findings are documented in dependency-audit.md. Existing local/demo production
flags were not changed to manufacture a preflight pass. Use a fresh deployment
database and the actual hosting values in deployment/.env.production.example.

The previous hosting preparation and React validation record follows.

## Historical record

# Deployment readiness — November 5, 2026

## November 5 release validation

The full release gate passed on the current source, including **179 backend tests
/ 744 assertions**, **77 frontend tests**, TypeScript/build, all release/configuration
fixtures, built frontend HTTP checks and the actual repair/review journey. Native
Statamic HTTP checks passed separately. Evidence and exact scope are in
[current verification](current-verification.md).

Vercel account access is verified and the local project link is saved, but that
project has no deployment, domain, environment variables or database integration.
Render and Neon connections are not installed/authorized; mail credentials are
absent. Docker and public browser validation cannot run against a nonexistent
host. Fresh dependency audits failed to reach the registries. Public launch is
still blocked by these external requirements; no local production settings or
data were changed to fabricate a pass.

## Latest hosting preparation

The latest backend follow-up passes **179 tests / 744 assertions** and all four
deployment-configuration checks. Docker asset publication preserves the custom
Statamic configuration, and startup now refuses to serve before production
preflight passes. Real HTTP upload/recovery/authorization and transactional
failure tests pass. The startup shell script passes syntax validation; this is
not an actual Docker build. No frontend files changed in this follow-up.

The full automated release gate now passes **176 backend tests / 726 assertions**,
77 frontend tests, TypeScript/build, all rule/configuration/artifact fixtures and
the real repair-to-review journey. Native Statamic HTTP checks passed; a fresh
backup drill restored 34 tables and 53 files. See [current verification](current-verification.md).

The new [Render + Vercel profile](render-vercel.md) includes a backend Dockerfile,
safe startup, production catalog, single-editor provisioning, HTTP password-reset
mail and opt-in bounded PostgreSQL file persistence. These changes do not enable
the profile locally or publish the existing Vercel project. Actual Docker build,
external database/sender configuration, public-host workflows/restarts, fresh
dependency audit and physical browser/mobile acceptance remain required. Render
Free is a capstone/demo option with sleep/quotas, not production-grade hosting.

The following removal results are the previous baseline; the new counts above
include the hosting work.

**eRepair is now a regular online web application. PWA installation is no longer
required and has been removed at the user's request.**

## Latest verification after removal

| Check | Result |
| --- | --- |
| Laravel/PostgreSQL | 158 tests / 672 assertions passed |
| React routes/components | 77 tests in 14 files passed |
| Repair rules and worker-retirement behavior | 25 tests passed |
| Artifact safety | Seven tests passed, including rejection of stale install assets |
| HTTPS host-check fixtures | 14 passed; this is not actual hosting acceptance |
| Installed native Statamic Vue/CSS | Passed |
| TypeScript, normal production build | Passed without PWA tooling |
| Built frontend HTTP | Five stages passed, including absence of installation metadata |
| Real customer-to-technician repair/review workflow | Passed on rerun in 168 seconds |

The first full gate reached the live journey's old 180-second overall limit near
the final steps; its repair was already completed in PostgreSQL. The dedicated
rerun passed all assertions, final review/history/appointment checks and logout.
The complete-journey budget is now five minutes for the constrained laptop;
individual UI waits remain bounded and no assertions were removed. The complete
gate was not rerun again after that targeted successful rerun.

Installation buttons/page/provider, manifest, offline document, PWA icons and
Vite/Workbox installation dependencies are removed. Old /install bookmarks redirect
home. public/sw.js is a retirement-only migration file: it unregisters old workers,
cleans only their scoped static precache, has no fetch handler and never reloads
open forms. Physical migration in an actual browser remains a manual check, not
an OS installation requirement. See web-delivery.md.

The lock graph was reduced from 569 to 252 entries without changing remaining
package versions. npm's offline virtual-tree/root dependency validation passes.
An ordinary offline npm lock update could not fetch an uncached optional WASM
archive, so the existing graph was pruned with the installed npm Arborist and
validated. Fresh downloads/clean installation were not tested in this restricted
session. The old glob/minimatch/deepmerge/Workbox paths are absent; a fresh advisory
report is still required before public security clearance.

## Public launch still pending

Public production deployment is not yet cleared. The last read-only preflight
failed seven local/demo settings: environment/debug, HTTPS origin, CORS, Sanctum
host, secure sessions, actual mail and production data. No local credentials or
demo records were removed to force a pass. Use a fresh production database.

Configure compatible PHP/PostgreSQL hosting, HTTPS, persistent CMS/upload storage,
real password-reset delivery and encrypted off-host recovery. Run the production
preflight and packaged host verifier there. Finish browser/mobile/keyboard/map
acceptance and obtain working advisory reports. Installation as a PWA is not part
of those requirements. A provider address can be used without buying a domain.

The earlier local backup drill matched 32 tables and 43 files; it does not certify
production recovery. Statamic remains the independent single-editor Core setup.
Follow deployment/INSTALL.md, production-readiness.md and browser-acceptance.md.
