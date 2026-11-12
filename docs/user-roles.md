# Roles and access design

| Role | Interface | Ownership and limits |
| --- | --- | --- |
| Public | Vue public pages | Approved public technician profiles only; no repair/private account data |
| Customer | Vue | Own profile, requests, appointments, notifications; review own completed repair once |
| Technician | Vue technician area | Own professional profile and availability; only assigned requests and appointments; approval required for directory visibility |
| Administrator | Statamic CP | Explicit operational permissions, verification, moderation, reports and editorial content |

Customers and technicians use the Laravel `web` guard and `users` table. One linked CMS identity in `cms_users` uses the independent `cp` guard and native Statamic Core. Both use the same backend and PostgreSQL database. Native CP login never grants a marketplace API session, and marketplace login never grants CP access. The CMS middleware rechecks the linked operator's administrator role and active status on every request. Neither role nor super is mass assignable on the application User model. Native login, operational authorization and publication are tested; physical browser acceptance remains outstanding.

Registration validates its allowed role list, rejects administrator/permission fields, hashes passwords and creates technician profiles as pending. UI route guards improve navigation but never replace API policies. Administrator verification uses a shared service, audit record and explicit privilege checks. Suspended accounts lose API access and public visibility; CP account suspension also invalidates stored sessions. Email changes are deliberately not exposed without a verification workflow.

Suspended marketplace participants retain access to authenticated logout so they
can invalidate their sessions. Private API rejection includes a stable inactive
account code. The SPA clears private queries and drafts on account changes or
expiration; see [session privacy](session-security.md).
