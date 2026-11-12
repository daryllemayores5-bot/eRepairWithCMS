# Regular web delivery and legacy installation cleanup

PWA installation was removed at the user's request. eRepair now runs as a regular online Vue/Laravel web application. There is no install prompt/page, web manifest, offline fallback, PWA plugin or new worker registration. Old /install links redirect home.

A small public/sw.js retirement script remains at the previous URL solely so existing workers can update, remove their scoped Workbox precache and unregister. It has no fetch handler and never reloads open forms. The new application also unregisters only the old root /sw.js worker; unrelated registrations/caches are preserved. Keep the existing no-cache Nginx location for this retirement file during migration. Previously installed OS shortcuts are managed by the browser/OS.

Tests exercise actual retirement-script events and guarded browser cleanup. Deployment checks require a normal web build with no stale manifest/offline assets. Physical browser migration remains a manual acceptance check.
