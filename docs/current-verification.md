# Current verification ? November 11, 2026

Pro/Premium workshop implementation, November 13, 2026: implemented locally
before publishing. Pro adds PDF quotations/job sheets and reusable repair-note
templates. Premium adds owner-scoped parts inventory, repair-linked deductions,
restocking, low-stock alerts, movement history and date-filtered CSV/PDF business
reports. New dashboard pages and repair-page actions make these tools visible;
Free retains the existing repair workflow and sees upgrade prompts.

Verification: the full backend suite passed 289 tests / 1,896 assertions and
the full frontend suite passed 215 tests. A subsequent exact-decimal edge case
and final fixes passed the 16-test workshop/PDF/concurrency set (449 assertions)
and seven focused Vue tests. The PDF is parsed by an external PDF reader.
Concurrent stock requests cannot overdraw or duplicate deductions. Owner, role,
expiry, scheduled-upgrade, input validation and spreadsheet-injection checks pass.
Type checking and the updated production bundle pass. These JavaScript checks
used installed Node 24.14.0; the release doctor still rejects that runtime, so
they are diagnostics, not supported-runtime release clearance. Remote deployment
must build and verify on the project's configured Node 26 runtime.

Before the local migration, recovery matched 38 tables and 66 files. The additive
migration now creates three workshop tables; local integrity/FK-index checks pass
and the schema dictionary was regenerated. Existing accounts, repair records,
plan prices and purchased subscriptions were not reset. The new tools are not
live until their deployment commit is pushed and both hosts are updated. The
existing hosted mail authorization failure remains separate and unresolved.

Owner-requested hosted samples, November 13, 2026: added three fictional
technicians, four customers and twelve SAMPLE-labeled repair requests to the live
database. The records include all ten repair statuses, 49 status-history rows,
twelve appointments, five estimates, two explicitly fictional reviews and 24
in-app notifications. The public API returns all three sample profiles. No mail
was sent and no real login history was fabricated. Unique random login passwords
are saved privately in `.local/hosted-sample-accounts.json`; all seven password
hashes verify. Reserved sample email addresses cannot receive email resets.

The additive transaction was rehearsed twice on the existing recovery branch;
its second run created no duplicates. Existing live account fingerprints match
exactly. One non-sample request appeared concurrently (none existed at the initial
snapshot); the sample transaction inserts only its own fixed references. The
single CMS administrator was retained. This uses a separate labeled sample set,
not the destructive/fresh-install local DemoSeeder. Evidence is in
`.local/hosted-sample-data-verification.json`; the recovery branch now also holds
the sample rehearsal. Live mail failure remains unresolved as documented in
`mail-verification.md`.

Hosted HTTP/session acceptance, November 13, 2026: the user ran
`node .local/verify-hosted-session.mjs --cms-session` from a network-enabled
terminal. All ten stages pass, including the full anonymous HTTPS verifier,
secure host-only XSRF/session cookies and credentialed CORS, private JSON denials,
native CMS assets, authenticated operations/subscription/revenue pages, separate
CMS/marketplace guards and logout. The saved report has status `passed` at
`2026-11-12T23:48:53.086Z`; no email was requested. This supersedes the earlier
tool-only cookie/denial limitations and failed network attempt below. Actual
browser repair workflows, authorized external reset-mail receipt and independent
database/application-key recovery remain outstanding.

Hosted recovery follow-up, November 13, 2026: a separate Neon branch,
`deployment-recovery-check-20261113` (`br-misty-cake-aom36yt6`), matches the
production branch across 34 non-transient tables. Cache, cache locks, sessions
and queued jobs were excluded from the comparison. All 25 durable files
(10,077 bytes) pass stored size/SHA-256 checks on both branches. Their payloads
were also exported and restored into a new local directory, with every restored
checksum matching. Evidence is retained in
`.local/backups/hosted-recovery-20261113/`. This verifies a provider-side copy
and filesystem restoration, not independent database-backup or key recovery.
The recovery branch remains retained; production connections were unchanged.

`.local/verify-hosted-session.mjs` now wraps the existing full anonymous HTTPS
verifier and optionally checks native CMS login, private operations pages,
independent authentication guards and logout using the privately saved hosted
credentials. Syntax validation passes. Actual execution in the agent session
fails at HTTPS connectivity (`EACCES`) before authentication; it must be run
from a terminal with network access. No email was sent. Full browser repair
workflows, authorized reset-mail/inbox checks and independent database/key
recovery remain outstanding.

Hosted deployment, November 13, 2026 (Asia/Shanghai):
`https://erepairwithcms.vercel.app` now serves the frontend and proxies the live
Render backend. Render deploy `dep-dbr4i8oae00c73eiefa0` is live at source commit
`422ef9f89b16df93e5281c48371f44861da567e8`. Configuration and full database
production preflight pass. Neon has 14 applied migrations, 38 tables, one
administrator and one independent CMS editor. The fresh hosted database contains
no local demonstration technicians.

The public health endpoint returns HTTP 200 with application `ok` and database
`connected`. Technician discovery, the SPA deep link and native CMS sign-in
respond successfully; sampled native JS/CSS and custom CMS CSS return HTTP 200
with correct MIME types. Sanctum returns HTTP 204 with a secure HttpOnly SameSite
cookie observed. The connected fetch helper does not expose every Set-Cookie
header and classified a private-route 401 as share-link authentication failure;
the complete cookie/denial checks are therefore not certified by this smoke test.

Build commands now use transient cache/session/queue drivers before database
initialization. Hosted migrations use Neon's direct endpoint with verified TLS;
DB_URL overrides DB_HOST and was corrected accordingly. Explicit optional libpq
client-certificate paths prevent the queue service account from probing /root.
The one-time bootstrap password has been cleared from Render. PHP-FPM, Nginx,
scheduler and queue entered RUNNING; two scheduled CMS jobs completed after the
last deploy, with no repeated certificate failure on the new instance.

Authenticated browser workflows, external password-reset delivery and a hosted
backup/recovery exercise remain acceptance work. Local network restrictions still
prevent the standalone HTTPS verifier from reaching the host, so connected tools
were used for the checks above. Private deployment evidence is in
`.local/eRepairWithCMS-deployment.json`. Earlier unconnected-hosting statements
below are historical.

Hosted staging bootstrap (November 12, 2026): the connected Vercel account was
queried again. `erepair-verification` is configured for Node 26.x, is not live,
has no latest deployment or domains, and retains provider-domain SSO protection.
Render and Neon discovery confirms neither integration is installed/connected;
their account authorization is required before backend/database provisioning.
Suggestions were offered; suggestions alone do not establish account access.
No cloud resource was provisioned, paid plan purchased or access protection
changed. The frontend was not uploaded against an absent backend.

An ignored `.local/staging/backend.env` draft now contains a separate fresh
staging encryption key/admin password and the existing Render security/durable
storage defaults. Existing drafts are preserved on rerun. No secret values were
printed or transferred, and the working demo environment/database was untouched.
This draft is deliberately incomplete: real HTTPS origins, provider database and
mail credentials and the intended administrator identity remain `CHANGE_ME`.
It is not a production-preflight pass or permission to publish. The next hosted
step requires Render/Neon account access; the laptop's compatible Node upgrade
and the previously documented acceptance gates also remain outstanding.

Post-redesign deployment assessment (November 12, 2026): **not cleared for public
production deployment**. Fresh doctor output rejects Node v24.14.0 against the
declared frontend engine range. Production preflight still fails eight checks:
environment, HTTPS origin, CORS, Sanctum, secure sessions, real mail, PostgreSQL
version and demo data. The working demo was not reconfigured to fabricate passes.
Safe reports: `.local/final-readiness-doctor.log` and
`.local/final-readiness-production.json`.

Fresh database integrity/index checks pass and all 14 migrations are applied.
The latest isolated recovery matched **38 tables and 66 content/upload files**;
private evidence is `.local/backups/restore-check-20261112-070652-4862b420`.
All five built-preview HTTP stages pass against the current production assets,
including PostgreSQL-backed health and private-cache denials. The existing
post-redesign package passes its 293-file SHA-256 integrity manifest. No code or
dependency changed since the latest **274 backend / 209 frontend** test baselines;
these full suites were not repeated during this configuration/recovery recheck.

