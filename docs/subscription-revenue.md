# Subscription and simulated revenue system

## Plan-specific workshop tools

Free retains the full existing repair workflow. Pro and Premium add **Documents
& Templates** at `/dashboard/documents`: technicians can download actual PDF
quotations and job sheets for their own repairs and create/edit/delete up to 100
private note templates. Selecting a template on a repair inserts editable text;
the technician must explicitly post the note. Quotations require an estimate.
PDFs are generated without a new package, use standard PDF fonts and contain no
remote assets. They are repair documents, not payment receipts.

Premium additionally adds **Parts Inventory** at `/dashboard/inventory`: opening
stock, restocking, repair-linked usage, reorder levels, low-stock alerts, inactive
parts and movement history. Only the owner's in-progress repairs can consume
parts. Stock writes use transactions and row locks; repeated movement keys do
not deduct twice, even under concurrent requests. Negative stock is prevented.
Each usage snapshots its unit cost; restocking changes the current unit cost
without rewriting previous usage. In-app low-stock alerts include zero stock.

Premium **Business Reports** at `/dashboard/business-reports` supports Philippine
date filters (up to 366 days), request counts, completed repairs, latest approved
estimates on completed jobs, recorded parts costs and CSV/PDF downloads. Exports
include up to 2,000 requests submitted in the selected range. Completion metrics
use completion dates; parts metrics use movement dates. Estimate totals are not
received revenue, and parts costs do not include labor or other shop expenses.
Exports protect spreadsheet cells against formula injection and omit customer
emails and attachments. Large monetary aggregates retain exact decimal strings.

The API enforces plan, active-account and owner checks on every tool operation.
Scheduled upgrades do not unlock tools early; access stops at expiration even
without cron. Saved templates, stock and movement records remain stored when a
plan expires. The additive workshop migration adds three tables and appends tool
benefits without changing plan prices, purchased cycles or existing repair data.
Rollback refuses to discard workshop history. Tool links and locked upgrade
cards remain visible to Free technicians for plan comparison.

The technician subscription page compares **Free (₱0)**, **Pro (₱199 / 30 days)**
and **Premium Technician (₱399 / 30 days)**. Prices are database-configured defaults.
Choose a paid card, review the selected quote, then click **Simulate Payment**.
Requests show a stable `SUB-000001` reference alongside preserved internal DEMO
audit references. No real money is collected. Only the native CMS administrator
can approve or reject requests; admin search accepts either reference.

The additive Premium migration inserts the `premium` plan only if missing,
preserving existing/customized Free, Pro and Premium records. It adds a scheduled
cycle state and a unique scheduled-cycle index in the existing tables. It is
forward-only: rollback refuses to remove purchased plan/history data. No users,
payments, repairs or existing subscriptions are seeded or rewritten.

## Paid plan changes and renewals

The landing page now offers a public **For Technicians** comparison, linked by
**View technician plans**. Its prices, durations, descriptions and benefits come
from `GET /api/v1/subscription-plans`, which exposes active public plan fields
only, ordered by configured price. No prices are duplicated in marketing code.
Inactive plans are hidden; loading, retry and empty-catalog states are supported.
Visitors can create a technician account, existing technicians can open their
subscription workspace, and customers are not sent into subscription checkout.
The comparison explains separate repair estimates, administrator verification,
subscription-only badges and the DEMO payment/approval requirement. There is no
checkout or payment creation on the public page.

| Scenario | Approval behavior |
| --- | --- |
| Free → Pro or Premium | Start the selected 30-day plan at approval |
| Active Pro → Premium | Keep Pro until its existing expiration; schedule a full 30-day Premium period immediately afterward |
| Active Premium → Pro | Use the same scheduling policy; keep Premium until its existing expiration |
| Pro → Pro or Premium → Premium renewal | Preserve remaining time; the renewed cycle ends 30 days after the later of current expiration or approval |
| Expired paid plan → Pro or Premium | Start the selected 30-day period at approval |

There are no prorated refunds, monetary credits or price adjustments. If a current
period expires while a request awaits approval, the new period starts at approval.
Only one pending request and one future approved plan change can exist per
technician. A scheduled change blocks additional checkout until it starts.
Scheduled periods provide no early badge, promotion or Premium analytics.
Entitlements check timestamps even if the scheduler is late; the existing
`erepair:subscriptions-expire` job also expires old periods and activates due ones.
Already expired scheduled periods never grant benefits. Existing historical
cycle/payment rows remain available.

