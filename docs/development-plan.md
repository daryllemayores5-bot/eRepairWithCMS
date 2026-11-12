# Implementation plan

The application is implemented across all eight phases, with Vue 3 as the canonical frontend in `frontend/` and Laravel/Statamic as the backend. The React source and runtime are retained only as migration references. PostgreSQL, migrations, demo data, backend integration, native CMS checks, Vue build/tests, and the real HTTP repair journey work locally. Remaining acceptance gaps are browser visual/map checks, public hosting configuration, and a fresh dependency advisory audit; see `current-verification.md`. No frontend fixtures or simulated login stand in for API functionality.

| Phase | Deliverables | Acceptance check |
| --- | --- | --- |
| 1: Foundation | Laravel/Statamic/Sanctum dependencies, Vue 3/TypeScript/Vite/Tailwind setup, PostgreSQL configuration, cookie/CSRF integration, installation guide | Dependency install, build, health API, database connection, CP login page |
| 2: Data and identity | Migrations, relationships, factories, sample seeders, customer/technician registration, sessions, password reset, policies | PostgreSQL tests for registration, login, role escalation, isolation, CP permissions |
| 3: Technician finder MVP | Approved directory, profile, filters, transparent matching, compare, city map markers, pagination | API and Vue tests for combined filters, approval visibility, matching order and empty/error/loading states |
| 4: Customer | Dashboard, five-step requests, private uploads, appointments, tracking, notifications, reviews | Complete customer workflow with unrelated-record access rejected |
| 5: Technician | Profile, availability, accept/decline, estimates, progress, documentation | Valid transitions, conflicting appointment checks, estimate approval and authorization |
| 6: Statamic | CP operational extensions, verification, moderation, categories, reports, native CMS content | Same Eloquent records accessible through CP and API; only administrators can manage them |
| 7: Web delivery | Regular online Vue build and safe removal of the former worker | No install metadata or offline cache; normal browser navigation |
| 8: Release | Integration/browser tests, responsive QA, deployment and defense documentation | Full workflow on PostgreSQL, production build, CP access and security checks |

The finder is implemented; its live API flow passes. Further work should close the documented browser visual, accessibility, and map acceptance gaps when the browser environment permits. Do not implement frontend administration; operational administration belongs to native Statamic. Store UTC timestamps; display business dates in Asia/Manila and money in PHP. Label all future sample accounts and repairs as demo data. Never publish demo credentials to production.
