# Statamic Core integration

Native **Repair Services** entries now manage homepage service titles, summaries,
details, display order and publication. The Operations page links directly to
services, pages, announcements, FAQs and homepage globals. See
[CMS service management](cms-services.md) for editing and defense instructions.

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


## Latest local CMS verification — November 4, 2026

The CMS login → authorized operations/shared PostgreSQL data → native editorial
publication → public content API flow passed fresh checks:

| Boundary | Evidence |
| --- | --- |
| Native authentication and permissions | 28 focused backend tests / 161 assertions passed, including guard isolation, editor restrictions, operational authorization and technician verification |
| Editorial write/publication path | Native entry creation, publish and unpublish change public API visibility in the guarded integration test; only its uniquely named temporary entry is removed |
| Working local server | All 11 native CMS HTTP stages passed: login, CSS/JS, operations, head-loaded custom CSS, reports, published content, participant denial and logout |
| Native client assets | 25 distinct generated Statamic script/stylesheet references returned HTTP 200 with appropriate MIME types and nonempty content |
| Operations client rendering regression | Installed Statamic Vue compiler plus jsdom passed the head-loaded CSS/grid/card/button and literal-note checks |

No application fix was required by these checks. PostgreSQL tests use only the
guarded disposable erepair_test database. Existing local accounts and repair
records were preserved. Working local CMS login is at http://127.0.0.1:8000/cp;
platform management is at /cp/erepair after administrator login.

These results do not establish physical browser screenshots, clicking through the
native editor, responsive layouts or public-host acceptance. The current browser
automation restriction remains; no browser/OS security setting was bypassed.

## Operations stylesheet loading

Statamic 6 compiles Blade CP content with Vue. Its compiler discards style tags
inside that content, which caused the unstyled operations page. The prior HTML-only
HTTP checks proved data/authorization but did not catch this mounting behavior.

CSS now lives at `backend/resources/css/erepair-cp.css`, is published to
`backend/public/vendor/erepair/css/operations.css`, and is registered with
`Statamic::externalStyle` in the document head using a content-hash cache version.
The Composer asset hook publishes it automatically after native CMS assets.
Existing installations can run from backend:

```text
php artisan vendor:publish --tag=erepair-cp-assets --force
php artisan view:clear
```

Then hard-refresh the CP. The operations wrapper uses v-pre so server-escaped user
notes stay literal rather than becoming Vue expressions. No vendor/license code or
transactional permissions changed. A regression uses the installed CP Vue compiler
in jsdom to reproduce dropped inline styles and verify the head-loaded grid/card/
button rules and literal notes. Backend/HTTP checks verify head placement, source
publication and CSS MIME/content. Physical screenshots are not claimed.

Official guidance: [Statamic 6 migration](https://statamic.dev/getting-started/upgrade-guide/5-to-6)
and [CP stylesheets](https://statamic.dev/control-panel/css-javascript).

## Free edition configuration

The shared Laravel application now uses **Statamic Core 6.39.0**, with one genuine
CMS editor and no paid addons identified in the installed application. Native Pro,
REST content API, GraphQL, revisions and Git automation are disabled in configuration;
multisite is disabled too. No vendor code or license checks have been patched.
Core is free for production within its edition limits; hosting and domain costs
are separate. Adding another actual CMS editor or enabling native Pro features
requires a separate edition/license decision.

Official references checked November 2, 2026:
- [Core and Pro licensing](https://statamic.dev/getting-started/licensing).
- [Independent authentication guards](https://statamic.dev/knowledge-base/tips/using-an-independent-authentication-guard).
- [Content queries](https://statamic.dev/frontend/content-queries).
- [Pricing](https://statamic.com/pricing).

## One backend and database; independent authentication

Customers and technicians authenticate with Laravel Sanctum's stateful `web`
guard and `App\Models\User`. The single CMS editor authenticates through `cp`
using `App\Models\CmsUser`. Both repositories use the same PostgreSQL database.
The CMS row links to the application administrator so technician verification,
moderation and activity logs continue using the original application actor ID.
The repository counts its actual CMS table, not a filtered or fabricated count.

The additive migration `2026_11_02_100000_separate_single_cms_identity.php` creates
`cms_users` and distinct CMS token tables. It copies the existing administrator's
password hash once, without deleting, renaming or rewriting application accounts.
It refuses an ambiguous conversion with multiple application administrators.
The database enforces at most one CMS row with a unique true-only singleton flag.
The CLI provisioning service also refuses a second editor or a participant account.

The existing administrator's CP email and password initially remain valid. After
conversion, the two login identities reset their passwords independently. A CP
login does not authenticate `/api/v1/user`; a marketplace login does not grant CP
access. Both guards use Laravel sessions in the same browser; invalidating that
session on logout may log out both. CP middleware checks the linked operator's
active status and administrator role on every protected request.

For a new installation run migrations, then `php artisan erepair:create-admin`.
The command privately prompts for credentials and creates both linked identities
transactionally. The demo seeder does the same only in non-production environments.
Do not seed or import the laptop's demo database in production.

## Operational and editorial features

`routes/cp.php`, `OperationsController` and the native Blade CP layout provide
technician verification, participant management, repair monitoring, service
catalogs, review moderation, statistics and CSV reports. These are Laravel
operations over application models, not native multi-user CMS permission features.
All actors resolve through the linked application administrator. The custom
`activity_logs` table is an application audit trail.

Pages, FAQs, announcements, globals, blueprints and public assets use native Core
content management. Collection revision declarations are disabled. The fixed
public Laravel content projection uses the documented Entry/Global facades and
exposes only published public fields; native Statamic REST/GraphQL are not enabled.
React renders this text safely. Native publication/unpublication and the public
projection are integration-tested with a temporary entry that is then removed.

Native CMS installation and assets still use the pinned Composer lockfile and
`scripts/install-cms-assets.php`. The earlier duplicate two-factor migration remains
a documented no-op; the original shared identity migration owns those fields.
The installed native WebAuthn migration remains in the same PostgreSQL database.
Legacy role/group tables are retained but native multi-user roles are not used.

## Verification and remaining deployment checks

`php scripts/audit-cms-edition.php` reports the actual edition, guards, repository
count and feature flags without printing secrets. On the local database it now
reports Pro false, one CMS user, 27 application accounts and a passing native
Core user-limit check. Earlier reports describing 27 CMS identities are historical.

Core integration tests cover native login, Core pages, operational permissions,
publication, independent authentication, password-reset isolation, linked-operator
suspension, singleton constraints and CLI provisioning. `php scripts/cms-http-smoke.php`
passes real native HTTP/session/asset/report checks. These checks do not certify
physical browser rendering or PWA installation. See `current-verification.md` for
the release gate and remaining hosting/browser/dependency-audit acceptance.
