# Compatibility investigation

> Current scope: PWA installation was removed at the user's request. eRepair is a regular online web application. Older PWA results below are historical evidence, not current features or deployment requirements. See [web delivery](web-delivery.md).


Checked 2026-10-30 before adding application dependencies. Official documentation establishes compatible major versions; dependency resolvers and platform checks must establish the exact installed set. Cached metadata is not proof of the latest release available today.

Resolved and verified locally on 2026-11-02: **Laravel 13.39.0, Statamic 6.39.0, Sanctum 4.3.3 and native Inertia 3.6.0**, on the agent's PHP 8.4.12; the user's successful installation used PHP 8.4.25. The installed Statamic manifest supports Laravel 13 and Inertia 3. Frontend lockfile versions are **React 19.3.1, TypeScript 7.0.2, Vite 8.3.5, Tailwind 4.3.3, Vitest 4.1.12 and Vite PWA Plugin 2.0.0**. Compiler, component tests, production build and real DOM/API integration pass. React remains a separate REST SPA. The table below records the original selection; PostgreSQL now runs on workspace loopback port 55432.

| Component | Selected line | Evidence / local finding |
| --- | --- | --- |
| PHP | 8.4 | Installed CLI 8.4.12; Laravel 13 requires PHP >=8.3 |
| Laravel | 13 stable | Official Laravel 13 starter recovered from local Composer cache |
| Statamic | 6 stable, >=6.5 | Official docs support Laravel 12 and 13; 6.5 introduced Laravel 13 support |
| Sanctum | 4 stable | Composer must resolve against Laravel 13 |
| React | 19.3 | Official React versions page identifies 19.3 as current |
| Vite | 8 stable | Node 24.14 satisfies the official >=20.19 / >=22.12 tooling baseline |
| Tailwind | 4.3 | Official Vite plugin integration; CSS-first configuration |
| shadcn/ui | Current source component conventions | Vite + Tailwind 4 documented; no separate runtime framework |
| PostgreSQL | Supported maintained deployment release | Local client 14.5 exists; no server responded on default port; PHP initially lacks pdo_pgsql |

Sources:
- https://laravel.com/docs/13.x/releases
- https://statamic.dev/getting-started/installing/laravel
- https://statamic.com/blog/statamic-supports-laravel-13
- https://statamic.dev/knowledge-base/tips/storing-users-in-a-database
- https://react.dev/versions
- https://vite.dev/guide/
- https://tailwindcss.com/docs/installation/using-vite
- https://ui.shadcn.com/docs/installation/vite

Do not use a Laravel Inertia starter: React is a separate REST SPA and Statamic has its own Control Panel frontend. Let Statamic select its compatible Inertia dependency. Do not replace required dependencies when offline installation fails.