Fresh npm and Composer audits again could not connect to their registries
(`.local/final-readiness-npm-audit.log`,
`.local/final-readiness-composer-audit.log`); no advisory clearance is claimed.
Docker/Podman are absent and the previous browser approval restriction remains;
actual image-build and physical browser/mobile acceptance are unverified. Actual
target-host HTTPS/mail delivery and production recovery still require acceptance
on that environment. Local recovery and fixture tests do not certify a remote
production host. The original development app/database remain running; only the
temporary built-preview process for this recheck was stopped.

Reference-inspired UI redesign (November 12, 2026): the landing page and shared
Vue styling now follow the supplied blue/white repair reference. The page has
an optimized illustrative hero photograph, catalog-linked popular repairs,
location/device/problem search, benefit cards, real available technicians, repair
steps, a decorative workspace/phone preview, customer/technician CTAs, actual
public customer reviews, services, existing plan comparison and editable CMS
FAQs/updates. Shared navigation, buttons, fields, cards, auth, dashboard/sidebar,
tables and footer carry the palette across the active system. Scoped CMS
operations styles were updated and published with the existing asset mechanism.

Layouts include phone/tablet/desktop breakpoints; on phones the search precedes
the decorative photo. The hero is a locally hosted 121,964-byte WebP generated
for this project, not a technician identity. Reviews use existing public APIs;
no customer feedback, staff identities or business counts were fabricated.

All **209 frontend tests across 23 files**, full type checking/production build,
**nine native CMS/artifact checks**, **15 live native CMS HTTP stages** and all
**69 preserved React reference checks** pass. Six new landing regressions cover
search submission, dependent device/problem options, real feedback order/rating,
empty feedback, retry and CMS editability. The existing CMS stylesheet test now
checks the requested cobalt/9px-control design contract. The running site serves
the hero with `image/webp`, the page/module and its PostgreSQL-backed API with
HTTP 200. Logs: `.local/reference-ui-full-frontend.log`,
`.local/reference-ui-build.log`, `.local/reference-ui-cms-http.log`.

No backend business logic, migration or subscription history changed; the prior
274-test / 1,343-assertion backend baseline was not rerun for this UI/CSS pass.
The CMS HTTP login updates the administrator's login timestamp. A screenshot
browser launch was rejected by automatic approval review because sandbox
approval is unavailable. No physical browser/mobile screenshots or viewport-fit
acceptance are claimed. The source-build Node requirement and previously listed
production-host/audit/container/recovery gates remain open. The local app remains
running on 5173 with Laravel/CMS on 8000.

Build-runtime preflight correction (November 12, 2026): `erepair:doctor` no longer
uses the stale Node 22.12 minimum. It reads `engines.node` from the frontend
manifest, checks stable CLI versions against that declared range, and fails
closed for missing/invalid requirements, prereleases and unavailable processes.
It uses the existing runtime `composer/semver` dependency required by Statamic;
no package version or lockfile changed. Process probes remain bounded to ten
seconds and failures do not expose process exception details.

The release runner invokes this check before any JavaScript verification tools.
`frontend/.npmrc` enforces compatible engines for installation (also verified
with the repository-root `--prefix frontend` command), and `frontend/.nvmrc`
selects Node 26. The actual laptop's v24.14.0 is now correctly rejected. A bounded
attempt to reach the official Node 26 checksum/download host failed; no runtime
installation or global PATH change was performed.

All **274 backend tests / 1,343 assertions** pass, including **22 new diagnostic
cases** covering patch boundaries, excluded major versions, prereleases, changed
or missing requirements, command failures and safe error reporting. Scoped Pint
passes. The real release invocation correctly stops at the Node prerequisite
before JavaScript suites. Evidence: `.local/runtime-full-backend.log` and
`.local/runtime-release-preflight.log`. The preceding 203-test frontend/build
baseline is unchanged; those suites were not rerun for this backend/tooling-only
change. No database migration or working-record change occurred. Prior full
release successes below predate this correction and do not clear the runtime
requirement. Public deployment, registry audits, host/mail/browser/container and
production recovery acceptance remain open.

Public technician-plan comparison (November 12, 2026): the landing page now has
a **For Technicians** section and **View technician plans** anchor. Responsive
comparison cards load active plan prices, durations, descriptions and benefits
from the new read-only `GET /api/v1/subscription-plans` endpoint. Guests can
register as technicians; existing technicians can open their subscription
workspace. Customer/admin sessions are not offered technician checkout. Notices
explain separate repair estimates, customer access without subscriptions,
administrator verification, subscription-only badges and DEMO payment approval.
The endpoint returns an explicit public field allowlist and creates no purchase.

Validation: **252 backend tests / 1,292 assertions**, **203 frontend tests across
22 files**, production type checking/build and scoped Pint all pass. Four new
PostgreSQL tests cover anonymous access/public fields, actual updated prices and
durations, inactive-plan preservation and an empty catalog; eight additional
frontend cases cover the comparison, role-specific navigation, retry/empty states
and its landing-page integration. The new API also passed through the running
Vue server's real same-origin proxy with Free/Pro/Premium at 0/199/399 pesos.
Evidence: `.local/public-plans-full-backend.log`,
`.local/public-plans-full-frontend.log`, `.local/public-plans-build.log`.
No migration, working payment, approval or subscription-history change was made.
Browser/mobile visual acceptance and the previously documented public-deployment
gates remain open. The existing local development servers were left running.

Whole-system release verification (November 12, 2026): the complete local release
runner reached its successful completion marker with live API checks enabled.
Evidence: `.local/whole-system-release-check.log`.

| Boundary | Result and evidence |
| --- | --- |
| Backend and real PostgreSQL integration | 248 tests / 1,265 assertions passed against guarded disposable `erepair_test`, including authentication/authorization, repair lifecycle, Premium plan changes, payment/revenue consistency, concurrency, files, CMS and loopback SMTP |
| Vue interactions | 195 tests across 21 files passed; full TypeScript checking and production build passed |
| Release and routing tools | All 83 standalone rule, artifact, host, routing, build and CMS-style tests passed; Composer manifest/platform checks passed |
| Built frontend to API | All five HTTP stages passed: assets, deep links, regular web delivery, PostgreSQL health and uncached authenticated-endpoint denials |
| Real Vue to Laravel to working database | The live repair journey passed in 102.45 seconds, including discovery, photo request, technician acceptance, appointment, diagnosis/estimate, customer approval, pickup/completion, review and final logout |
| Native Statamic Core | All 15 HTTP stages passed: login/static assets, persisted reports, subscription/revenue administration, public content projection, participant denials and logout |
| Database schema and recovery | All 14 migrations applied and integrity checks pass. The pre-live-test isolated drill matched 38 tables and 65 content/upload files; private recovery evidence is `.local/backups/restore-check-20261112-040958-7a34e678` |

No database reset or demo reseeding occurred. The live journey intentionally
retained its uniquely labeled completed demo repair and related photo/history,
appointment, estimate, notification and review records. Integration-test payments
and approvals ran in the disposable test database, not the working application.
The live CMS login updates its editor's login timestamp.

**Public deployment is not cleared.** Read-only production preflight still fails
eight checks: environment, HTTPS origin, CORS, Sanctum, secure sessions, real mail,
PostgreSQL version and production-only data. Its safe named report is
`.local/whole-system-production-preflight.json`. Use a fresh restricted production
database on patched PostgreSQL 16+, actual HTTPS/mail configuration, durable
storage and target-environment recovery/host verification. Preserve this demo
database and stable application key; do not change local flags to fabricate a pass.

Local Node 24.14.0 is outside the frontend's declared engine range even though
checks pass; use a patched supported release satisfying
`^22.22.2 || ^24.15.0 || >=26.0.0` for installation/builds. Fresh npm and Composer
audits again failed to reach their registries; neither is a security clearance
(`.local/whole-system-npm-audit.log`, `.local/whole-system-composer-audit.log`).
Docker/Podman remain unavailable, so an actual container build is unverified.
Automatic approval review rejected starting the fresh browser probe because it
requires sandbox approval, which is unavailable in this session. No current
browser screenshots, responsive/mobile acceptance or real-provider email delivery
are claimed. These external acceptance gates remain in the deployment/browser
instructions.

