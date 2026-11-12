# Laravel REST API

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


Base: `/api/v1`. Send `Accept: application/json`. Resources wrap single records in `data`; resource pagination supplies `data`, `links`, `meta`. Appointment/notification pagination uses Laravel paginator fields including `current_page` and `last_page`. Validation failures use `message` and `errors`.

All endpoints below have source implementations. The real PostgreSQL suite, live Vue DOM-to-API workflow and installed native CMS content delivery pass. Native CP publication/unpublication is integration-tested. Physical browser rendering remains a separate acceptance check; installation is not required.

## Public and authentication

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/health` | Actual PostgreSQL readiness query; 200 or safe 503 |
| GET | `/lookups` | Active device types, brands, repair categories and specializations |
| GET | `/subscription-plans` | Public active plan comparison: ID, slug, name, description, decimal price, duration in days, features and active flag; sorted by price/ID; no subscriptions, payments, account fields or timestamps |
| GET | `/device-types`, `/specializations` | Individual active lookup lists |
| GET | `/content` | Published Statamic editorial fields; 503 if CMS is not installed |
| GET | `/technicians` | Approved, active public directory |
| GET | `/technicians/{id}` | Approved public profile |
| GET | `/technicians/{id}/reviews` | Published reviews |
| GET | `/technicians/{id}/photo` | Public approved-profile image |
| POST | `/register` | Customer/technician only; creates pending technician profile |
| POST | `/login` | Active-account session login |
| POST | `/forgot-password`, `/reset-password` | Laravel password broker |

Directory parameters: `search`, `city`, `province`, `device_type` ID, `specialization` ID, `repair_category` ID, `availability`, `min_rating`, `sort` (`recommended`, `rating`, `experience`, `distance`) and `page`. Distance sorting requires `latitude` and `longitude`; manual city search works without them. City/province must match together within one service area. An active `repair_category` automatically adds its device/specialization constraints; conflicting explicitly supplied criteria return 422. The same filter validation applies when requesting matching explanations on public profile details. Pending/rejected/suspended technicians cannot be exposed by filters.

## Authenticated active accounts

Subscription routes, payloads, lifecycle and native administrator paths are
documented in [subscription and simulated revenue](subscription-revenue.md).
Public technician resources include `subscription_tier` (`free`, `pro`, `premium`)
and `is_premium`, based on approved payments and exact period dates. The legacy
`is_pro` flag means Pro benefits, including Premium. These are independent of
the existing verification fields and never bypass technician eligibility.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/user` | Explicit current-user fields |
| POST | `/logout` | Invalidate session and rotate CSRF |
| PATCH | `/profile` | Update permitted account name |
| GET | `/dashboard` | Scoped counts, recent requests and appointments |
| GET | `/repair-requests` | Own/assigned requests; `status=all/active/history/pending`, pagination |
| POST | `/repair-requests` | Customer creates request; multipart photos supported |
| GET/PATCH | `/repair-requests/{id}` | Authorized details / customer pending-description edit |
| POST | `/repair-requests/{id}/cancel` | Valid customer cancellation only |
| GET | `/repair-requests/{id}/history` | Authorized status/note timeline |
| PATCH | `/repair-requests/{id}/status` | Actor-specific state machine |
| POST | `/repair-requests/{id}/estimates` | Assigned technician's diagnosis estimate |
| POST | `/repair-requests/{id}/approve-estimate` | Owning customer approves current estimate |
| POST | `/repair-requests/{id}/notes` | Assigned technician adds a repair note; current status, history, notifications and activity log are saved transactionally |
| POST | `/repair-requests/{id}/attachments` | Validated private image/PDF upload |
| GET | `/attachments/{id}` | Policy-protected download |
| GET/POST | `/appointments` | Scoped list / authorized proposal |
| PATCH | `/appointments/{id}` | Authorized proposal update or technician confirmation |
| POST | `/reviews` | Customer's completed repair; unique per repair |
| GET | `/notifications` | Current recipient's database notifications |
| PATCH | `/notifications/{id}/read` | Mark only own notification as read |

