# eRepair interface design

## Reference-inspired system theme — November 12, 2026

The supplied blue-and-white repair landing reference now guides the active Vue
system: cobalt actions, pale blue surfaces, compact rounded cards, soft shadows,
an underlined public navigation state, a navy footer and blue workspace/sidebar
accents. `frontend/src/redesign.css` extends the existing styles without changing
the preserved base stylesheet. Shared buttons, fields, cards, tables, auth
story panels and workspace surfaces carry the theme across application routes.
Native eRepair CMS operations receive the same palette in the scoped operations
stylesheet, published through the existing asset tag; native Statamic controls
and the Free/Core edition are retained.

The landing page has a repair-photo hero/popular services panel, real catalog
search, benefits, actual available technician cards, repair steps, a decorative
workspace/device preview, customer/technician actions, published customer reviews,
service guides, database-backed plans and editable CMS FAQs/announcements. Hero
copy remains CMS-controlled. Reviews are fetched from the featured technicians'
existing public review APIs, sorted by date and displayed with their real rating
and author; empty/error/retry states do not invent testimonials or statistics.

The hero photograph is an AI-generated illustrative repair scene, not a directory
technician's identity or a customer testimonial. The locally hosted
`frontend/public/images/repair-technician.webp` is 121,964 bytes, converted from
the generated PNG without changing its subject. Original artwork is retained
outside the public/release tree in ignored `.local/repair-technician-original.png`.

At phone widths the search moves ahead of the decorative hero image; its fields
stack with 16px text. Tablet/desktop grids, floating repair links, review cards,
CTA panels and FAQ columns collapse at defined breakpoints. Public navigation
collapses below 1024px, with its existing Escape/focus behavior preserved. Wider
repair/history tables retain bounded scroll regions and existing mobile cards.
DOM tests and builds pass; real viewport screenshots/mobile acceptance remain
pending because automatic approval review rejected the browser launch.

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


The UI uses Vue 3, Tailwind CSS and the existing shadcn-style component system.
The product emphasis is technician discovery, followed by a connected repair
workspace. All counts, appointments, profiles and requests come from the Laravel
API. Decorative marketing illustrations explain the process without presenting
invented technicians, reviews, repair activity or business statistics.

## Responsive and clarity improvements — November 12, 2026

The dashboard header separates its search field and account controls below 640px,
with a compact workspace menu below 1024px. Public/workspace menus support Escape
and return keyboard focus to their toggle. Navigation closes when routes change.
Repair lists display participant/status/date cards below 768px, with the existing
scrollable table retained at larger widths. Subscription history uses detailed
cards below 1024px and a keyboard-focusable table above that breakpoint. Plan
comparison cards use one column on phones, two from 768px and three from 1280px.

Shared controls have a 44px minimum button height, bounded widths and wrapping
labels. Inputs/selects use 16px text on phones and do not impose intrinsic widths
on their containers. `frontend/src/ui.css` bounds content and permits long names,
device models, references and attachment filenames to wrap. Grid columns use
`minmax(0, ...)` where needed. Wide data scrolls inside labeled regions instead
of being hidden through page-wide overflow clipping. The confirmation dialog
scrolls within the viewport on short screens.

The repair wizard focuses invalid controls and newly opened step headings, and
matching-technician errors offer retry without clearing the form. Validation
descriptions no longer become part of the sign-in/problem field labels. A zero
review count reads “No reviews yet.” Notification read actions show progress,
block repeated submissions and recover after errors. Subscription history
distinguishes pending approval, scheduled access, rejected access and historical
cycles replaced by renewal. Expired paid access clearly returns to Free.

DOM interaction tests, source checks and production compilation cover these
changes. This is not physical viewport acceptance: a fresh Edge headless probe
failed before rendering with `GPU process isn't usable`; Chrome produced no
screenshot. Actual phone/tablet/browser layout checks remain in
[browser acceptance](browser-acceptance.md).

## Visual language

The landing page now shares the customer/technician workspace's navy-and-blue
palette, card radius, shadows, button sizing and field styles. It uses a compact
hero, prominent real device/problem/location search, service cards, available
technician profiles, customer/technician entry panels and CMS-backed FAQs and
announcements. Decorative journey content is desktop-only so mobile visitors
reach search sooner. Workspace links respect the current user role. Device
changes clear a previously selected incompatible problem.

LandingPage.vue handles the page composition. ContentPage.vue and
HowItWorksPage.vue retain the existing public content routes. The content
composable shares the same API/query cache across these pages. The Vue migration
preserved backend and authentication business rules and replaced framework-specific
libraries with their Vue equivalents.