Technician finder recovery update (November 12, 2026): approximate location now
shows progress and prevents duplicate requests. Search changes/resets and
unmounting invalidate old callbacks; removing location preserves other criteria
and comparisons while resetting pagination and distance sorting as needed.
Failed background refreshes retain the last successful results with a warning
and retry action. Initial errors use an honest unavailable state. Map tiles have
a retry action that preserves markers/view, and recreated maps clear stale tile
errors. Unmounted map refresh work cannot create another Leaflet instance.

All **195 frontend tests across 21 files** and the full production type
checking/build pass, including ten additional regression cases. Evidence is in
`.local/finder-full-frontend-tests.log` and `.local/finder-production-build.log`.
No backend, schema or business data changed in this pass; the previous 248-test
backend verification remains the backend baseline. Physical browser/map-tile
acceptance remains pending; DOM/Leaflet-engine tests do not establish visual fit
or real geolocation permissions. See the updated finder/browser instructions.

Database and release recheck (November 12, 2026): all **14 migrations** are
applied. Read-only constraint, foreign-key index, subscription uniqueness and
restricted database-role checks pass. All seven pre-upgrade business/identity
record fingerprints matched before the CMS HTTP check; afterward all six
business record sets still match, including subscriptions and payments. A
field-by-field comparison with the recovery snapshot confirms the CMS check
changed only the editor's `last_login`, with identity, credentials, permissions
and preferences preserved. Free/Pro/Premium are active at 0/199/399 pesos.

The post-Premium recovery drill restored **38 tables and 65 content/upload
files** with matching fingerprints/hashes in an isolated temporary database.
The working database was preserved. The local release checks pass **248 backend
tests / 1,265 assertions**, **185 frontend tests**, full type checking and the
production build. All **15 native CMS HTTP checks** pass against the database.
The release now includes the database deployment guide, schema and data
dictionary referenced by the installer; all seven artifact tests pass after
that packaging fix. Evidence: `.local/release-verification-latest.log` and
`.local/backups/restore-check-20261112-024532-f4a111fd` (private recovery data).

Public deployment remains uncleared: this is a local/demo PostgreSQL 14.5
environment. The target needs patched PostgreSQL 16+, HTTPS origins, secure
session settings, real mail, production credentials/data and a target recovery
drill. Fresh npm/Composer audits again could not reach their registries; known
development-tool findings remain open. Docker/Podman are unavailable and the
previous physical-browser limitation remains unresolved. No production
configuration, public launch, dependency-audit clearance or container build is
claimed. See [database deployment](database-deployment.md) and
[deployment readiness](deployment-readiness.md).

Responsive UI and clarity update (November 12, 2026): the dashboard header/search
now stacks on phones with a compact workspace menu. Repair lists and subscription
history offer mobile cards alongside their larger-screen tables. Comparison
cards, pagination, long names/filenames, form fields and action buttons have
bounded/wrapping layouts; the subscription dialog scrolls on short viewports.
Scroll regions are keyboard-focusable. Common button variants have 44px minimum
touch targets; their existing merge/slot regression reflects the new size.

Menu Escape handling returns focus to the toggle. Repair steps focus invalid
controls and new headings, technician search can retry without clearing form
data, and zero reviews are labeled honestly. Sign-in and problem-description
labels remain stable when validation descriptions appear. Read-notification
actions show pending state, prevent repeated calls and recover after failure.
Subscription history distinguishes replaced renewals, unactivated/rejected
requests and scheduled/current/expired access; Free after expiration is clearer.

All **185 frontend tests across 20 files**, full production type checking/build,
**nine CMS/artifact checks**, source checks for **52 Vue components / 50 TypeScript
files** and all 69 React reference checks passed. New interaction regressions
cover navigation/focus, validation, retry preservation, participant links in
repair cards, readable subscription access states and notification progress.
Backend/schema and existing records were not changed or retested in this UI pass;
the prior backend baseline remains 248 tests / 1,265 assertions.

Physical browser acceptance is still **pending**. A fresh isolated headless Edge
probe exited before rendering with `GPU process isn't usable`; Chrome produced
no screenshot. No viewport screenshots or real-phone acceptance are claimed and
no browser/OS security was disabled. DOM assertions do not establish visual fit.
See [UI design](ui-design.md) and the expanded [browser checklist](browser-acceptance.md).

Premium subscription update (November 12, 2026): Free, Pro (₱199/30 days) and
Premium Technician (₱399/30 days) now share the existing simulated-payment and
native Statamic approval workflow. The additive migration was applied locally;
a second migration run reported no changes. Premium insertion is idempotent and
preserves custom plan definitions. Seven existing record sets exactly matched
their pre-upgrade fingerprints: Free/Pro plans, subscriptions, DEMO payments,
technician profiles, marketplace users, CMS users and repairs. No existing user
or payment history was replaced, and no new working-database payments were made.

Cross-tier changes are approved into a scheduled period after current paid time;
same-tier renewal keeps the existing remaining-time policy. Profile locks and
pending/active/scheduled unique indexes protect concurrent requests/approvals.
Timestamp access handles late scheduler execution without granting future or
expired benefits. Premium promotion breaks relevance ties ahead of Pro/Free and
cannot bypass device/location filters, availability, sorting or verification.
Public cards/profiles/comparison use explicit paid-tier badges. Pro analytics
remain available, with Premium-only 7/30/90-day views/inquiries and prior-period
statistics from actual tracked records. No visit-to-inquiry conversion claim is
made because those records are not individually linked. Native CMS reports use
actual approved DEMO amounts, separate three tiers, show scheduled periods, and
project either configured paid plan.

Validation passed: **248 backend tests / 1,265 assertions**, including **20 Premium
cases** and two real multi-process race tests; **176 frontend tests across 18
files**; full production type checking/build; **nine artifact/native-CMS tests**;
scoped Pint; source checks for 51 Vue components/48 TypeScript files; and all 69
React reference checks. A pre-migration isolated recovery matched **38 tables and
65 content/upload files**. Post-migration database integrity/index checks and
the installation doctor pass; all 14 migrations are applied. The schema-only
dictionary includes the new scheduled state/index. Physical browser/mobile and
public deployment acceptance remain open. See [subscription policy and setup](subscription-revenue.md).

Workspace cleanup (November 12, 2026): the redundant installed React runtime,
16 extracted release-verification copies, the unused root Vite cache and two
temporary diagnostic/download targets were moved into ignored `.local/retired/`.
All 69 legacy source files matched the preserved React reference before moving;
its separate local environment was also copied and checksum-verified in ignored
local storage. `frontend-react-reference/` remains required by active source and
form-schema regression checks. Active Vue/Laravel source, installed dependencies,
database, uploads, recovery backups and release archives were preserved.
This is reversible archiving: automatic approval policy rejected permanent folder
deletion, so approximately 261 MB were consolidated without reclaiming disk space.
All 172 frontend tests, source/reference checks, production type checking/build
and seven release-artifact tests passed after archiving. README and the current migration record reflect the
new runtime archive location.

Automatic technician map update (November 12, 2026): approval now assigns missing
city-level coordinates automatically, preserves valid manual points and refuses
unresolved approvals with actionable validation. Approved profile city/province
edits replace coordinates atomically; unresolved edits retain the prior profile.
Pending/rejected profiles stay hidden. Map view refreshes its filtered, paginated
results every 30 seconds; List view does not poll. The bundled catalog contains
315 approximate location references, with cached, bounded Open-Meteo lookup for
unlisted towns. No participant names or private addresses are sent to that service.

All **227 backend tests / 1,071 assertions** and **172 frontend tests across 18
files** passed, as did production type checking/build, scoped Pint, source checks
and native CMS styling compilation. Eleven new backend tests cover approval to
public finder/profile coordinates, manual-pin preservation, unresolved approval,
rejection, city changes, backfill, aliases, lookup matching/caching, ambiguity and
provider failures. One frontend regression covers polling only in Map view.
After adding the final offline Pola reference, the affected backend suite passed
again (**11 tests / 54 assertions**). The local backfill populated **one** existing
approved profile; a subsequent read-only query found no remaining active approved
profiles with missing coordinates. Existing valid points were preserved; no
schema or credentials changed. All 69 React reference files still match.
Provider HTTP behavior is tested with fakes; a live request to the external
geocoding endpoint could not be verified from this environment. Bundled-catalog
resolution works offline. Physical map/browser acceptance remains open. See
[technician finder](technician-finder.md) for attribution and provider configuration.

