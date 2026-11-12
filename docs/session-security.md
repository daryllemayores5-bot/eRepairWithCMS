# SPA session privacy and logout verification

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


## Fixed account-boundary issue

The React application originally reused private query keys such as `dashboard`
and `repair` after a different account signed in. Backend ownership checks were
still enforced, but previously fetched private data could remain in client memory
and be reused by the next account. A regression test reproduced this issue before
the fix; additional tests reproduced a competing logout redirect.

`src/lib/session-cache.ts` now identifies the public directory/editorial query
keys. Other keys are treated as private by default. Identity changes and expired
sessions cancel private requests, remove their cached responses and clear mutation
state. Late responses from canceled queries cannot refill the old private cache.
Public directory/content caches can remain. Authentication uses only Laravel's
session/CSRF cookies; no browser token persistence was added.

Successful login/registration accepts the actual API user response only after
canceling older identity/private requests. The protected outlet is keyed to the
user ID, so an account change also discards component-local drafts and selected
private upload files. Same-user profile refreshes retain valid current-user data.

## Expired sessions and inactive accounts

The Pinia store's shared session controller tracks request generations internally without transmitting them
as headers. A protected 401 or an explicitly coded inactive-account 403 clears
the current local session. An ordinary forbidden repair record does not sign the
user out. Late unauthorized responses from an earlier identity cannot terminate
the newly authenticated identity. Interceptors are removed on store-scope disposal.

A protected CSRF 419 rechecks the actual server identity rather than assuming
every token mismatch means logout. Identity also revalidates on window focus.
These checks are client privacy measures; Laravel policies and authentication
remain the authority for each server request.

Inactive participants can now access the authenticated logout endpoint to invalidate
their session, while private records still return 403 with `account_inactive`.
Explicit logout treats an already expired 401 session as signed out, but reports
other network/server failures instead of pretending server logout succeeded.

## Explicit logout navigation

React Router's deferred navigation could race the private route's automatic guest
redirect, leaving the user at `/login` even after choosing a successful return to
the homepage. The logout boundary now remains pending until the public route
actually commits. The Vue implementation awaits Vue Router's navigation promise
while holding the same pending boundary. Repeated sign-out clicks are disabled during that transition.
A regression test failed before this change and passes with the route-commit
acknowledgment. No arbitrary navigation delays or full-page reloads were added.

## Evidence

Eleven Pinia authentication integration tests and fifteen transport/cache tests cover identity changes, expiration, late private
responses, successful login, protected API rejection, ordinary versus inactive
403s, CSRF revalidation and explicit logout navigation. A protected-route test
also verifies that the previous user's unsaved private note is removed on account
change. Backend tests verify inactive-user logout and safe JSON diagnostics for
configuration failures.

Live workflow tests use real cookies, CSRF, APIs, database records and uploads.
Acceptance runs choose a slot beyond actual existing technician appointments to
avoid collisions with retained demo history. Failure cleanup authenticates the
test customer explicitly and targets only its own uniquely labeled repair.
Browser responsiveness, actual PWA behavior, live HTTPS/mail and production-host
checks remain separately documented acceptance requirements.
