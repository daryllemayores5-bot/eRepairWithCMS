# Restricted PostgreSQL runtime and maintenance

The existing loopback eRepair database now uses a separate application login with
no superuser, database-creation, role-creation, replication or RLS-bypass flags.
The previous privileged login remains separate for local CLI maintenance. This
does not create another website administrator or change customer/technician roles.

`backend/scripts/harden-local-database.php` limits itself to the existing local
`erepair` demo database and refuses production, remote connections, missing demo
markers and URL-based connections that could override explicit credentials.
It creates a unique restricted login, transfers only this application's database,
public schema and relations, and removes PUBLIC connect/schema-create grants for
this application database. It does not use cluster-wide `REASSIGN OWNED` or modify
the test database, other project databases or existing cluster roles.

The application login owns its own schema/relations so normal Laravel migrations
remain possible. This is an application database owner, not a cluster administrator.
Production hosts can further separate migration and DML-only runtime roles.

Before activation, the installer compared account/profile/repair/CMS counts and
the administrator password hash through the new connection. The actual run preserved
those records and the application encryption key. A repeated run confirmed the
already non-superuser state without changing credentials again. The production
readiness check now reports `database_role` as passing.

## Private local recovery state

Runtime credentials are in the ignored `backend/.env`. Previous privileged
credentials and an environment rollback copy remain in ignored
`.local/postgres-maintenance.json` and `.local/database-env-before-hardening`.
No values are printed or committed, and neither file is in deployment packages.
These remain sensitive local developer recovery files, not a production secret
manager. Transfer the prepared package instead of the entire workspace.

The privileged login is not the web application's configured connection. Only the
restricted local backup drill reads the maintenance file, after checking its
non-production loopback demo scope. The maintenance helper rejects credentials
for another host/port, other databases, missing/invalid fields and remote sources.
It never rewrites runtime configuration.

## Backup and test separation

`erepair:backup-restore-check` dumps through the restricted application login.
Only creation/removal of its random temporary database uses the separate
maintenance login. The restore database is owned by the runtime login so restoration
requires no runtime CREATEDB grant. The fresh drill passed: 32 tables matched the
exported snapshot, 35 editorial/upload files matched their restored SHA-256 hashes,
and the temporary database was removed.

`backend/.env.testing` remains separate. Disposable `erepair_test` permissions
support guarded schema resets; these are not runtime web credentials and must
never be copied into production. Native CMS HTTP checks and the actual live repair,
appointment, estimate, completion and review workflow pass after the runtime change.
No production database provisioning or public deployment is implied.