Technician comparison update (November 12, 2026): directory selections now use
the URL as their source of truth. Selecting/deselecting or clearing profiles
updates the current history entry; applying/resetting filters and changing result
pages preserve the selection. Reloaded/shared directory URLs restore selected
cards, and back/forward navigation updates them without requiring a remount.
The `compare` parameter remains excluded from API filters and query cache keys.
Two new regression tests reproduced missing URL persistence and stale selection
after navigation before the fix. The complete frontend suite passed **171 tests
across 18 files** with one worker, and production type checking/build passed.
Source checks passed for 50 Vue components and 48 TypeScript files; all 69
preserved React reference files still match. Backend/schema were unchanged and
were not retested in this update. Physical browser acceptance remains open.

Database hardening update (November 12, 2026): all **216 backend tests / 1,018
assertions** passed. Migration 13 adds 19 supporting indexes, two composite
ownership foreign keys/unique keys and stricter state/date checks without adding
tables or rewriting records. Actual local schema checks pass with **38 tables
and 35 foreign keys**. All **21 business/identity tables** exactly matched the
verified pre-migration row fingerprints. The initial backup/restore drill
matched 38 tables and 65 content/upload files. Live health, finder, lookups and
content endpoints returned HTTP 200 after migration. A second restore drill
against the hardened schema also matched all 38 tables and 65 files, and all
15 native CMS HTTP checks passed. Scoped Pint checks passed.
The first full run exposed two outdated production-check mocks; updated
query-specific fixtures and the complete rerun passed.

The schema, ER diagram and metadata-only data dictionary now include current
subscriptions, tracking, Core identities and persistent file tables. New commands
`erepair:database-check` and `erepair:database-schema` inspect/export metadata
without application records. Production preflight adds integrity/index checks
and requires PostgreSQL 16+. It correctly rejects this laptop's PostgreSQL 14.5
demo runtime and other local-only configuration; no production clearance or
public deployment is claimed. Target-provider patch/TLS/restore acceptance remains
required. See [database deployment](database-deployment.md).

Native CMS services update: a Core-compatible Repair Services collection and
validated blueprint now manage published homepage service guides. The Operations
page links to services, pages, announcements, FAQs and homepage globals. The full
backend suite passed **208 tests / 984 assertions**, and the full frontend suite
passed **169 tests across 18 files**. Native service creation, editing, publication,
unpublication, validation, ordering and access restrictions are covered. Source
checks passed for **50 Vue components and 48 TypeScript files**. The live content
API returned three published service guides and existing editorial fields; all
**15 native CMS HTTP checks** passed. The production type check/build and five
built-preview HTTP stages also passed. The release package was regenerated and
checksum-verified with the new collection, blueprint and service content.
The initial new homepage integration test
hit its default 5-second timeout; it now uses the existing route suite's 15-second
limit, and the full rerun passed. Physical browser/mobile acceptance remains
outstanding. See [CMS services](cms-services.md).

Subscription presentation update (after the complete gate below): My Subscription
now uses standard upgrade, confirmation, renewal and history language, with a
clear no-charge notice. Stable `SUB-` request references preserve original audit
references; admin search accepts both. The additive default-description migration
was applied locally and preserves customized descriptions. The updated backend
subscription suite passed **22 tests / 182 assertions**, including concurrent
approvals. The production type check/build, source checks, scoped Pint checks and
all **five built-preview HTTP stages** and **15 native CMS HTTP stages** passed.
The full frontend suite passed **166 tests across 17 files** with one worker,
including request history without DEMO wording. The first parallel run had one
5-second timeout (165 passed); the isolated rerun passed without increasing the
test timeout. The updated release package was regenerated and checksum-verified.
No payment gateway or actual billing was added.

Previous complete local release gate: **exit status 0**. One invocation passed
Composer validation/platform checks, **82 standalone rule/tooling tests**, the
installation doctor, **203 backend tests / 931 assertions**, full Vue type
checking, **166 Vue tests**, the production build, five built-preview HTTP
stages, the real Vue repair/photo/appointment/estimate/approval/completion/review/
logout journey and **15 native CMS HTTP stages**. Evidence is in ignored
`.local/deployment-final-release-gate.log`. This is local release evidence;
public hosting, audits, real mail, container build and browser acceptance remain
unverified. No external deployment was performed.

Latest host-verifier follow-up: **43 routing, HTTPS host-fixture, packaging and
native CMS styling tests** pass. The expanded real CMS HTTP check passed **15
stages**, and all five built-preview HTTP stages pass with the added subscription
deep link and private API probes. Application code/schema was unchanged; the
last full application baselines remain 203 backend tests / 931 assertions and
166 Vue tests. Actual public-host/browser acceptance remains unverified.

Latest deployment preparation: **203 backend tests / 931 assertions** and **15
deployment/artifact/native CMS styling tests** pass. Full Vue type checking,
production compilation, installed dependency/manifest consistency and source
checks pass; the unchanged application UI's full suite baseline is **166 tests**.
All eleven native CMS HTTP checks passed again. A fresh isolated recovery matched
**38 tables and 59 editorial/private/public files**. Public production clearance
is still blocked by external hosting/mail/database configuration, an actual
container build, dependency-advisory access and physical browser/host acceptance.
See `deployment-readiness.md` for the current gate status.

Prior subscription completion result: **199 backend tests / 916 assertions**,
**166 Vue tests**, full type checking, production compilation, source checks,
five production-preview HTTP stages and eight packaging/CMS styling tests pass.
This includes real multi-process PostgreSQL checkout/approval/renewal races.
The existing subscription and repair live HTTP results below remain valid;
this final hardening run did not add working-database payments. Physical browser,
mobile and screen-reader acceptance remains unverified because Chrome's GPU
subprocess fails before rendering.

Vue is now the canonical frontend in frontend/. The original React source and
installed runtime are preserved in frontend-react-reference/ and
frontend-react-runtime/. Their 69 reference files match, and the active
lockfile contains no React-only packages.

The actual Vue integration dependencies are installed and locked. Full Vue type
checking, 143 route/component/authentication tests and the production build passed.
Built-preview HTTP checks passed on port 5183. The complete real Vue-to-Laravel
repair/photo/appointment/estimate/approval/completion/review/logout journey passed
in 194 seconds, with no API/auth/query mocks. This is DOM/API acceptance; it does
not certify physical browser layout or actual external map tiles.

The earlier canonical release gate completed all checks: 179 backend tests / 744
assertions, 130 Vue tests, production compilation, five built-preview HTTP stages
and the real live workflow (217.31 seconds overall, 207.91 seconds in the final test).
All eleven native CMS HTTP stages also passed. Evidence is in
.local/nov11-vue-release-gate-clean.log and .local/nov11-vue-cms-http.log.
The first log's outer PowerShell wrapper treated Composer's informational stderr
as a NativeCommandError despite every check completing. The clean direct-process
capture reran the complete gate successfully with exit status 0.
The backend implementation and schema were not changed. Live acceptance leaves labeled
demonstration history and preserves existing records.

Port 5173 belongs to another application. The isolated Vue preview uses
http://127.0.0.1:5183 and a scoped Laravel development process on port 8001.
Original environment files were preserved; process-local origin settings keep
this profile separate. Public hosting was not changed.

Edge and Chrome headless attempts reached DevTools but failed because their GPU
processes were denied by the environment. No browser/OS security was disabled. The user
installation reported five moderate development-tool findings; see dependency-audit.md.
Fresh agent-side advisory access still fails, so no clean audit is claimed.

The read-only production preflight connects successfully to the database but
correctly reports seven unmet local/demo configuration conditions: production
environment, HTTPS origin, CORS, Sanctum host, secure sessions, real mail and
production-only data. Evidence: .local/nov11-vue-production-check.json. Existing
local settings and demo data were preserved; this is not public-host clearance.

Installed: Vue 3.5.45, Vue Router 4.6.4, Pinia 3.0.4, Vue Query 5.107.1,
TypeScript 5.9.3, Vite 8.3.7, Tailwind 4.3.5 and Vitest 4.1.12.

