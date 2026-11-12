# Browser acceptance before production

These checks are **pending**. Headless Edge and Chrome both failed during startup under the agent's process restrictions; no browser screenshots or mobile acceptance are claimed. DOM/API and HTTP tests passed, but they do not establish visual behavior, map rendering or actual mobile behavior.

Use the local demo database only. The currently running development app is
**http://127.0.0.1:5173**, with Laravel at `http://127.0.0.1:8000` and native
Statamic at **http://127.0.0.1:8000/cp**. The earlier isolated verification used
5183/8001; these are alternative ports, not the current app URLs. Reproduction
commands are in README. Keep hostnames consistent. Demo account names are in
README; the private password is in ignored `.local/demo-password`.

## Technician finder and responsive UI

The reference-inspired redesign introduces a photo hero, unified blue/white
surfaces and actual published feedback. Automatic approval review rejected the
fresh screenshot browser launch because sandbox approval is unavailable; no
visual verification of these new layouts is claimed.

- [ ] At 320/390px, confirm the landing search appears before the decorative photo, fields use readable text, popular-service links wrap, and CTAs/reviews/FAQs fit without horizontal page overflow.
- [ ] At 768/1024/1366px, inspect the hero/photo overlay, navigation, benefit/technician/review grids, platform illustration and plan cards. Open a long CMS title, long profile name and long review.
- [ ] Confirm the hero WebP loads, published CMS title/FAQ changes appear, device/category switching clears incompatible selections, and real review ratings match the technician profile. Exercise empty/retry states.
- [ ] Inspect native CMS operations after the asset update: navigation, statistics, forms, reports and revenue tables should share the blue palette without obscuring focus/error states.

The November 12 UI pass adds compact mobile navigation/search, mobile repair and
subscription cards, bounded/wrapping fields and buttons, keyboard scroll regions,
short-viewport dialogs and clearer validation/access labels. Functional DOM tests
cover menu dismissal/focus, wizard error recovery, history access states and read
action progress. A fresh headless Edge probe still failed before rendering; these
changes do not mark the physical checks below complete.

- [ ] At 320/390, 768, 1024 and 1366px, inspect both customer and technician dashboards: header/search, collapsed menus, long names, repair cards/tables, three subscription cards and history. Check for page-wide horizontal scrolling.
- [ ] At a short viewport or enlarged text, open subscription confirmation; verify the entire notice and both actions remain reachable by scrolling inside the dialog.
- [ ] With keyboard and touch, test public/workspace menus, invalid sign-in/wizard fields, retries, pagination, long attachment names and data-table scrolling.

- [ ] Inspect the homepage at mobile, tablet and desktop widths (for example 390, 768 and 1366 pixels); confirm navigation, search fields, cards, text and buttons fit without horizontal overflow.
- [ ] Search by device, repair problem, city and province; change filters and sort; inspect loading, empty and error states and result pagination.
- [ ] Compare two or three real profiles; open profile details, service areas and review pages; confirm the chosen technician carries through to the repair form.
- [ ] Open map view with internet access, verify OpenStreetMap attribution and approximate markers. Deny geolocation permission and confirm manual location search remains usable. Residential addresses must not appear publicly.
- [ ] While location permission is pending, change/reset filters or navigate away; a late permission response must not replace the new search. Remove approximate location and confirm other filters and comparisons remain selected.
- [ ] Interrupt a map refresh after results load: the previous results should remain with a warning. Restore connectivity and retry results/map tiles; markers and the chosen map view should remain usable.
- [ ] Navigate forms and menus using the keyboard; inspect labels, focus visibility, validation messages and mobile touch targets.

## Customer, technician and CMS

- [ ] Complete the customer → technician → request/photo → appointment confirmation → estimate approval → repair → pickup/completion → review journey through actual browser controls. The same journey has already passed the live DOM/API test.
- [ ] Test a second customer's access denial, logout and expired-session handling; verify private attachments require the correct authenticated participant.
- [ ] Register a new technician, finish its profile, confirm it remains hidden until native CMS approval, then verify it appears in discovery.
- [ ] Log into native Statamic as administrator, inspect navigation/layout, operational forms, reports, content editing and publish/unpublish. Confirm customer and technician accounts cannot access administration. The endpoint/data checks already pass.

## Former installation cleanup

- [ ] On a browser that previously installed eRepair, refresh online and verify the root worker unregisters and its scoped Workbox precache disappears.
- [ ] Confirm no install buttons/manifest remain and an old /install bookmark redirects home. No open forms should be force-reloaded by cleanup.
- [ ] Confirm normal online navigation and private-response no-store headers. Offline operation is not provided. Existing OS shortcuts can be removed through the browser or OS.

## Production configuration

- [ ] Review the open build-tool advisories in `dependency-audit.md` and rerun a network audit after dependency changes.
- [ ] Configure the real HTTPS origin, secure sessions, supported patched PHP/PostgreSQL, mail transport, private/public storage, backups and verify the single-editor Statamic Core configuration (no Pro license needed).
- [ ] Recheck the same-origin Nginx routes and asset paths on the actual host. The example deployment configuration has not been exercised on a production server.

The automated release gate does not mark these checkboxes complete. Record browser versions, devices, findings and fixes as acceptance evidence before production sign-off.
