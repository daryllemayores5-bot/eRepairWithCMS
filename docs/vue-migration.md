# Vue 3 migration ? activated November 11, 2026

Vue is now the canonical application in frontend/. The staged frontend-vue folder
was moved into that path after full type checking, 126 frontend tests, the
production build, built HTTP checks and the real repair/review journey passed.
The live journey completed in 194 seconds against Laravel/PostgreSQL with actual
cookie/CSRF sessions and uploads, without API/auth/query mocks.

The original source is in frontend-react-reference/. Its redundant installed
runtime was archived to .local/retired/frontend-react-runtime/ during cleanup;
it is no longer an active project folder. All 69 manifest-listed files matched
in both copies before archiving.
The active dependency lock contains no React-only packages. Eight shared CSS,
API, type and business-rule files remain byte-identical to the reference.

Four additional private-route regressions pass in the canonical folder: draft and
upload clearing on account switch, guest return destinations, pending/error
boundaries and native administrator routing. The canonical suite now passes all
143 tests, including thirteen navigation regressions covering active list/detail
links and exact overview matching. Backend, live workflow and eleven CMS HTTP
stages passed earlier; see
current-verification.md for evidence and remaining production requirements.

No backend application implementation, schema, existing identity or existing
business record was replaced. Live acceptance adds labeled demonstration history.
Origin overrides for the alternate local preview are process-local; existing
environment files are preserved. The Vue preview is http://127.0.0.1:5183 with
its scoped Laravel server on port 8001; port 5173 serves another application.

Physical desktop/mobile visual acceptance remains unverified because Edge and
Chrome headless GPU processes were denied. No OS/browser security was disabled. The five
moderate development-tool advisories and Node version warnings remain documented
in dependency-audit.md; a fresh agent-side registry audit still cannot connect.
Public deployment has not been performed as part of this migration.

The earlier staged-work notes below are history, not the active application status.

## Historical record

# Vue 3 migration

## Execution status

### Form and repair-request verification

The latest independent checks pass **79 isolated Vue tests**, strict type checking
for **14 application modules**, and source checks for **47 SFCs / 34 TypeScript
files**. The eight preserved CSS/API/type/business-rule files still match the
React reference.

Authentication and request validation schemas are now shared by the actual Vue
forms and their tests. Thirty-five validation/step cases compare behavior directly
with the preserved React schema expressions. A further regression reproduced and
fixed the Vue form helper submitting raw values instead of Zod's parsed result;
unknown fields are now stripped from submitted values as in the React resolver.

Four repair-submission tests exercise the actual Axios request pipeline with an
isolated adapter. They verify multipart field names, photos, Philippine-to-UTC
appointment conversion, credential/XSRF configuration, cache invalidation and
preservation of backend validation messages. These are transport-contract tests,
not evidence of a successful live backend workflow.

Package installation was attempted again and remains denied by `EACCES`. The full
Vue integration suite and production build still cannot run; the active frontend
has not been switched. No database/backend implementation changes were made.

### Cutover preparation

The production build entry point now selects React's compiler or `vue-tsc` from
the active frontend manifest, preserving the existing same-origin API/CMS
overrides. Seven fixtures verify compiler selection, failure handling and Vue
directory discovery before/after activation. The current React production build
passed through this updated entry point. Vue source, isolated type-check and core
test configuration now follow the frontend when it moves to the canonical path.
The new build fixtures are part of the release verifier.

The combined **33 delivery/build fixtures** and **39 isolated Vue tests** passed
after these changes. The 47-SFC/30-TypeScript source checks and twelve-module
isolated type check also passed.

Dependency access was checked again: npm still returns `EACCES`, and no usable
local installations or alternate package caches contain the missing integrations.
The requested terminal installation remains necessary before the full Vue tests,
production build and actual activation. React has not been replaced; all 69
reference files still match. No backend/database changes were made.

### Follow-up development

Registry access was rechecked and still fails with `EACCES`. Development continued
on the portions that can be verified with existing packages:

- Reproduced and fixed a controlled text-input reset regression. Typed search now
  uses input events so matching-professional searches update while typing, as in
  the React version. File and select controls retain their change-event behavior.
- Moved the existing session behavior into `services/auth-session.ts`, used by the
  Pinia store. Fifteen transport/cache regressions pass with actual Axios
  interceptors and TanStack Query observers: identity changes, expiry, late
  private/auth responses, inactive accounts, ordinary forbidden records, CSRF
  revalidation, logout failures, navigation timing and interceptor disposal.
  The Pinia/router integration suite remains pending; these are not live HTTP
  or browser-session tests.