## Follow-up completion checks

The setup path exposed a missing `test:rules` npm command in the Vue manifest.
Its original command has been restored and all 25 rule/worker tests pass through
that exact npm entry point. All eight original npm commands remain available;
every npm run command used by setup/release scripts resolves. Installed top-level
dependencies and the manifest/lockfile dependency ranges agree. Vue source checks
also pass (47 SFCs, 36 TypeScript files, eight unchanged shared files).

The running Vue app and its PostgreSQL-backed health endpoint both returned HTTP
200. A final Chrome attempt with software rasterization disabled still failed
with a denied GPU subprocess; `.local/nov11-browser-cpu-check.log` records this.
No browser security was disabled. Physical rendering remains unverified.

The supplied Nginx routing test was attempted, but Nginx was denied access to its
generated configuration file before servers could start. This is not a passed
routing test or evidence of an application syntax failure. Evidence:
`.local/nov11-vue-nginx-delivery.log`. The actual target-host Nginx/PHP-FPM check
remains required. The helper now reports configuration-check failures without
mislabeling path/permission failures as invalid configuration syntax.

Registry audit access still fails and patched advisory packages are not cached;
see dependency-audit.md. Public hosting credentials/origins remain unavailable.
No external deployment, OS security change, reseeding or schema change was made.

## Navigation parity correction

Vue Router's route-record active matching differs from the original navigation's
path matching when list and detail pages are sibling records. The migrated link
failed to highlight Find technicians on technician profiles and Repair requests
on repair details/new requests. Six failures were reproduced in thirteen new
tests using a real Vue memory router. NavLink now resolves its destination and
matches path segment boundaries, preserving the original case-insensitive
matching, trailing-slash behavior, root exception and exact dashboard overview.
Both the existing selected classes and aria-current use that result. Vue Router
still handles navigation, modified clicks and actual hrefs.

All thirteen navigation tests now pass, including named destinations, query/hash
handling, reactive transitions and forwarded click handlers. The full Vue suite
passes 143 tests in ten files; full SFC type checking and the production build
pass. All five built-preview delivery checks also pass, including PostgreSQL
connectivity and private response cache headers. Source/reference checks pass.
The check summary is in `.local/nov11-navigation-checks.json`.
The backend and shared business files
were unchanged by this correction; their earlier live/CMS/backend results above
were not rerun or relabeled as new results.

## Asynchronous directory filter correction

The interrupted directory-filter follow-up is complete. Initial select values
from the directory URL now wait for asynchronously loaded lookup options. A user
selection made during loading, including an explicit empty selection, takes
precedence. Pending initialization stops after the option is applied or the field
is edited, and event listeners are removed when the field unmounts.

Five directive regression tests cover delayed options, submission values and
preserving edits. A directory route regression covers device type, repair problem
and specialization surviving lookup loading and form submission. The full Vue
suite passes **149 tests in eleven files**. Full Vue type checking, the production build and source checks
also pass (47 SFCs, 37 TypeScript files and eight unchanged shared files).
These frontend checks do not establish physical browser or public-host acceptance.

## Comparison navigation follow-up

The Vue comparison page now displays supported devices and deduplicated service
locations, and retries individual failed profiles without reloading successful
ones. Directory filters and pagination travel to comparison and return with the
selected technician IDs. Selection metadata is excluded from directory API
requests; both pages normalize the selection to three unique positive safe
integer IDs. Technician profile query keys retain their existing string-ID form.

The targeted route/comparison run passed 39 tests in two files, including failure
recovery and the search-to-comparison-to-search journey using Vue Router.
This is component/router coverage, not physical browser acceptance.

## Shared form accessibility follow-up

Shared repair fields now use explicit, unique label/control associations and
connect validation messages with `aria-describedby` and `aria-invalid`. Error
text sits outside the label so validation does not change a field's accessible
name. Corrected submissions remove the error association and invalid state.

Invalid validated-form submissions wait for Vue to render the descriptions before
focusing the first enabled invalid input, select or textarea in display order.
Hidden and disabled inputs are skipped. Three new DOM regressions cover error
descriptions and correction, repeated field names and focus-time descriptions;
the existing focus test now waits for the rendering boundary.

The full Vue suite passed **156 tests in fifteen files**. Full type checking,
the production build and Vue source checks passed. Actual
browser and screen-reader acceptance remains pending; DOM assertions do not
establish assistive-technology announcements or physical rendering.

## Subscription and simulated revenue addition

The additive subscription migration was applied to the existing local `erepair`
database. It creates four new tables and the Free/Pro plan definitions without
replacing users, repairs, CMS content or environment settings. See
[subscription implementation and capstone guide](subscription-revenue.md) for
the file inventory, routes, commands, demo scenarios and lifecycle semantics.

Verified after implementation:

- Full guarded PostgreSQL backend suite: **196 tests / 883 assertions**, all
  passed. This includes 17 subscription regressions and all existing tests.
- Full Vue suite: **164 tests in seventeen files**, all passed. Eight added tests
  cover the subscription page/badge and the actual admin projection script.
- Full Vue type checking and the production build passed. Source checks passed:
  49 SFCs, 46 TypeScript files, eight unchanged shared reference files.
- The real subscription HTTP journey passed with actual cookies, CSRF, native
  CMS administrator approval, persisted payments and database-derived revenue.
  Duplicate requests/approval, unauthorized approval, Pro profile/analytics,
  safe local expiration, retained revenue and approved renewal were verified.
- That journey left **two approved DEMO payments of ₱199 each**, totaling
  **₱398 simulated revenue**, on `technician1@erepair.test`. The first cycle is
  expired and the second is active. They are labeled DEMO records, not money
  processed or command-generated seed samples. Existing users/repairs remain.
- All **eleven native CMS HTTP checks** passed after the addition.
- The existing real Vue-to-Laravel repair/photo/appointment/estimate/approval/
  completion/review/logout workflow passed again (157.38 seconds overall).
- Route and schedule inspection confirmed the new protected endpoints and the
  once-per-minute expiration command. Run Laravel's scheduler worker/cron for
  unattended expiration; access checks also enforce timestamps without it.
- All eight release-artifact/native CMS styling tests passed. All 69 preserved
  React reference files matched. Five production-preview HTTP delivery stages
  passed on port 5184 (port 5183 remains the active development server).
- A 274-file source/static release package passed SHA-256 manifest verification
  in `.local/releases/erepair-2026-11-11T08-38-49-129Z`. This is a local artifact,
  not a public deployment. The initial built-delivery check targeted the dev
  server, which correctly lacked compiled asset paths; it was rerun successfully
  against the actual production preview.

No payment provider, external payment confirmation, paid CMS feature or chart
package was added. Charts use native SVG and have readable data equivalents.
Revenue uses decimal sums/integer centavos and Philippine calendar boundaries;
projections remain separate from approved DEMO payments. The backend log tail
showed the environment's existing unavailable Statamic Outpost debug messages;
no new subscription application exception was observed during live checks.
Physical desktop/mobile rendering, dialog keyboard and screen-reader acceptance
remain unverified. No public deployment or real payment processing is claimed.

The earlier React verification record follows for historical context.

## Final subscription completion audit

The follow-up closes stale entitlement caches and partial demo setup:

- When subscription polling observes activation, renewal or expiration, shared
  technician query caches are invalidated. Expiration cancels/removes cached Pro
  analytics. Two new Vue regressions cover those transitions and confirmation
  price stability while the configured plan price changes.
- Local sample setup now runs in one transaction under the technician lock. A
  deliberate interruption test proves all sample cycles, payments and associated
  audit entries roll back. Expired samples also have historically consistent
  request/approval/start dates. Demo expiration rechecks the current cycle under
  the lock, preserving a cycle replaced by concurrent renewal.
- A guarded concurrency test starts independent PHP/PostgreSQL processes behind
  a common readiness barrier. Simultaneous same-key requests create one payment;
  competing different-key renewals create one pending request; simultaneous
  approvals grant each 30-day period once and total exactly ₱398 for two approved
  periods. Only committed fixture records in `erepair_test` are created/removed;
  the working database and its demo payments are untouched.
