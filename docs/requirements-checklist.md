# Requirements audit — November 2, 2026

This is an implementation/evidence map, not a statement that all browser and
production checks passed. Exact current test totals are in `current-verification.md`.

| Requirement | Implemented path | Verification / remaining acceptance |
| --- | --- | --- |
| Technician finder is primary | Home search, directory, public profiles, compare, matching service | API/filter/matching and live Vue workflow pass; visual/map acceptance pending |
| Device/problem/location/rating/availability filtering | Form Requests, normalized catalogs, Eloquent queries and rule scores | Backend regression coverage and Vue filter test |
| List/map and optional geolocation | Leaflet/OSM, service-area centroids, manual city/province input | Code and API constraints checked; real tiles/permission UX pending |
| Customer/technician authentication | Sanctum stateful web sessions, registration, reset forms, policies | API and real cookie/CSRF workflow pass |
| Independent single CMS administrator | Native Statamic Core, cp guard and cms_users in shared PostgreSQL | Core/native HTTP, singleton, suspension and reset-isolation tests pass |
| Customer and technician dashboards | Vue dashboard, requests, appointments, notifications, profile, availability/reviews | Component/API and live workflow coverage; physical responsive QA pending |
| Repair request/photo wizard | Five-step form, persisted reference/request, private uploads | Form/rule/API and live photo-request workflow pass |
| Accept/decline/appointment/estimate/approval | Shared Laravel services and valid transitions | Backend lifecycle/conflict/ownership tests and live workflow pass |
| Repair notes/documentation/history/completion | Authorized repair endpoints, append-only history and private downloads | Backend and live completion/notification/review checks |
| Ratings/reviews/moderation | One review per completed repair, public aggregates, native CP moderation | Backend uniqueness/authorization/moderation tests |
| Native administrator operational CMS | Verification, participant management, monitoring, catalogs, reports and activity | Native Core HTTP and integration tests pass; browser navigation/forms pending |
| Editorial CMS | Native collections, globals, blueprints/assets and published Laravel projection | Native publish/unpublish reflected in public content tests |
| One backend and database | Laravel/Statamic in backend; users and cms_users share PostgreSQL | Actual integration/migrations; no separate administrator backend/database |
| Security boundaries | Role/ownership policies, protected uploads, CSRF, validation, throttling and cookie auth | Negative/regression tests; host-specific TLS/cookie/mail acceptance pending |
| Session changes and private client data | Private-query cancellation/clearing, keyed form boundaries, generation checks and logout navigation | Account-switch, expiry, late-response, CSRF, inactive-user and navigation regressions; see session-security.md |
| Regular web application | Installation/PWA requirement removed by user; online browser delivery | Retirement migration and no-install build checks |
| Demo data and Philippine handling | Filipino samples, device catalogs, PHP amounts and Manila display/UTC storage | Seeded local data and workflow/rule tests; demo prohibited in production |
| Dependency installation/locks | Composer and npm lockfiles, repeatable setup and explicit production build | Installed graph/build pass; open build-tool advisories and restricted fresh audit remain |
| Backups and restoration | Snapshot/dump/isolated database restore plus editorial/upload file staging | Fresh local 32-table/43-file drill passed with restricted runtime; encrypted off-host production recovery pending |
| Restricted database runtime | Separate application owner/login without cluster administrative flags; isolated CLI maintenance credentials | Runtime role check, record/hash preservation, native CMS, live writes and fresh 32-table/43-file restore pass |
| Password-reset SMTP delivery | Real transport and delivered-token reset tests for customer and independent Core login | Two loopback SMTP tests pass; real provider/inbox verification remains pending |
| Production configuration | Environment template, Nginx example and read-only production preflight | Safe/unsafe configuration and effective SMTP URL/TLS/provider tests; local demo correctly fails target-host requirements |
| Deployment artifact | Source/static allowlist, no secrets/uploads/caches, SHA-256 manifest | Artifact tamper/path/secret/overwrite tests and generated-package integrity checks |

Subject 1 is covered by Vue, Laravel APIs, PostgreSQL, discovery and the repair
journey. Subject 2 uses native Statamic Core editorial facilities plus its custom
operational Control Panel extension. Core's single actual editor limit remains
enforced; marketplace participants do not become CMS accounts.

Remaining public-release prerequisites are documented in `production-readiness.md`,
`browser-acceptance.md` and `dependency-audit.md`. No production host/domain/mail
credentials are configured in this repository, and no public deployment is claimed.