Revenue is recognized once at DEMO payment approval, including scheduled periods.
Rejection and repeated/conflicting decisions add no revenue. Activation of a
scheduled cycle adds no second transaction or revenue amount.

This addition uses the existing Laravel 13.39.0 / Statamic 6.39.0 Core backend,
PostgreSQL database, independent single CMS editor and Vue 3 frontend. It does
not add any payment provider, credentials, paid CMS feature or chart dependency.
Every payment is a DEMO simulation. Customers and Free technicians retain the
existing discovery, inquiry and repair features without subscribing.

## Access

- Technician: `http://127.0.0.1:5183/dashboard/subscription`, or **My Subscription**
  in a technician's workspace menu.
- Administrator login: `http://127.0.0.1:8001/cp`.
- Administration: `http://127.0.0.1:8001/cp/erepair/subscriptions`.
- Revenue: `http://127.0.0.1:8001/cp/erepair/revenue`.

Administrator links also appear in native Statamic's eRepair navigation and on
the existing Operations page. Marketplace sessions cannot administer payments.
The CMS remains configured with `statamic.editions.pro=false` and one CMS editor.
Laravel controllers, Eloquent, policies and Blade implement subscription logic;
no Statamic commerce, multi-user, roles/permissions or paid add-on is used.
See [Statamic licensing](https://statamic.dev/getting-started/licensing).

The existing local demo password stays in ignored `.local/demo-password`; never
publish it. The subscription HTTP check upgraded `technician1@erepair.test` and
left two approved DEMO payments. Use another existing Free demo technician,
such as `technician2@erepair.test`, for a fresh upgrade presentation. Confirm its
current tier in Subscription Management before presenting. No demo accounts,
repair records or passwords were replaced.

## Installation and running

The existing local installation has already run the additive migration. For
another existing installation, preserve its environment, key and database:

```powershell
cd backend
php artisan migrate
php artisan vendor:publish --tag=erepair-cp-assets --force
php artisan schedule:work
```

Run the scheduler separately from the web servers. On a deployed host configure
Laravel's regular once-per-minute `php artisan schedule:run` cron entry instead.
The provided Render image supervises its scheduler and database queue worker
automatically; do not add a duplicate cron/scheduler to that same instance.
`erepair:subscriptions-expire` is scheduled every minute without overlapping.
Request-time access checks also reject overdue subscriptions immediately.

From the project root, use the current isolated local ports:

```powershell
powershell -ExecutionPolicy Bypass -File scripts/postgres.ps1
# Separate terminal:
powershell -ExecutionPolicy Bypass -File scripts/dev.ps1 -Port 8001 -FrontendOrigin http://127.0.0.1:5183
# Separate terminal:
$env:BACKEND_URL='http://127.0.0.1:8001'
npm.cmd --prefix frontend run dev -- --port 5183 --strictPort
```

## Data and lifecycle

Four new tables are added by
`backend/database/migrations/2026_11_11_000001_create_demo_subscriptions.php`:

| Table | Purpose |
| --- | --- |
| `subscription_plans` | Configurable Free, Pro and Premium names, descriptions, prices and feature text; default paid prices ₱199 and ₱399, each 30 days |
| `technician_subscriptions` | Historical pending, active, scheduled, expired and cancelled purchase cycles, with UTC start/expiration timestamps |
| `subscription_demo_payments` | Unique DEMO reference and checkout key, decimal amount, quoted name/duration, decision, administrator and timestamps |
| `technician_profile_views` | Profile/day keyed HMAC-deduplicated visits; no raw IP, email or customer identifier |

`technician_id` references `technician_profiles.id`, matching the existing repair
domain, not `users.id`. Free is the default entitlement; no Free subscription
row or new registration behavior is needed. The original migration seeds Free and
Pro; the additive Premium migration inserts the third plan without replacing
existing definitions. Neither migration creates users or payments.

Paid requests snapshot the selected plan, configured price and duration. The technician confirms
that displayed quote; a changed server price requires refreshed confirmation.
Administrator plan edits do not rewrite existing payment amounts or paid time.
The Free plan must remain active and priced at zero. Both paid durations are 30 days.
Disabling a paid plan prevents new checkouts while honoring existing quoted
requests and already approved periods.

Each request creates a pending cycle and DEMO payment in one transaction. The
technician profile is locked first for checkout and review operations. PostgreSQL
partial unique indexes enforce one pending request, one active cycle and one scheduled cycle per
technician; unique references and per-technician checkout keys enforce duplicate
protection. Repeated/conflicting decisions on a finalized payment do nothing.
The database additionally rejects non-DEMO payment methods and invalid states.

Approval records the administrator and time, then activates or schedules one cycle
according to the policy above. Same-plan renewal
expires at `max(current approved expiration, approval time) + 30 days`. The old
approved cycle is retained as cancelled/replaced, with its payment unchanged;
its remaining time transfers to the new active cycle. Rejection cancels only the
pending cycle, preserves any current paid period, and allows another checkout.
Time expiration never deletes accounts, inquiries, repairs or approved revenue.

Pro and Premium badges are subscription indicators, not verification or qualifications.
The finder still requires approved verification and an active technician account.
All existing compatibility, location, availability and explicit sort rules remain
first; Premium then Pro status breaks ties before the existing ID tie-breaker. Free
technicians stay visible. Public cards mark valid paid listings as featured, with
distinct violet Premium and amber Pro styling. Public APIs expose
`subscription_tier` and `is_premium`; legacy `is_pro` and `hasProSubscription()`
mean access to Pro benefits, which Premium inherits. `validPro()` remains the
exact Pro tier for CMS filters and distribution counts; `validPaid()` covers both.

## Revenue, projections and analytics

Revenue comes exclusively from approved `is_demo=true` records, using their
stored amounts and approval dates. SQL decimal sums and integer centavos handle
money; no floating-point revenue arithmetic is used. Month/year boundaries use
Asia/Manila before conversion to UTC. The chart includes the last twelve months,
including zeros. Pending/rejected payments do not contribute. Expired approved
periods retain their historical revenue.

The doughnut shows Free, active Pro and active Premium among active marketplace technician
accounts, including those awaiting verification. Suspended accounts are excluded
from that distribution. Payment/revenue history retains all approved transactions.

Revenue Projection uses the selected configured Pro or Premium price and an adjustable paying
technician count. Server-side calculations and the browser's integer-centavo
calculator agree. At ₱199: 10 → ₱1,990/month and ₱23,880/year; 50 → ₱9,950/month
and ₱119,400/year; 100 → ₱19,900/month and ₱238,800/year. These are projected
gross subscription revenues, not profit, actual earnings or approved DEMO totals.
At ₱399, 10 Premium technicians project ₱3,990/month and ₱47,880/year.
The form still calculates on submission when JavaScript is unavailable.

Profile-page visits send a separate CSRF-protected request after the profile
loads. Directory/comparison data fetches do not inflate views. Tracking also runs
for Free profiles so a later Pro subscription can show recorded history. The
daily HMAC includes the day and profile, preventing cross-day/profile visitor
linkage without the server key. Self-views are skipped. Counts are approximate:
shared networks can undercount and bots can overcount. No pre-installation view
history is invented. Customer inquiries are actual existing repair requests;
they are not completed jobs. Pro analytics shows totals, six monthly buckets and
the fourteen most recent days with recorded views. Free accounts keep their
existing dashboard and receive an upgrade explanation for advanced analytics.
Premium additionally provides 7/30/90-day daily view and inquiry counts, zero-filled
days, selected-period totals and previous-period totals using Philippine dates.
These are actual tracked views and repair requests scoped to the signed-in
technician. No conversion rate is claimed: view hashes do not link a visit to
an individual inquiry. Pro retains its existing totals and six monthly buckets;
Premium date filters are enforced server-side.

## Live capstone presentation

1. Sign into an existing Free technician account. Open **My Subscription** and
   explain Free access, all three comparison cards and the DEMO-only notice.
2. Click **Upgrade to Pro** or **Upgrade to Premium**, inspect the selected ₱199/₱399 quote and click **Simulate Payment**.
   Show **Pending Admin Approval**, the DEMO reference and unchanged Free tier.
3. In a separate browser profile/session sign into the native CMS administrator.
   Open **Subscription Management**, locate that reference and approve it.
4. Return to the technician. The page polls every 15 seconds; refresh if needed.
   Show Active, start, expiration, days remaining and actual analytics. For an
   existing Pro account upgraded to Premium, show its scheduled start/expiration
   and explain that Pro remains available until its paid time ends.
5. Open the public technician profile/card. Explain the Pro/Premium subscription badge
   separately from the existing verification badge. Paid priority never bypasses
   filters or verification; it breaks relevance ties.
6. Open **Revenue Dashboard**. Compare before/after totals: approving one default
   request adds exactly its quoted ₱199 or ₱399. Show monthly chart/data, tier distribution and
   transaction history. Repeating approval does not increase revenue again.
7. In **Revenue Projection**, enter 10 and then 50. Show the monthly and annual
   projected gross amounts, separate from approved DEMO payment totals.
8. Demonstrate expiration using the exact newly approved reference and selected
   demo technician profile ID, then refresh public/private pages. Free access
   remains; Pro badge, priority and analytics access are removed. Submit renewal
   and approve it to restore Pro. Renewing before expiration preserves paid time.

## Optional local sample setup and safe expiration/reset

Find the **profile ID** in the public technician profile URL, such as
`/technicians/2`. Commands require local/testing, a loopback database, an existing
`.test` technician account and an existing active administrator. No user is
created or deleted. Replace the examples with the intended profile/reference.

```powershell
cd backend
# Optional only: create four labeled sample scenarios on a demo technician
# without manually requested subscriptions. Adds two approved DEMO payments.
php artisan erepair:subscriptions-demo setup --technician=2
# Repeat setup safely: the same sample requests are not duplicated.

# Expire exactly one approved active DEMO cycle for a .test technician:
php artisan erepair:subscriptions-demo expire --technician=2 --reference=DEMO-EXACT-REFERENCE

# Regular overdue expiration (does not artificially change timestamps):
php artisan erepair:subscriptions-expire

# Remove ONLY command-generated is_seeded_demo cycles/payments for this profile:
php artisan erepair:subscriptions-demo reset --technician=2 --confirm
```

Reset preserves manually requested payments, accounts, views, repairs and the
audit trail. It intentionally removes sample approved amounts from DEMO totals.
It does not reset manually approved presentation transactions. Use another Free
demo account for repeated presentations; never truncate the application tables
or rerun the original whole-application DemoSeeder on existing data.

## Routes added

The overview includes `current_plan_slug`, `is_premium`, and
`scheduled_subscription` (with plan and period dates) in addition to existing
fields/history. All three plans are returned, including disabled plans for
display; new checkouts require an active paid plan. Scheduled purchases remain
approved transactions in history while their subscription state is `scheduled`.
Current-tier checks and Premium analytics use period timestamps, not plan names.

| Method | Route | Access |
| --- | --- | --- |
| GET | `/api/v1/technician/subscription` | Active technician; own overview/history |
| POST | `/api/v1/technician/subscription/demo-payments` | Active technician; `plan_slug=pro` or `premium`, confirmed `expected_price`, `confirm_demo=true`, UUID `idempotency_key`; omitted slug preserves legacy Pro behavior |
| GET | `/api/v1/technician/subscription/analytics` | Active technician with approved Pro/Premium; optional `range_days=7`, `30`, `90` requires current Premium |
| POST | `/api/v1/technicians/{id}/view` | Visible public profile; CSRF and throttling |
| GET | `/cp/erepair/subscriptions` | Existing authorized native CMS administrator |
| POST | `/cp/erepair/subscriptions/payments/{payment}` | Same administrator; approve/reject |
| POST | `/cp/erepair/subscriptions/plans/{plan}` | Same administrator; validated plan edits |
| GET | `/cp/erepair/revenue` | Same administrator; filters/projections |

## File inventory

New backend files:

- Migration `database/migrations/2026_11_11_000001_create_demo_subscriptions.php`.
- Models `app/Models/{SubscriptionPlan,TechnicianSubscription,SubscriptionDemoPayment}.php`.
- Services `app/Services/{SubscriptionService,SubscriptionRevenue}.php`.
- Money helper `app/Support/SubscriptionMoney.php`.
- Policy `app/Policies/TechnicianSubscriptionPolicy.php`.
- Requests `app/Http/Requests/{RequestDemoSubscription,UpdateSubscriptionPlan}.php`.
- Controllers `app/Http/Controllers/{Api,Cp}/SubscriptionController.php`.
- Commands `app/Console/Commands/{ExpireSubscriptions,SubscriptionDemo}.php`.
- Blade views `resources/views/cp/{subscriptions,revenue,subscription-nav,subscription-payments}.blade.php`.
- Script `resources/js/revenue-projection.js`, published to
  `public/vendor/erepair/js/revenue-projection.js`.
- PostgreSQL tests `tests/Feature/SubscriptionIntegrationTest.php`.

Modified backend files: `app/Models/TechnicianProfile.php`,
`app/Services/TechnicianFinder.php`, `app/Http/Resources/TechnicianResource.php`,
`app/Providers/AppServiceProvider.php`, `routes/{api,cp,console}.php`,
`resources/views/cp/erepair.blade.php`, `resources/css/erepair-cp.css` and its
published `public/vendor/erepair/css/operations.css`. The required installed Boost
setup also regenerated `backend/CLAUDE.md` development guidelines. No dependency
or environment change was required.

New frontend files: `src/pages/MySubscriptionPage.vue`, its `.test.ts`,
`src/types/subscription.ts`, `src/components/technician/ProBadge.vue` and
`src/lib/revenue-projection.test.ts`. Modified: `src/router.ts`,
`src/layouts/DashboardLayout.vue`, `src/components/technician/TechnicianCard.vue`,
`src/pages/TechnicianPage.vue`; generated `frontend/dist` was rebuilt.

Other additions: `scripts/subscription-http-smoke.php` and this guide. README,
`docs/current-verification.md`, `docs/api-documentation.md`,
`scripts/lib/release-artifact.mjs` and `tests/release-artifact.test.mjs` are updated
to document and package the addition.
The preserved React references, existing authentication, CMS content, environment
files and repair schema are unchanged.

## Verification and limitations

Run from the root with the existing PHP PostgreSQL extensions/test credentials:

```powershell
$env:PHP_INI_SCAN_DIR="$PWD/.local/php"
$env:RUN_POSTGRES_TESTS='1'
cd backend
php artisan test
cd ..
npm.cmd --prefix frontend test
npm.cmd --prefix frontend run build:production
node scripts/check-vue-source.mjs
```

The backend suite is guarded to use only `erepair_test`, never the local working
database. The subscription tests cover Free access, confirmed pending requests,
duplicate protection, approval/rejection, authorization, renewal, expiry, badge
data, finder filters/order, exact revenue/month buckets, projections, analytics,
native CMS compatibility and safe demo controls.

An opt-in real HTTP journey uses existing local demo credentials and **leaves two
approved DEMO payments** on a selected Free `.test` technician. It refuses to
expire an already-active technician at startup. From the workspace root:

```powershell
$env:PHP_INI_SCAN_DIR="$PWD/.local/php"
$env:EREPAIR_LIVE_ORIGIN='http://127.0.0.1:5183'
php scripts/subscription-http-smoke.php http://127.0.0.1:8001 technician2@erepair.test
```

The actual completed run used technician1. It verified real sessions/CSRF,
pending creation, repeated request, unauthorized approval denial, native admin
approval/repeated approval, exact revenue, Pro profile and analytics, expiration
and approved renewal. Unit/component tests do not certify physical browser
layout, native dialog keyboard handling or screen-reader behavior. Browser/mobile
acceptance remains required in this environment. The scheduler is registered;
keep a worker/cron running during unattended use. No real payment processing,
external deployment or legal/accounting earnings claim is provided.

Final automated totals are recorded in `current-verification.md`.

## Final completion evidence

The final hardening run passed **199 backend tests / 916 assertions**, **166 Vue
tests**, full type checking, production compilation, source checks, five built
preview delivery stages and eight packaging/CMS styling tests. All 69 preserved
React source files match. Earlier real subscription and repair HTTP journeys also
passed; the final test run added no further working-database payments.

Subscription polling now refreshes shared finder/profile caches on entitlement
changes and removes cached analytics after expiration. Confirmation retains the
price the technician actually opened even when polling updates the plan listing.
Sample setup is atomic: interruption rolls back all its cycles, payments and audit
entries. Artificial expiration rechecks its target after obtaining the profile
lock. Generated expired samples have corresponding historical approval dates.

`backend/tests/Feature/SubscriptionConcurrencyTest.php` and
`backend/tests/Support/subscription-race-worker.php` add real independent-process
coverage. Workers synchronize before simultaneous checkout, approval and renewal.
The assertions prove one same-key payment, one pending competing renewal, one
active period, preserved remaining time and no duplicated revenue. The test is
guarded to `erepair_test`, cleans up only its own fixture records and never uses
the working database. Run it separately with the test opt-in:

```powershell
cd backend
$env:RUN_POSTGRES_TESTS='1'
php artisan test --filter=SubscriptionConcurrencyTest
```

No requested subscription/revenue feature remains unimplemented. Physical browser
and screen-reader acceptance is still an operational prerequisite: a fresh Chrome
attempt failed before rendering with `GPU process isn't usable` and produced no
screenshot. Browser security was left enabled. Keep Laravel's scheduler running
for unattended expiration; timestamps also enforce access without the scheduler.
