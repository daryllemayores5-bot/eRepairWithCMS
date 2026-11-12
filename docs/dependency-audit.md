# Dependency audit ? Vue migration, November 11, 2026

The deployment-readiness follow-up retried both live audits. npm again failed to
reach its advisory bulk endpoint; Composer failed to connect to Packagist.
Neither output is an advisory clearance. Existing dev-tool findings below remain
open. No package override, fabricated empty audit, or lockfile change was used
to make a release appear clean.

The frontend manifest and lockfile root now declare the actual supported Node
range required by the locked test CLI: `^22.22.2 || ^24.15.0 || >=26.0.0`.
This is a metadata correction, with no dependency version changes. The connected
Vercel project is already configured for Node 26. The laptop's Node 24.14.0
remains outside that range even though its existing checks pass.

The user-installed Vue dependency graph reported five moderate affected nodes.
The installation audit details are retained in the ignored npm cache log
.local/npm-cache/_logs/2026-11-10T14_49_48_799Z-debug-0.log.

Underlying reported packages:

| Package | Installed | Scope | Advisory |
| --- | --- | --- | --- |
| glob | 13.0.7 | dev-only, through Vue test utilities / js-beautify | https://github.com/advisories/GHSA-3g3m-2fjr-6pw4 |
| minimatch | 10.2.6 | dev-only test-tool graph | https://github.com/advisories/GHSA-g3hv-75wg-m249 |

Both package entries are marked dev-only in the active lockfile. The deployed
frontend is compiled static output and the backend is PHP; the test-tool graph
is not a public Node request handler. This scopes exposure and does not fix the
findings. No unsupported forced overrides or dependency-source edits were applied.

A fresh agent-side npm audit request still fails at the registry. An offline
empty report is not security clearance. Recheck advisories and compatible fixes
from a terminal with registry access before public release, and rerun builds/tests
after any lockfile change.

The transitive test CLI packages nopt 10.0.1 / abbrev 5.0.0 require Node
^22.22.2 || ^24.15.0 || >=26.0.0. The current Node 24.14.0 emits engine warnings;
actual type checks, tests and builds nevertheless pass. Use a supported patched
Node release satisfying that range for reproducible deployment/CI installation.

lucide-vue-next 0.577.0 is deprecated in favor of @lucide/vue. It remains pinned
by the lockfile for the existing icon set during this migration. Deprecation is
not an additional vulnerability finding; a package rename should be validated
against the preserved icon output before changing versions.

The earlier React/PWA dependency reports below are historical and do not describe
the active Vue lockfile.

## November 11 follow-up

A fresh bounded npm audit request failed again at the registry; evidence is
`.local/nov11-vue-audit-followup.json`. The local cache has no glob 14.0.2 or
minimatch 11.0.1 tarballs. These are the patched versions identified in the
[glob advisory](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4) and
[minimatch advisory](https://github.com/advisories/GHSA-g3hv-75wg-m249).

The upstream [Vue Test Utils manifest](https://raw.githubusercontent.com/vuejs/test-utils/main/package.json)
still declares js-beautify ^2.0.0, and the upstream
[js-beautify manifest](https://raw.githubusercontent.com/beautifier/js-beautify/main/package.json)
declares glob ^13.0.6. A forced major replacement is outside that declared range.
No cached safe fix or clean audit is claimed. The active manifest's dependency
ranges match the lockfile root and `npm ls --depth=0` passes.

## Historical record

# Dependency audit — November 2, 2026

Latest attempt, November 5: npm's security-advisory request failed and Composer
could not connect to Packagist (curl error 7), using workspace-owned cache paths.
These failures provide no security clearance. Evidence is in
`.local/nov05-npm-audit.json` and `.local/nov05-composer-audit.stderr`.

## Current graph after installation removal

November 4 hosting follow-up: both audits were retried. npm's advisory endpoint
request failed, and Composer could not connect to Packagist (curl error 7).
Neither result establishes a clean audit. Local evidence is retained under
`.local/npm-audit-current.*` and `.local/composer-audit-current.*`.

The PWA plugin and Workbox were removed at the user's request. The pinned lock graph
now has 252 entries, down from 569, with no changes to remaining package versions.
The previous glob/minimatch/deepmerge/Workbox paths listed below are absent from
this lockfile. This does not establish that the new graph has no advisories; a
fresh registry audit remains pending. npm's virtual-tree/root dependency checks
pass; bundled WASM dependencies are supplied by their original package archive.
Fresh npm lock regeneration attempted to fetch that uncached optional archive, so
the graph was pruned offline using the installed npm Arborist and validated. No
unsupported overrides or package upgrades were introduced. Existing node_modules
may retain unused development packages; deployments include neither node_modules
nor the removed plugin. The original findings below are historical evidence.


Fresh November 3 readiness attempts: npm could not reach its advisory endpoint,
and `composer audit --locked --no-dev --format=json` could not connect to
Packagist. Neither attempt produced a valid clean advisory report. The installed
lockfiles, builds and tests pass, but fresh security-feed acceptance remains open.

The successful npm installation reported **10 affected dependency nodes: 2 low and 8 moderate**. The installation log includes three underlying advisories. `npm explain glob minimatch deepmerge --json` confirms these installed paths are development/build dependencies:

| Package | Installed versions | Dependency path | Advisory |
| --- | --- | --- | --- |
| glob | 11.1.0 | workbox-build | [Abort cancellation](https://github.com/advisories/GHSA-3g3m-2fjr-6pw4) |
| minimatch | 10.2.6; 5.1.9 | glob; filelist | [Pattern amplification](https://github.com/advisories/GHSA-g3hv-75wg-m249) |
| deepmerge | 4.3.1 | @rollup/plugin-node-resolve | [Prototype poisoning](https://github.com/advisories/GHSA-gcqx-w48m-w2h4) |

The glob advisory identifies 14.0.2 as patched; minimatch identifies 11.0.1. Those major versions fall outside the current parents' declared ranges. The deepmerge advisory currently lists no patched version. No forced downgrade, unsupported override or modified dependency source was applied. Such changes need compatibility testing and a refreshed lockfile.

The identified paths build trusted project files; they do not process repair/customer input in the deployed Laravel API. The production frontend serves compiled assets, without a publicly exposed Node build service. This is a scope assessment based on the installed dependency graph, not a claim that the findings are fixed.

A fresh network audit from the agent session failed because registry access is denied. An offline audit returned an empty report because its advisory feed was unavailable; **that result is not evidence of zero vulnerabilities**. The original installation findings remain open. Before production, rerun `npm.cmd --prefix frontend audit --json` from a terminal with registry access, review upstream-compatible fixes and rerun builds/tests after dependency changes. Do not use `npm audit fix --force` blindly.