- Full final results: **199 backend tests / 916 assertions**, **166 Vue tests in
  seventeen files**, full type checking, production build, 49 SFC/46 TypeScript
  source checks, all eight artifact/CMS styling tests and all five built-preview
  HTTP stages passed. All 69 preserved React reference files still match.

A fresh standard headless Chrome attempt used a new workspace profile and left
browser security enabled. It exited with `GPU process isn't usable` after denied
GPU subprocesses; no screenshot was produced. Browser/mobile/native-dialog and
screen-reader acceptance cannot be claimed from this environment. All requested
subscription/revenue implementation work is complete; these physical acceptance
checks and running the scheduler/host configuration remain operational steps.

## Complete release runner correction

The Windows release runner now distinguishes the trusted live Vue origin,
compiled-preview origin and native backend origin. Existing same-origin preview
setups remain compatible when `BuiltPreviewOrigin` is omitted. All live targets
are limited to root HTTP URLs on 127.0.0.1/localhost, without credentials,
query/path or fragment. Environment overrides remain inside the restoration
boundary and no local environment file is changed.

`scripts/lib/check-command.ps1` resolves actual applications before execution,
uses the first normal command match, tolerates successful native stderr and
rejects real nonzero exits, unresolved programs and processes that never start.
Seven real Windows PowerShell fixture tests pass. The first combined attempt
reproduced Composer resolving to two files and joining their paths; the corrected
runner passed the entire gate. The failed attempt remains in ignored
`.local/deployment-release-gate-native-resolution-failure.log`.

The complete real Vue journey passed in 167.97 seconds overall (161.07 seconds
inside the test), adding its labeled demonstration repair/review history while
preserving existing records. Native CMS HTTP now runs inside the same gate.
Subscription payments were not created or changed by these checks. The Vercel
project was rechecked again: Node 26, no deployment or domains, and provider-domain
SSO protection enabled. Public-origin access still needs actual verification.

## Subscription and revenue host verification

The read-only HTTPS verifier now checks anonymous 401 JSON denial, no-store and
nosniff headers for account, dashboard, repair history, subscription history,
analytics and native operations/subscription/revenue routes. It rejects denial
responses containing data or debug details. It also verifies delivery, MIME type
and nonempty content for the revenue projection script and checks the new SPA
subscription deep link. Fixture tests reproduced twelve missing checks before
the verifier was extended; the final complete tooling run passes 43 tests.

A routing regression also reproduced the generated SPA fallback matching private
paths such as `/backend/.env`. The fallback now excludes root/nested dot paths,
backend/deployment/tooling/documentation prefixes and static asset misses while
preserving normal application links. This is a routing correction, not evidence
that private files were exposed. No backend origin or public deployment was
invented; actual HTTPS routing still requires target-host acceptance.

The real CMS smoke test now checks anonymous and participant denial for the new
admin sections, authorized rendering of both pages with DEMO notices, projection
script delivery and logout across those routes. All fifteen stages passed
against the existing loopback backend. The built preview additionally confirmed
all five protected marketplace/subscription API paths return uncached JSON 401
responses. These checks create no repairs, subscriptions or payments. No schema,
authentication architecture, CMS content or application UI was changed.

## Deployment preparation follow-up

The Render image now supervises foreground PHP-FPM, Nginx, Laravel scheduling
and the database queue worker. It includes Supervisor and pcntl. Configuration
preflight runs before migrations and full database preflight before processes;
PHP-FPM/Nginx configuration checks run before supervision. Background jobs use the
existing PostgreSQL advisory lock and durable file transaction. New tests cover
successful file/database persistence, rollback/restoration on failure and actual
database queue worker execution with persisted recovery.

The Vercel config generator now distinguishes the source checkout from a compiled
release. Compiled packages use their existing assets without absent npm sources;
source checkouts keep the locked install/build. A real CLI fixture verifies mode
detection and refusal to overwrite reviewed configuration. Release packaging
uses dedicated static-upload exclusions for backend source, environments and
runtime material while retaining compiled frontend output. The first artifact
test caught duplicate `.vercelignore` copying; packaging now copies only the
compiled-release variant and all final checks pass.

Final evidence: 203 backend tests / 931 assertions, fifteen deployment/artifact/
CMS styling tests, full frontend compilation/type checking, 49 SFC/46 TypeScript
source checks and manifest/installed dependency agreement. The full Vue UI suite
baseline remains 166 tests; no application UI code changed in this follow-up.
All eleven native CMS HTTP stages passed. The isolated backup restored 38 tables
and 59 files with matching fingerprints/hashes and removed only its new restore
database. Its sensitive snapshot stays in ignored `.local/backups`.

The frontend engine metadata now matches the locked CLI requirements; the
connected Vercel project uses Node 26. The project still has no deployment or
domains. Render/Neon are not connected and no production credentials were found.
Fresh npm and Composer audits failed to reach registries. Docker/Podman are
absent; the image was not actually built here. The local production preflight
still correctly fails seven development/demo conditions. No local environment,
key, user, repair or payment was altered to make preflight pass. Browser and
target-host acceptance remain outstanding. No public deployment was performed.

# Historical verification before the Vue migration

## November 5: full release and hosting-connection verification

The full `scripts/verify-release.ps1 -LiveApi` gate passed on the current source:
**179 backend tests / 744 assertions**, **77 frontend tests in 14 files**,
25 rule/worker-retirement tests, seven artifact tests, 14 host-verifier fixtures,
four deployment-configuration checks, native Vue/CSS, TypeScript and production
build, five frontend HTTP stages, and the real repair/review journey (about 119
seconds overall). All eleven native Statamic HTTP checks also passed. Evidence:
`.local/nov05-full-release-gate.log` and `.local/nov05-cms-http.log`.

The authenticated Vercel project `erepair-verification` was rechecked: no live
deployment, assigned domain, environment variables or installed database integration.
The local checkout is now linked to that verified project using ignored
`.vercel/project.json`; linking does not deploy or configure the backend.
Render and Neon connections are available but not installed/authorized. No new
hosting tools became available after connection discovery. No hosting credentials
were present in the current process environment. No public endpoint was invented
or an incomplete frontend published.

Fresh npm/Composer audits again failed to reach their advisory registries; no clean
security audit is claimed. Docker/Podman remain unavailable. The read-only local
production preflight still correctly fails seven development/demo checks:
environment, HTTPS origin, CORS, Sanctum, secure sessions, mail and production data.
Existing local data/configuration were preserved. Public-host, container, external
mail/recovery and physical browser/mobile acceptance remain pending external access.
Connection status is recorded without secret values in
`.local/hosting-connection-status.json`.

Earlier verification history follows.

## Latest: Render/Vercel preparation

Follow-up backend verification passed **179 tests / 744 assertions**, including
real multipart repair creation, restart-style photo recovery through the download
API, rejection of another customer's session, and atomic rollback of jobs,
appointments, notifications and attachments on storage failure. Hosted database
lock waits are bounded to five seconds. Four deployment configuration tests pass.
The shell startup script passes `bash -n` syntax validation.

The Dockerfile now publishes only CP/frontend/eRepair assets, preserving the
custom CMS guards, independent user repository and Core edition configuration.
Container startup runs production preflight before starting PHP-FPM/Nginx.
The full backend suite was rerun after these changes. The earlier complete gate
below remains the frontend/live-flow baseline; no frontend code changed.

Fresh npm and Composer audits were attempted again and could not reach their
registries; neither produced valid advisory clearance. Evidence is retained in
`.local/hosting-final-backend.log`, `.local/npm-audit-current.json` and
`.local/composer-audit-current.stderr`. The Vercel project was rechecked: no
deployment, domains or environment variables are configured. Docker/runtime,
provider connections and public browser acceptance remain unverified.

The complete `scripts/verify-release.ps1 -LiveApi` gate passed after the hosting
changes: **176 backend tests / 726 assertions**, **77 frontend tests in 14 files**,
25 repair/worker-retirement checks, seven artifact tests, 14 host-verifier fixtures,
three deployment-configuration checks, native Vue/CSS, TypeScript, production build,
five frontend HTTP stages and the real customer-to-technician repair/review flow
(94 seconds of test execution, about 100 seconds overall). The updated artifact
fixture separately passed all seven checks after adding the deployment files.

