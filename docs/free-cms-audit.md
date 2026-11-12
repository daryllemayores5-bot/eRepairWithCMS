# Free CMS conversion — November 2, 2026

## Implemented result

The project is now configured for **free Statamic Core**, with a separate, single
CMS identity in the same PostgreSQL database as the Laravel application. Pro and
its native APIs/revisions/Git automation are disabled. The actual vendor user-limit
middleware passes without modification. No commercial license has been purchased.

| Check | Before conversion | After conversion |
| --- | --- | --- |
| Application accounts | 27 | 27, preserved |
| Application administrators | 1 | 1, preserved |
| Native CMS repository model | App\Models\User | App\Models\CmsUser |
| Native CMS repository accounts | 27 | 1 |
| CMS authentication guard | web | cp |
| Native Pro mode | enabled trial | disabled |
| Native Core user-limit check | blocked | passed |
| Native content API / GraphQL / revisions / Git / multisite | disabled | disabled |

The original administrator password hash was preserved and copied once into the
new CMS login. Future password resets are independent. Customers and technicians
remain Laravel marketplace users, with the same repair records and policies. This
is still one Laravel backend and one PostgreSQL database. The linked CMS identity
is the same human administrator, not an additional administrator/person.

## Supported basis

A Statamic maintainer recommends independent guards for this exact one-CMS-user,
many-Laravel-user arrangement in [the project discussion](https://github.com/statamic/cms/discussions/14103).
The [official independent-guard documentation](https://statamic.dev/knowledge-base/tips/using-an-independent-authentication-guard)
explains the separation. The implementation uses Eloquent storage for both models
to meet the shared-database requirement. It does not suppress the native user-limit
middleware, falsify its count, or grant customers CMS access.

[Core licensing](https://statamic.dev/getting-started/licensing) permits free use
within its single-editor and other edition limits. Native Pro functionality remains
disabled. The application-specific public content projection reads published fields
using documented [content-query APIs](https://statamic.dev/frontend/content-queries);
it does not enable the native Statamic content API or GraphQL service. Core supports
the underlying collections, globals, blueprints, content queries and assets.

## Reproduction

From `backend`, with PostgreSQL PDO enabled, run:

```text
php artisan migrate
php scripts/audit-cms-edition.php
```

The audit is read-only, prints counts/flags rather than credentials, and returns
zero when the native Core user-limit check passes. Exit code 2 means that check
is blocked. The earlier audit correctly found the old unseparated repository's
27-user blocker; it has now been resolved by the independent identities/guards.

The migration rejects ambiguous existing multiple administrators. The CMS table
has a database constraint enforcing one editor. `erepair:create-admin` provisions
one linked administrator transactionally and refuses another. The native Core
configuration has no paid Pro toggle to enable accidentally through an old .env.
Do not deploy the older Pro release package after this conversion.

## Verification scope

Native Core authentication, CMS operations and published-content HTTP checks pass.
Dedicated regression tests exercise the unchanged native user counter, independent
guards, password-reset token isolation, operator suspension, single-editor database
constraints and fresh administrator provisioning. Existing operational/publication
tests run under Core. See the latest verification report for exact suite totals.
Physical browser/PWA acceptance, hosting, HTTPS and outstanding npm build-tool audit
findings remain separate deployment tasks. A free CMS does not imply free hosting.
