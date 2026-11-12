# Project audit — November 2, 2026

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


Reviewed the React customer/technician flows, Laravel API controllers, requests,
resources, policies and workflow services, native Statamic operations, PostgreSQL
integration, authentication/session boundaries, upload protection, PWA caching,
installation/release scripts and deployment documentation. This is evidence of
the checks performed, not a guarantee that every defect has been found.

## Reproduced and fixed

1. Pending technician photos used an approved-only public URL. Authenticated
   owners now receive a private photo endpoint and see their current photo in
   profile settings. Public profile URLs remain independent of session identity.
2. Photo replacement could read a stale cached relationship and leave the current
   file orphaned. Replacement now locks and reads the database row, saves the new
   path transactionally, cleans up a failed new upload, and deletes the replaced
   file after commit.
3. Repair notes used potentially stale route-bound status and persisted before
   their notifications. The shared service now locks the repair, checks the active
   assigned technician, and commits history, database notifications and activity
   together. Delivery failure rolls the transaction back.
4. The Statamic verification page lacked pending-photo inspection. Administrators
   now receive protected thumbnails and an independent CP photo endpoint; ordinary
   marketplace sessions cannot access it.
5. The map centered on the first result and could omit other markers or retain
   stale bounds after a same-query refetch. It now fits all located results and
   responds to changed coordinates without resetting on identical data renders.
6. Technician profile updates accepted deactivated device types/specializations,
   and approval accepted profiles with only inactive capabilities. Both paths now
   require active catalog entries. Existing historical relationships are retained.
7. API documentation still said frontend packages were missing. It now reflects
   installed integration and distinguishes automated DOM tests from browser checks.

The initial four photo/note regressions all failed against the previous code.
The two inactive-catalog regressions also failed before their fixes. Eight backend
regression tests now cover these changes and photo authorization. Map and profile
component tests cover bounds updates, missing coordinates and private previews.

## Validation and remaining work

See `current-verification.md` for final suite totals and release evidence. Tests use
the guarded disposable PostgreSQL database; the local demonstration data is kept.

Verified after these fixes: 142 backend tests / 591 assertions, 33 React component
tests, 46 standalone rule tests, six artifact tests, TypeScript and production/PWA
build, eight native CMS HTTP stages and the live React-to-API repair/review flow.

The actual local production preflight still rejects development environment/debug,
HTTP origin, development CORS/Sanctum/session settings, log mail and demo data. The
restricted database runtime check passes. These settings need real deployment
values and a fresh production database; changing the working demo to pretend to
be production would not verify a public host.

The fresh npm audit request failed at the registry endpoint. Previously reported
build-tool advisories remain open in `dependency-audit.md`. Physical browser map,
responsive/accessibility, service-worker and PWA installation acceptance also
remain, as do public HTTPS routing and real mail-provider delivery. No public
deployment, license purchase or production credentials are claimed.