Native CMS HTTP checks passed all eleven stages. A fresh local backup drill restored
**34 PostgreSQL tables and 53 files** with matching fingerprints/hashes into isolated
targets. Ten additive migrations are applied to the local application database.
Private uploads and all existing users/repairs remain preserved.

New opt-in hosting behavior is covered by disposable-PostgreSQL tests: content and
binary file recovery after simulated filesystem loss, authoritative startup restore,
durable deletion, path/integrity rejection, quota rollback/recovery, nonmultipart API
deletions, failed-response restoration, administrator singleton/idempotency, and a
production catalog that creates no people/jobs/reviews. HTTP-mail tests send real
Laravel reset notifications to a fake provider and check canonical links and safe
failure handling. They do not verify external mail delivery or a hosted restart.

Evidence: `.local/render-vercel-release-gate.log`; deployment instructions and
limitations are in [render-vercel.md](render-vercel.md). The connected Vercel project
has no deployment yet. Docker is unavailable locally, and Render/database/mail
provider configuration is absent. Local production preflight still correctly fails
seven development/demo settings; it was not weakened. Public launch is **not yet
cleared**. Earlier results below describe prior work, not the current test count.

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


The application now runs **Statamic Core** with one independent CMS editor in the
same Laravel backend and PostgreSQL database. The native Core user-limit check
passes. No paid Pro license is required for the current single-editor feature
configuration. Production host, browser/PWA and dependency-audit acceptance remain.

## Core conversion verified

- At the Core conversion, all **27 existing application accounts** were preserved: one administrator,
  ten customers and sixteen technicians. One linked CMS login represents the same
  administrator; it is not another person or marketplace participant.
- The additive ninth migration created `cms_users` and separate CMS token tables.
  The existing administrator's application password hash was preserved and copied
  once. Native CMS login works with the original local credentials.
- Marketplace `web` and CMS `cp` guards use distinct Eloquent providers in the same
  database. Native CMS login does not authenticate the marketplace API, and customer
  or technician login never grants CP access. Full session invalidation can log
  both guards out in the same browser.
- Native CMS user counting sees one actual table row. No vendor middleware/counts
  or license checks were patched. A database constraint and private CLI provisioning
  enforce one CMS editor. Operator suspension/demotion denies active CMS sessions.
- Pro, native REST/GraphQL, revisions, Git automation and multisite are disabled.
  Native Core collections, globals, blueprints, public assets and the Laravel
  operations extension remain. Native publication and unpublication are tested.
- Native CMS password-reset tokens cannot be substituted with marketplace reset
  tokens. CMS resets leave the application password unchanged and link to the
  correct native reset form.

See `free-cms-audit.md` and `statamic-integration.md` for the implementation and
links to the official independent-guard and Core licensing documentation.

## Latest automated release gate

The user removed the installation/PWA requirement. The regular-web implementation
passed 158 backend tests / 672 assertions, 77 frontend tests, 25 repair/worker-retirement
rules, seven artifact checks and 14 host-verifier fixtures. Native Vue/CSS checks,
TypeScript and the normal production build pass. No new caching worker or install
manifest is registered. PWA dependencies and 317 orphaned lock entries were removed
without changing the remaining package versions. npm's offline lock listing and
Arborist required-edge validation pass. Fresh dependency downloads could not be
performed under the current registry/cache restriction. Earlier PWA results below
are historical; physical OS installation is no longer a release requirement.

The landing-page redesign passed **80 frontend tests across 14 files**, TypeScript
and production/PWA compilation. Five added tests cover search/device-problem
compatibility, guest/customer/technician entry links and actual CMS-response
rendering. Featured profiles still use the existing public API. The built worker
now precaches 34 static entries (about 981 KiB). Browser visual/mobile acceptance
remains pending; backend and repair-workflow evidence below is the prior baseline.

The whole-project design consistency follow-up then passed all 75 frontend tests
(13 files), TypeScript and production/PWA compilation. Shared reference styling
now covers public and workspace pages; the route-area matrix is in
`redesign-audit.md`. Native operations CSS was published, its real Vue rendering
test passed, all 11 CMS HTTP stages passed, and the dedicated backend stylesheet
integration check passed (one test / seven assertions). The earlier full backend
and live repair-workflow results below remain the baseline; only UI/CSS and its
test fixtures changed in this follow-up.

The dashboard-reference follow-up passed 75 frontend tests across 13 files,
TypeScript compilation, the production/PWA build and eight built-frontend HTTP
checks. It adds a dedicated workspace shell, reusable metric cards, real area
chart and functional directory search. Three added route tests cover workspace
search and the other participant shown in technician/customer repair tables.
The original frontend run encountered laptop timing limits, and initial added
tests needed populated repair fixtures plus bounded lazy-route waits; the final
complete suite passed. Backend source and database schema were unchanged.
The real React DOM-to-Laravel/PostgreSQL repair-to-review workflow also passed
after the shell redesign (145 seconds), including account changes and logout.
The labeled demonstration request/review remains in local repair history.

The subsequent deployment-tool hardening passed 14 HTTPS fixture tests, including
six regressions that failed against the old verifier. It now checks native
Statamic scripts/stylesheets for delivery, MIME types, nonempty content and same
origin. A direct local HTTP run verified 25 distinct native assets. The expanded
native CMS smoke passed 11 stages. No application source or frontend build changed
in this follow-up, so the complete gate results below remain the prior baseline.

The custom CMS stylesheet bug is fixed: inline styles were discarded when Statamic
6 compiled the Blade page with Vue. CSS is now published and registered in the head,
versioned by content hash, and published automatically by the Composer asset hook.
The full gate includes a native CP Vue/CSS regression. Native CMS HTTP coverage has
ten stages, including head-link placement and CSS MIME/content delivery. The host
verifier rejects a missing operations stylesheet. Earlier HTML-only checks did not
establish this styling behavior.

The fresh readiness run passed the release gate and native CMS HTTP checks. A
subsequent verified preflight fix rejects cluster-administration flags even when
the runtime is not a superuser; both new regressions failed on the old check and
the complete backend suite then passed 150 tests / 643 assertions. The real
restricted login passes. A fresh isolated restore matched 32 tables and 43 files.
Public clearance remains pending; see `deployment-readiness.md`. Fresh npm and
Composer advisory requests both failed to reach their registries, not clean audits.

`scripts/verify-release.ps1 -LiveApi` passed after the complete deployment audit:

- **158 backend tests, 672 assertions, zero skips**, using guarded disposable
  PostgreSQL `erepair_test`; includes Core authentication, production configuration,
  demo-data rejection and backup-drill guard coverage.
  This includes seven new mail-preflight regressions for URL precedence,
  provider dependencies, credentials, timeout and TLS. Native transport construction
  confirms the effective SMTP settings without sending external mail.
- **72 React component tests in thirteen files**. Vitest uses two workers to avoid
  starving asynchronous UI updates on this laptop; the first unrestricted full
  run timed out waiting for a lookup, and the revised full gate passed unchanged
  application assertions.
- **46 standalone workflow/PWA rules** and **six release-artifact tests**.
- **Eight HTTPS host-check fixture tests**, included in the final release gate.
- Composer manifest/lock and platform checks, installation doctor, TypeScript
  compilation and production/PWA build.

The built PWA precaches 35 static files, about 976 KiB. Its worker excludes API,
Sanctum, CP and upload routes from runtime caching. The real deferred install
prompt and `/install` page have component coverage; no physical OS installation
success is claimed. The upstream worker bundler emits one nonfatal deprecated
inlineDynamicImports warning; generation succeeds.

`php scripts/cms-http-smoke.php` also passed all ten actual HTTP stages after
the conversion: native HTML/assets/login, API-session isolation, operational HTML,
reports, published content, participant denial and native logout. Existing private
asset/public asset, repair ownership and technician-verification checks pass in
the backend suite. Static preview/API delivery was checked after the last build.

The live React DOM-to-API workflow passed again in the November 4 complete release gate (102 seconds):
directory/profile selection, customer login, photo request, technician acceptance,
appointment confirmation, diagnosis/estimate, customer approval, repair progress,
completion and review, using real cookies, CSRF and the local PostgreSQL records.
No API/authentication/query mocks are used. Its labeled demo repair/review remains
in local demonstration history; this is not a browser/PWA installation result.