The complete-system pass adds Primary Navy `#0B1F41`, Sidebar Navy `#0A2147`,
Success `#16A34A`, Warning `#F59E0B` and Danger `#EF4444` as shared Tailwind tokens.
Authenticated workspaces have a dark navy sidebar and compact white top navigation;
public pages retain discovery-oriented navigation and a matching navy footer.
The supplied dashboard screenshot now guides the workspace composition: a
full-height navy sidebar, dedicated white search bar, compact breadcrumb,
six technician metrics, blue area chart, status ring, recent requests and balanced
appointment/profile panels. Public pages retain their existing navigation.
See `redesign-audit.md` for the full route inventory and changed files.

The workspace search submits to the real technician directory. The shared
MetricCard displays actual counts and rating, without invented growth figures.
The area chart connects the API's fourteen daily counts; its accessible data table
remains available. The ring continues to show the actual total and distribution,
without copying the reference image's example percentage. Technician tables show
the customer; customer tables show the assigned technician. Mobile navigation
remains collapsible, tables scroll within their cards, and keyboard focus and
reduced-motion behavior remain supported. The existing sign-out navigation
handshake stays in SiteLayout so the new shell does not bypass authentication.

- Inter typography; compact uppercase section labels, clear page titles and
  comfortable body line heights.
- Blue `#2563EB` for primary actions, slate `#0F172A` for headings, muted slate
  `#64748B`, page background `#F8FAFC`, white surfaces and `#E2E8F0` borders.
- Consistent rounded cards, restrained border/shadow treatments and generous
  section spacing. Dark slate appears selectively in the public journey card.
- Emerald for positive status, amber for waiting states, blue for active states,
  neutral slate for inactive/closed states and red for rejection/destructive actions.
  Status text remains visible so meaning does not depend on color alone.
- Hover/focus treatments on actionable elements, clear primary/secondary buttons,
  touch-friendly controls and reduced-motion support.

## Shared components and layouts

Card dimensions, title/description typography and spacing now live in the Card
components rather than unlayered page overrides. This allows deliberate variants
and hover shadows to work predictably. Both Card and surface-card use the shared
surface-shadow token. The public shell, workspace shell and PWA launch background
use the same pale background color. Single-line inputs/selects share a 44px minimum
control height and common disabled/validation treatment; textareas retain their
content height. Profile and repair form section titles use the shared section
heading style. Marketing headings keep their intentional larger hierarchy.

The `erepair-platform` root supplies the same surface/shadow/typography rules to
public and workspace routes. Dashboard styling is no longer the only scope for
shared cards. Homepage, directory, authentication and wizard surfaces use the same
surface-card component class; forms, legal/editorial content, reviews, history,
install/connectivity and empty/error states inherit the shared design. Auth pages
have a navy introductory panel and white form card. Pagination is one accessible,
responsive navigation surface across lists. See the route-area matrix in
`redesign-audit.md` for whole-project coverage.

Operations CSS is published from `backend/resources/css/erepair-cp.css` and loaded
through Statamic's external stylesheet API in the document head. Do not put style
tags inside the Vue-mounted Blade body; Vue discards them. The native CP compiler
regression covers this behavior and literal server-rendered notes.

The `components/ui/Card*.vue` family supplies Card, CardHeader, CardTitle, CardDescription and
CardContent. `PageHeading` supplies page hierarchy and action placement. Buttons,
inputs, empty states and status badges share the same visual rules. Base border
styles live in Tailwind's base layer; unlayered resets no longer override utility
border colors or button font weights.

The dashboard presents a greeting, real metric cards, a readable request table,
upcoming appointments and one focused guidance card. Narrow screens stack the
layout; wide tables scroll horizontally. Technician metric cards receive enough
space rather than squeezing five cards into a single narrow row. There are no
invented trend charts or percentages.

The homepage has a prominent search form and a code-rendered repair-journey card.
Sign-in/registration uses a calm two-column introduction on large screens and a
focused single-column form on phones. Profile and repair forms reuse page headings,
inputs and surfaces. Existing menu, filter, comparison and map controls remain.

## Validation and limits

Both roles now have real 14-day activity charts and status-distribution charts.
The new scoped read aggregate groups existing PostgreSQL timezone-aware records by
Manila calendar day; ownership and midnight-boundary tests pass. SVG charts have
accessible labels and an expandable activity data table. Missing/empty data shows
an explicit empty state, and no chart library or other dependency was added.

Existing public, authentication, customer and technician routes have 33 automated
route/role rendering cases. The native CMS operations style is scoped to its custom
Blade extension; built-in Statamic controls and editorial data are preserved.

TypeScript, component tests, production/PWA build, HTTP delivery and the actual
Vue-to-Laravel repair journey are checked by `scripts/verify-release.ps1 -LiveApi`.
No backend business rules, cookie-session boundaries or map privacy behavior were
replaced by this styling pass. The final counts are in `current-verification.md`.

Physical browser rendering, responsive screenshots, keyboard/accessibility checks,
map tile delivery and OS PWA installation remain in `browser-acceptance.md`.
Automated DOM tests are not claimed as visual/browser acceptance.
