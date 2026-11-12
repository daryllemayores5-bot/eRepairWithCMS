import test from 'node:test'
import assert from 'node:assert/strict'
import { mkdtemp, mkdir, readFile, writeFile, rm } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { buildArtifact, verifyArtifact } from '../scripts/lib/release-artifact.mjs'

async function fixture(t) {
  const root = await mkdtemp(join(tmpdir(), 'erepair-release-'))
  t.after(() => rm(root, { recursive: true, force: true }))
  const workspace = join(root, 'source')
  const destination = join(root, 'release')
  const files = [
    ...['artisan', 'please', 'composer.json', 'composer.lock', '.env.example', 'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php', 'public/.htaccess', 'public/robots.txt', 'public/favicon.ico'].map(file => `backend/${file}`),
    ...['app', 'config', 'content', 'resources', 'routes', 'scripts', 'database/migrations', 'database/factories', 'database/seeders'].map(dir => `backend/${dir}/example.php`),
    ...['index.html', 'sw.js'].map(file => `frontend/dist/${file}`),
    ...['nginx.conf.example', '.env.production.example', 'INSTALL.md'].map(file => `deployment/${file}`),
    'scripts/verify-artifact.mjs', 'scripts/verify-host.mjs', 'scripts/lib/release-artifact.mjs',
    'docs/subscription-revenue.md',
    'docs/database-deployment.md', 'docs/database-schema.md', 'docs/database-data-dictionary.md',
    'deployment/render/supervisord.conf',
    'deployment/vercel-static.ignore',
    'render.yaml', '.dockerignore', '.vercelignore', 'deployment/render/Dockerfile', 'deployment/render/nginx.conf.template', 'deployment/render/entrypoint.sh', 'scripts/configure-vercel.mjs', 'scripts/lib/vercel-config.mjs', 'docs/render-vercel.md',
    ...['deployment.md', 'web-delivery.md', 'statamic-integration.md', 'dependency-audit.md', 'browser-acceptance.md', 'current-verification.md', 'free-cms-audit.md', 'production-readiness.md', 'requirements-checklist.md', 'session-security.md', 'database-runtime.md', 'mail-verification.md', 'project-audit.md', 'api-documentation.md', 'technician-finder.md', 'ui-design.md', 'redesign-audit.md', 'deployment-readiness.md'].map(file => `docs/${file}`),
    'backend/.env', 'backend/storage/app/private/customer.png', 'backend/bootstrap/cache/config.php',
    'backend/vendor/secret.php', 'frontend/node_modules/secret.js', '.local/postgres-password',
  ]
  for (const file of files) {
    await mkdir(join(workspace, file, '..'), { recursive: true })
    await writeFile(join(workspace, file), file === 'deployment/vercel-static.ignore' ? 'backend\n**/.env\n**/.env.*\n' : file)
  }
  return { workspace, destination }
}

test('packages only source and static build; excludes credentials, uploads, caches and dependencies', async t => {
  const { workspace, destination } = await fixture(t)
  const manifest = await buildArtifact(workspace, destination)
  assert.equal((await verifyArtifact(destination)).files, manifest.files.length)
  assert.ok(manifest.files.some(file => file.path === 'frontend/dist/sw.js'))
  assert.ok(manifest.files.some(file => file.path === 'backend/.env.example'))
  assert.ok(manifest.files.some(file => file.path === 'docs/subscription-revenue.md'))
  for (const file of ['database-deployment.md', 'database-schema.md', 'database-data-dictionary.md']) {
    assert.equal(await readFile(join(destination, 'docs', file), 'utf8'), `docs/${file}`)
  }
  assert.ok(manifest.files.some(file => file.path === 'deployment/render/supervisord.conf'))
  assert.match(await readFile(join(destination, '.vercelignore'), 'utf8'), /^backend$/m)
  assert.ok(!manifest.files.some(file => /secret|customer|postgres-password|bootstrap\/cache|backend\/\.env$/.test(file.path)))
})

test('detects changed static assets', async t => {
  const { workspace, destination } = await fixture(t)
  await buildArtifact(workspace, destination)
  await writeFile(join(destination, 'frontend/dist/sw.js'), 'changed')
  await assert.rejects(verifyArtifact(destination), /checksum failed/)
})

test('rejects obsolete installation assets from a stale build', async t => {
  const { workspace, destination } = await fixture(t)
  await writeFile(join(workspace, 'frontend/dist/manifest.webmanifest'), '{}')
  await assert.rejects(buildArtifact(workspace, destination), /Removed installation asset/)
})

test('detects unexpected credentials added to the release', async t => {
  const { workspace, destination } = await fixture(t)
  await buildArtifact(workspace, destination)
  await writeFile(join(destination, 'backend/.env'), 'private')
  await assert.rejects(verifyArtifact(destination), /Unexpected file/)
})

test('refuses local development URLs baked into static assets', async t => {
  const { workspace, destination } = await fixture(t)
  await writeFile(join(workspace, 'frontend/dist/index.html'), '<a href="http://127.0.0.1:8000/cp">Admin</a>')
  await assert.rejects(buildArtifact(workspace, destination), /Local development URL/)
})

test('refuses to overwrite an existing release', async t => {
  const { workspace, destination } = await fixture(t)
  await buildArtifact(workspace, destination)
  await assert.rejects(buildArtifact(workspace, destination), /EEXIST/)
})

test('rejects traversal paths in the manifest', async t => {
  const { workspace, destination } = await fixture(t)
  await buildArtifact(workspace, destination)
  const path = join(destination, 'release-manifest.json')
  const manifest = JSON.parse(await readFile(path, 'utf8'))
  manifest.files[0].path = '../outside'
  await writeFile(path, JSON.stringify(manifest))
  await assert.rejects(verifyArtifact(destination), /stay inside/)
})
