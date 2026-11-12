# Project scan and repairs — November 11, 2026

The scan covered the active Vue application, Laravel integration tests, native
Statamic HTTP behavior, source/build checks, release packaging and deployment
fixtures. Existing React directories remain preserved migration references.

## Findings fixed

- The workspace PostgreSQL server was stopped. The existing cluster was started
  on port 55432 and completed its automatic recovery. Application data was not
  reseeded. The backend now connects successfully.
- Professional profile and photo saves refreshed only some technician caches.
  Availability updates missed repair-wizard search results. A shared refresh now
  expires private profiles, public profiles/comparison, directory searches,
  featured technicians and repair-wizard matches after these mutations.
- New reviews refreshed repair records but left public ratings and review lists
  cached. Review submission now also expires technician views and review queries.
- Password recovery could submit overlapping requests and show a previous success
  alongside a later error. Pending submissions are guarded and old success text
  is cleared on each new attempt.
- Failed appointment, notification and professional-profile/lookup loads had no
  retry action. They now use the existing recoverable error component.
- The PHP demonstration HTTP client hardcoded port 5173 as the request origin,
  causing CMS participant checks against the isolated port-5183 profile to fail
  with HTTP 419. It now honors `EREPAIR_LIVE_ORIGIN`, validates a root loopback
  HTTP origin and preserves the original default.

## Confirmed checks

- Laravel/PostgreSQL integration: 179 tests, 744 assertions passed against the
  dedicated `erepair_test` database.
- Native CMS: all eleven HTTP checks passed, including participant exclusion,
  independent CMS authentication, assets, content, reporting and logout.
- New password-recovery and technician-cache regressions: both passed.
- Full Vue type checking passed after the fixes.
- Initial Vue baseline: 151 tests passed before the fixes.

Evidence is in ignored `.local/project-scan-*` logs. The final release-gate result
is recorded in `current-verification.md` once complete.

## Remaining acceptance

These checks do not establish physical desktop/mobile rendering, external map tile
delivery, external email delivery or a working public host. The fresh npm audit
attempt failed to reach its advisory endpoint, so no clean dependency audit is
claimed. Existing development-tool advisory findings remain tracked separately.

The local Vue preview uses port 5183 and the scoped Laravel server uses port 8001.
Their origin settings are process-local. No public deployment was performed.
