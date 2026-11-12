# Vue frontend

This is the active eRepair Vue 3 frontend. It uses script-setup SFCs, Vue Router,
Pinia, Vue Query, Axios, Zod, Tailwind/CSS, Lucide Vue, Vue Sonner and Leaflet.
Laravel, native Statamic and PostgreSQL remain in the existing backend.

From the workspace root:

```powershell
npm.cmd --prefix frontend run typecheck
npm.cmd --prefix frontend test
npm.cmd --prefix frontend run build:production
npm.cmd --prefix frontend run dev
```

The verified alternate local preview is http://127.0.0.1:5183 with its scoped
Laravel server on port 8001. See [local development](../README.md) for the exact
commands and [migration verification](../docs/vue-migration.md) for scope/results.

Original React source and runtime are preserved in `frontend-react-reference`
and `frontend-react-runtime`. They are not active or shipped in releases.

Independent source/shared-module checks are also available:

```powershell
node scripts/check-vue-source.mjs
node scripts/typecheck-vue-core.mjs
node scripts/verify-react-reference.mjs
```

The main typecheck/tests/build and real demo journey have now run with the actual
Vue integration packages installed. Physical browser/mobile acceptance and fresh
security-advisory clearance remain separate release requirements.