Technician endpoints: GET `/technician/dashboard`, `/technician/repair-requests`, `/technician/profile`, `/technician/reviews`; PATCH `/technician/profile`; PATCH `/technician/availability` with `availability=available/busy/unavailable`; GET/POST `/technician/photo`; POST `/technician/repair-requests/{id}/accept` or `/decline`; PATCH `/{id}/status`. Ownership/state validation is shared with the repair services. The technician route group requires a technician account. Changing availability does not cancel existing repair appointments.

Both dashboard endpoints also return `data.activity`: fourteen `{date, count}`
records from the first day through today in Asia/Manila, including real zero days.
Counts reflect requests created on each day using PostgreSQL timezone-aware
timestamps and the same customer/assigned-technician ownership scope as the other
dashboard fields. This is a read-only presentation aggregate. Progress charts
derive from existing `counts`; no chart values are randomized or hardcoded.

The private photo GET serves only the current technician's image, including pending profiles, with no-store headers. Protected profile responses use this URL; public profiles retain the approved-only public photo URL. Photo replacement locks the current database row before changing its path and removes the replaced file after commit. Native CMS administrators can review pending photos through `/cp/erepair/technicians/{id}/photo`; marketplace sessions cannot access this route. Profile updates accept only active device types and specializations, and verification requires active capabilities.

Public map coordinates are returned only as a complete valid pair, rounded to two
decimals. Nearest sorting uses these same rounded points and places missing/invalid
locations last. The native CP-only POST `/cp/erepair/technicians/{id}/location`
accepts required numeric `latitude` (-90..90) and `longitude` (-180..180), stores
only the rounded public point and writes an activity record. This does not verify
the real-world position or change technician approval; the administrator must
check the reference against the profile city/province before saving.

CMS approval now automatically resolves missing map coordinates from the profile
city/province, preserving existing valid manual points. An unresolved location
returns a `decision` validation error rather than approving an unmapped profile.
Approved profile city/province changes automatically update the public point;
unresolved edits return 422 on `city` and preserve the previous profile/location.
Pending profiles remain hidden and their location edits clear old coordinates.
See [technician finder](technician-finder.md) for lookup configuration and existing
approved-profile backfill.

## Request bodies

Registration: `name`, `email`, `password`, `password_confirmation`, `role`; technicians also supply `city`, `province`. Administrator, super and permission injection is rejected.

Registration, login and both password-recovery endpoints normalize string email values to lowercase after trimming. Non-string or malformed email values return field-specific 422 errors; they are never coerced from arrays or passed to authentication/password-broker operations. Form Requests validate all four endpoints.

Repair creation: `technician_profile_id`, `device_type_id`, `device_brand_id`, `repair_category_id`, `model`, optional `serial_number`, `problem_description`, `starts_at`, `ends_at`, `service_location`, optional `photos[]` (at most five JPEG/PNG/WebP images, 5 MB each).

Estimate: decimal `amount`, `description`. Status: `status` and optional `remarks`. Appointment: repair ID when creating, ISO timestamps, service location; maximum four hours, future date and no overlapping proposed/confirmed booking. Review: `repair_request_id`, integer `rating` 1–5, `body`.

## Session security

First request **`GET /sanctum/csrf-cookie` outside v1**, then send credentialed API mutations with the `X-XSRF-TOKEN` header. Browser requests must come from a configured stateful origin; direct API clients must emulate that first-party cookie flow. No bearer-token login or localStorage token persistence is implemented. Login regenerates the session; password reset invalidates existing stored sessions.

Unauthenticated v1 endpoints return JSON 401 even when a client omits the JSON Accept header or requests HTML. API guest redirects are disabled so Laravel never attempts to resolve a nonexistent frontend `login` route. Regression and real frontend-preview HTTP checks cover this behavior.

Current limits: 60 v1 requests/minute per client; 6/minute for authentication/reset mutations. Standard failures: 401 unauthenticated, 403 unauthorized/inactive, 404 unavailable record, 419 expired CSRF, 422 invalid input/state/conflict, 429 throttled and 503 unavailable dependency. Production uses `APP_DEBUG=false`.