- Extracted map lifecycle behavior into `composables/technician-map.ts` while
  preserving the map template and styling. Seven tests pass with the real Leaflet
  engine in jsdom, covering bounds, overlapping participants, invalid/missing
  coordinates, fit controls, tile-error feedback and map cleanup/recreation.
  Actual tiles, routed popup navigation and browser layout remain unverified.
- Corrected the directory's constructor-binding order so its first query can
  read URL parameters during setup. Full directory rendering still needs the
  blocked integration suite.

Current follow-up verification: **39 isolated tests passed**, **12 application
modules passed strict isolated type checking**, and **47 SFCs / 30 TypeScript
files passed source checks**. The eight shared files and 69-file React reference
still match. The production Vue build and cutover remain blocked.

Additional reproducible command:

```powershell
node scripts/typecheck-vue-core.mjs
```

This checks actual session, form, map, transport and shared modules using installed
type declarations. It does not replace full Vue/SFC/Pinia/Vue Query type checking.

The following initial-session record is retained for comparison.

The Vue source is staged in `frontend-vue`; `frontend` is still the active React
application. The preserved `frontend-react-reference` contains source, tests,
public assets, configuration, dependency locks and a SHA-256 reference manifest.
Its local environment file was excluded. No backend source, database schema or
existing database records were changed by the migration work.

Completed source ports cover every existing route module: landing/editorial/help,
directory/filtering/compare/profiles/map, authentication/recovery, dashboard,
request lists/history, appointments, notifications, profile/availability/reviews,
the five-step photo request and repair detail/actions. There are **47 script-setup
SFCs**, a Pinia authentication store, reactive Vue Query composables, Vue Router
routes, Vite/TypeScript/test configuration and a same-origin production builder.
This is a source-port inventory, not a claim of full runtime or visual parity.

The Axios client, CSS, types, formatting/errors, repair rules and worker-retirement
implementation are retained byte-for-byte in eight shared files. Session query
keys, private-data cancellation and mutation clearing remain intact. Existing Zod
validation schemas are retained. Vue-specific compatibility includes button slots,
uncontrolled default form values, file inputs and private-form identity boundaries.
The native Statamic administrator interface remains in the existing backend.

### Verified in this migration session

| Check | Result and scope |
| --- | --- |
| Original frontend | All 77 React tests passed; TypeScript and Vite production build passed |
| Original standalone rules | All 25 repair/photo/appointment/worker-retirement tests passed |
| Existing delivery fixtures | All 26 artifact, host, Vercel/container and native CMS-style fixture tests passed; these do not certify a real host |
| Vue source | All 47 SFCs and 26 TypeScript files passed syntax, template-binding and local-import checks |
| Shared source | Eight CSS/API/type/business-rule files match the React reference byte-for-byte |
| Vue isolated tests | 15 tests passed using actual cached Vue packages and the existing Vitest runner; covers primitives, slots, field models/defaults, files, avatar fallback, validation, invalid-field focus, private cache cancellation and worker retirement |
| React preservation | 69 reference files match the active React implementation, including its package manifests/locks |

Source verification is reproducible with `node scripts/check-vue-source.mjs`.
Reference verification is `node scripts/verify-react-reference.mjs --compare-active`.
The cached isolated suite runs with:

```powershell
node frontend/node_modules/vitest/vitest.mjs run --config frontend-vue/vitest.core.config.ts
```

### Blocked acceptance and cutover

Online installation of Vue integrations repeatedly fails because this environment
denies registry requests with `EACCES`; offline installation fails with `ENOTCACHED`
for Vue Query. The Vue runtime/compiler and Vite Vue plugin were recovered from
the existing cache, but Vue Router, Pinia, Vue Query, Vue-specific test utilities
and `vue-tsc` cannot be fully installed. A direct Vue build attempt stops at the
missing `vue-tsc` executable. No resolved Vue dependency lockfile or successful
complete Vue build is claimed.

The complete existing route inventory and eleven authentication regressions have
been ported, along with the real local repair/review journey. Those suites have
**not run**. Map integration, live backend/authentication flows and desktop/mobile
visual parity are likewise not certified. Remaining specialized regression parity
must be checked against the retained React tests. The backend suite was not rerun
because backend implementation was not changed.

Next actions once registry access is available:

1. From the workspace root, run
   `npm.cmd --prefix frontend-vue install --cache .local/npm-cache`; retain/review
   the generated lockfile and actual resolved dependency versions.
2. Run Vue typecheck, the complete test suite and `build:production`; correct
   diagnostics and port any remaining feature-specific regression cases. A source
   syntax check does not substitute for any of these checks.
3. With the existing local backend/database and Vue preview running, execute the
   opt-in live suite. Compare all routes, responsive layouts, validation, maps,
   notifications and keyboard behavior with the React reference in real browsers.
4. Only after that acceptance, make Vue the canonical `frontend`, update setup,
   release/artifact/hosting integration and framework documentation, remove active
   React-only dependencies and rerun the complete release gate. Preserve the
   reference. Publishing remains a separate action.

No React dependencies were removed and no production-facing frontend switch was
made while those acceptance checks were unavailable.

## Scope and inventory (November 10, 2026)

The existing frontend is a Vite/TypeScript React SPA. Laravel/Statamic, PostgreSQL,
the independent native CMS, API routes, schemas and existing records stay unchanged.

Source inventory: App/main, SiteLayout and DashboardLayout, ProtectedRoute,
AuthContext, useLookups/useWebsiteContent, four UI modules, five common modules,
technician cards/maps, and 13 page modules. Several modules export additional pages:
password recovery, comparison, editorial/how-it-works, repair lists, appointments,
notifications, basic profile and technician reviews. App.tsx defines the complete
public and authenticated route inventory, including legacy /install redirection.
There are 14 React regression files, a worker-retirement suite, a live repair journey,
standalone repair rules and release/hosting fixtures.

Contracts retained: Axios /api/v1, credentials/XSRF cookies, /sanctum/csrf-cookie,
Laravel Sanctum sessions, multipart uploads, existing query keys and invalidations,
Manila appointments/UTC transport, PHP formatting and existing Zod/repair rules.
No browser token persistence. Unknown query keys are private by default.

## Ordered implementation plan

1. Copy React source, tests, public assets, package locks and build configuration to
   frontend-react-reference before implementation edits. Keep it independently runnable.
2. Install Vue 3, Vue Router, Pinia, Vue Query, Vue-compatible Lucide and notifications,
   Vue Vite/TypeScript/testing support. Retain React dependencies during verification.
3. Convert primitives to script-setup SFCs, preserving DOM elements, classes and slots.
   Convert auth to a Pinia store with query cancellation, generation checks, inactive
   account/CSRF handling and a logout navigation barrier. Port layouts and route guards.
4. Port landing/editorial/connectivity, discovery, comparison, profiles and Leaflet.
   Preserve filtering URLs, geolocation, grouped markers, missing-location behavior.
5. Port dashboards, paginated requests/appointments/notifications, profiles,
   availability, request wizard and repair detail/actions. Reuse backend contracts
   and framework-independent helpers unchanged.
6. Port regression tests and the guarded local live journey. Run Vue type checking,
   component tests, existing rule/release fixtures and production build. Run backend
   and local HTTP workflows when their prerequisites are available. Record exact
   results, failures and unavailable checks; browser parity requires browser evidence.
7. Once replacements pass, remove active React dependencies and update delivery
   scripts/documentation. Preserve the reference and its package lock for comparison.

## Risks and acceptance

- Session boundaries: account switch, expired/inactive identity, late requests,
  CSRF errors and logout must never retain/render another user's private data.
- Vue reactivity: query keys, enabled predicates, route params and dependent lookup
  fields must update; asynchronous identity changes must remount private forms.
- Forms: preserve validation messages, steps, selected technicians, photo limits,
  native form names, multi-select checkboxes and actual multipart requests.
- Design: keep CSS/font tokens/classes, responsive navigation, SVG icons, charts,
  pending/error/empty states and accessible field labels. No redesign is authorized.
- Map: use Leaflet directly with cleanup, grouped popups, all-points bounds and
  tile-failure feedback; external tile delivery still needs physical browser checks.
- Release: no React source/tests may accidentally enter the Vue bundle or source
  artifact; preserve preview proxies, API origin settings and legacy worker retirement.
- External dependency downloads, a running local database/API, and real browsers
  are environmental prerequisites, not grounds to claim untested success.

The user has authorized this frontend migration. No additional approval is needed
for reversible implementation work within this scope. Backend/schema changes,
publishing and deleting the React reference are outside this migration plan.