## Latest project audit fixes

The complete-system redesign adds the requested navy tokens and authenticated
sidebar, compact workspace navigation, discovery/profile/comparison/avatar updates,
shared supporting-page surfaces and scoped native operations styling. Real chart
activity is the only new read aggregate; matching, policies, authentication and
repair rules remain. The full gate passed; final navigation changes then passed
TypeScript, 72 frontend tests, production/PWA rebuild and HTTP smoke checks.
See `redesign-audit.md` for the complete inventory, file list, evidence and limits.

The subsequent visual-design pass adds shared Card and PageHeading components,
a redesigned dashboard overview/request table/appointment section, a restrained
public repair-journey illustration, split desktop authentication layout and unified
profile/request headings, input styling, empty states and status badges. Actual
server activity remains the only source of dashboard data. CSS resets were moved
or removed where they overrode Tailwind component border colors and font weights.
See `ui-design.md` for the design system and remaining physical browser acceptance.

The November 3 UI pass adds clearer primary/secondary/destructive actions, active
public navigation, icon-labelled workspace links, a collapsible mobile workspace
menu, mobile search-filter controls, persistent List/Map choices, comparison
selection emphasis and clearer repair-detail links. The homepage search and
directory hierarchy were refreshed without changing API authentication or the
logout-navigation boundary. Focus indicators and reduced-motion handling remain.

The map now groups identical points, exposes every corresponding profile, filters
invalid coordinate pairs, provides fit-all and missing-location/tile-error states,
and removes unverified radius circles. Native Core has an administrator-only
location correction form, rounded storage and activity logging. Nearest sorting
uses the same public rounded points. Geography remains approximate and dependent
on correct entered reference points; physical rendering and address accuracy are
not certified by component tests. See `technician-finder.md` for exact semantics.

The whole-project audit reproduced and fixed pending-photo access, stale photo
replacement, non-atomic repair notes and stale note status, missing administrator
photo inspection, map bounds and inactive-capability validation. Eight new backend
regressions, two map component tests and one profile-preview component test cover
these changes. The full release gate passed with the real API workflow; the final
photo-preview UI then passed TypeScript, all 33 component tests, the production
build and eight static/API HTTP checks. Native CMS HTTP passed eight stages.
The six artifact tests also passed after adding the audit and API docs to packages.
See `project-audit.md` for findings and the remaining acceptance items.

## Session privacy fixes

Account changes now cancel and remove private in-memory queries and mutation state,
preserve only recognized public caches and remount the protected form subtree.
Late responses from an older account cannot refill its private cache or terminate
a newer session. Protected 401/inactive-account responses clear local identity;
ordinary record-level 403 responses do not. CSRF failures recheck actual identity.
Suspended participants can invalidate their sessions through logout while private
records remain blocked. Configuration inspection errors also retain safe JSON
output rather than breaking machine-readable diagnostics.

Tests reproduced the old cache leak and a logout-navigation race before the fixes.
The explicit logout flow now waits for the public route to commit before releasing
its auth loading boundary. The full live workflow passes after the change. The
live test selects dates beyond actual existing bookings and authenticates cleanup
before canceling only its own uniquely labeled failed repair. See
`session-security.md` for the design and regression scope.

## Production configuration and recovery

The read-only `erepair:production-check` reports named checks without secrets.
It validates secure origins/CORS/Sanctum/cookies, encryption, PostgreSQL credentials
and remote TLS, real mail configuration, Core isolation, runtime/storage, migrations,
one active CMS editor, a non-superuser database role and absence of demo accounts.
The actual local run correctly fails production requirements for local HTTP/debug
settings, development sessions/trusted hosts, log mail and demo records. The
runtime database-role check now passes after the verified privilege separation.
The check does not change those settings or delete users. Remote
database CA configuration is now supported with `DB_SSLROOTCERT`.

`erepair:backup-restore-check` completed a real isolated demo recovery exercise:
**32 public database tables** matched exported repeatable-read snapshot fingerprints,
and **43 editorial/private/public files** matched SHA-256 hashes after restoration
into a separate staging tree. Only the new random restore database was removed;
the working database/uploads were preserved. Sensitive snapshots remain in ignored
`.local/backups` and are excluded from release packages. This is not a production
backup schedule or proof of off-host/key recovery. See `production-readiness.md`.

## Restricted runtime and mail delivery

The main local application now uses a dedicated PostgreSQL login without superuser,
database/role creation, replication or RLS-bypass flags. Only this application's
database/schema/relations were transferred; existing accounts, CMS identity,
technician profiles, repairs and administrator password were preserved. Privileged
CLI maintenance credentials and an environment rollback copy remain only in ignored
`.local` files, outside the web root and all release packages. Test credentials
stay separate. The hardened installer rerun made no further changes.

Native CMS HTTP checks, actual API writes and a fresh 32-table/35-file recovery
drill passed after this change. Temporary restore database creation/removal uses
separate CLI maintenance credentials; normal runtime has no CREATEDB grant. See
`database-runtime.md` for the applied scope and private recovery state.

Two guarded tests also delivered customer and Core administrator reset emails over
actual loopback SMTP, followed the delivered token links, and verified independent
password changes. No Notification/Mail fakes, external recipients or existing demo
password changes are involved. The bounded SMTP fixture exits through a shutdown
handshake and test-owned temporary files are removed. This proves the local mail
code path, not public inbox delivery. The main default mailer remains log, so its
production preflight requirement stays open. See `mail-verification.md`.

`requirements-checklist.md` maps the requested features to implementation and test
evidence while keeping actual browser and host acceptance explicitly pending.

## Release packaging

The release now includes `scripts/verify-host.mjs` for read-only checks of the
actual HTTPS origin after installation. Seven automated host-check fixture tests
cover normal delivery, unsafe origins, external assets, exposed private APIs,
insecure cookies, cached workers and sensitive-file SPA fallbacks. These are
tooling tests; no public domain or hosting credentials are configured or verified.

`npm.cmd --prefix frontend run build:production` forces same-origin API/CP links,
even when a local Vite environment points to the laptop. `node
scripts/prepare-release.mjs` creates a new timestamped source/static package in
`.local/releases`, omitting local credentials, database files, uploads, installed
dependencies and runtime caches. SHA-256 verification detects modified, missing or
extra files; it is not a publisher signature. Follow the included deployment
instructions and use the new Core package instead of older Pro packages.

## Installed runtimes

Laravel 13.39.0, Statamic 6.39.0, Sanctum 4.3.3, React 19.3.1, TypeScript 7.0.2,
Vite 8.3.5, Tailwind 4.3.3, Vitest 4.1.12 and Vite PWA Plugin 2.0.0 are installed
and locked. Local PostgreSQL 14.5 serves the loopback demo database; production
needs a maintained, patched server. Credentials remain in ignored local files.

- React preview: http://127.0.0.1:5173
- Laravel/API: http://127.0.0.1:8000
- Native Core CP: http://127.0.0.1:8000/cp
- Installation instructions/prompt: http://127.0.0.1:5173/install

## Remaining acceptance and environment limits

Normal headless Edge/Chrome launches failed under the current registry/process/GPU
restrictions. Browser rendering, maps, responsive/keyboard behavior, actual worker
execution and desktop/Android/iOS installation remain unverified. The live DOM
suite exercises actual React/XHR/Laravel/data without pretending to be a browser.

An optional isolated Nginx/PHP-CGI check stopped at configuration validation because
Windows denied Nginx access to the generated file. Actual target-host routing,
HTTPS and Linux PHP-FPM acceptance remain. No OS/browser security was disabled.

The successful npm install reported ten affected build/development dependency nodes
(two low, eight moderate) on glob/minimatch/deepmerge paths. A fresh registry audit
attempt failed; an empty offline report is not a clean audit. Findings remain open
in `dependency-audit.md`; no unsupported forced overrides were applied.

Before public promotion, complete browser/PWA acceptance, review these dependency
findings, configure the real HTTPS origin/cookies, patched runtimes, mail/reset
delivery, persistent editorial/private storage and tested backup restoration.
Hosting and domain costs are separate from the free CMS. No public deployment or
purchase has been made.
