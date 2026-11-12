import { createHash } from 'node:crypto'
import { lstat, mkdir, readFile, readdir, writeFile } from 'node:fs/promises'
import { join, resolve, sep } from 'node:path'

const sourceDirectories = ['app', 'config', 'content', 'resources', 'routes', 'scripts', 'database/migrations', 'database/factories', 'database/seeders']
const sourceFiles = ['artisan', 'please', 'composer.json', 'composer.lock', '.env.example', 'bootstrap/app.php', 'bootstrap/providers.php', 'public/index.php', 'public/.htaccess', 'public/robots.txt', 'public/favicon.ico']
const forbidden = /^(?:\.env(?:\..+)?|auth\.json|.*\.(?:log|key|pem|sqlite|sql|dump|tsbuildinfo))$/i
const allowedExamples = new Set(['backend/.env.example', 'deployment/.env.production.example'])

function safePath(base, name) {
  const target = resolve(base, name)
  if (target === resolve(base) || !target.startsWith(`${resolve(base)}${sep}`)) throw new Error('Artifact path must stay inside its root.')
  return target
}

async function walk(root, prefix = '') {
  const files = []
  for (const entry of await readdir(join(root, prefix), { withFileTypes: true })) {
    const name = prefix ? `${prefix}/${entry.name}` : entry.name
    if (entry.isSymbolicLink()) throw new Error(`Symbolic links are not allowed in release files: ${name}`)
    if (entry.isDirectory()) files.push(...await walk(root, name))
    else if (entry.isFile()) files.push(name)
    else throw new Error(`Unsupported release file: ${name}`)
  }
  return files.sort()
}

function validateName(name) {
  const parts = name.split('/')
  if (parts.some(part => ['node_modules', 'vendor', '.local', '.git', 'storage'].includes(part))) throw new Error(`Runtime/dependency directory is not distributable: ${name}`)
  if (!allowedExamples.has(name) && parts.some(part => forbidden.test(part))) throw new Error(`Sensitive or runtime file is not distributable: ${name}`)
}

export async function buildArtifact(workspace, destination) {
  // Exclusive directory creation prevents replacing an existing release.
  await mkdir(destination, { recursive: false })
  const manifest = { format: 1, createdAt: new Date().toISOString(), files: [] }
  async function copy(source, name) {
    validateName(name)
    if (!(await lstat(source)).isFile()) throw new Error(`Expected a regular source file: ${name}`)
    const contents = await readFile(source)
    // Bare localhost origins occur inside React Router/Axios URL constructors.
    // Reject addresses with a port/path, including actual development CP/API URLs.
    if (name.startsWith('frontend/dist/') && /\.(?:js|html|json|webmanifest|css)$/.test(name) && /https?:\/\/(?:localhost|127\.0\.0\.1|0\.0\.0\.0)(?=:\d|\/)/i.test(contents.toString())) {
      throw new Error(`Local development URL found in production asset: ${name}`)
    }
    const output = safePath(destination, name)
    await mkdir(resolve(output, '..'), { recursive: true })
    await writeFile(output, contents, { flag: 'wx' })
    manifest.files.push({ path: name, bytes: contents.length, sha256: createHash('sha256').update(contents).digest('hex') })
  }
  for (const file of sourceFiles) await copy(join(workspace, 'backend', file), `backend/${file}`)
  for (const directory of sourceDirectories) {
    const root = join(workspace, 'backend', directory)
    if (!(await lstat(root)).isDirectory() || (await lstat(root)).isSymbolicLink()) throw new Error(`Expected source directory: ${directory}`)
    for (const file of await walk(root)) await copy(join(root, file), `backend/${directory}/${file}`)
  }
  const dist = join(workspace, 'frontend/dist')
  if (!(await lstat(dist)).isDirectory() || (await lstat(dist)).isSymbolicLink()) throw new Error('Expected a regular frontend build directory.')
  for (const required of ['index.html', 'sw.js']) await lstat(join(dist, required))
  for (const file of await walk(dist)) {
    if (['manifest.webmanifest', 'offline.html', 'registerSW.js'].includes(file)) throw new Error(`Removed installation asset found in build: ${file}`)
    await copy(join(dist, file), `frontend/dist/${file}`)
  }
  for (const file of ['nginx.conf.example', '.env.production.example', 'INSTALL.md']) await copy(join(workspace, 'deployment', file), `deployment/${file}`)
  for (const file of ['render.yaml', '.dockerignore', 'deployment/render/Dockerfile', 'deployment/render/nginx.conf.template', 'deployment/render/entrypoint.sh', 'scripts/configure-vercel.mjs', 'scripts/lib/vercel-config.mjs', 'docs/render-vercel.md']) await copy(join(workspace, file), file)
  await copy(join(workspace, 'scripts/verify-artifact.mjs'), 'scripts/verify-artifact.mjs')
  await copy(join(workspace, 'scripts/verify-host.mjs'), 'scripts/verify-host.mjs')
  await copy(join(workspace, 'scripts/lib/release-artifact.mjs'), 'scripts/lib/release-artifact.mjs')
  await copy(join(workspace, 'docs/subscription-revenue.md'), 'docs/subscription-revenue.md')
  for (const file of ['database-deployment.md', 'database-schema.md', 'database-data-dictionary.md']) await copy(join(workspace, 'docs', file), `docs/${file}`)
  await copy(join(workspace, 'deployment/render/supervisord.conf'), 'deployment/render/supervisord.conf')
  await copy(join(workspace, 'deployment/vercel-static.ignore'), '.vercelignore')
  for (const file of ['deployment.md', 'web-delivery.md', 'statamic-integration.md', 'dependency-audit.md', 'browser-acceptance.md', 'current-verification.md', 'free-cms-audit.md', 'production-readiness.md', 'requirements-checklist.md', 'session-security.md', 'database-runtime.md', 'mail-verification.md', 'project-audit.md', 'api-documentation.md', 'technician-finder.md', 'ui-design.md', 'redesign-audit.md', 'deployment-readiness.md']) await copy(join(workspace, 'docs', file), `docs/${file}`)
  // These directories are populated only by Composer/Laravel on the destination.
  for (const directory of ['bootstrap/cache', 'storage/app/private', 'storage/app/public', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/statamic']) await mkdir(join(destination, 'backend', directory), { recursive: true })
  manifest.files.sort((a, b) => a.path.localeCompare(b.path))
  await writeFile(join(destination, 'release-manifest.json'), `${JSON.stringify(manifest, null, 2)}\n`, { flag: 'wx' })
  return manifest
}

export async function verifyArtifact(root) {
  const actual = await walk(root)
  const manifest = JSON.parse(await readFile(join(root, 'release-manifest.json'), 'utf8'))
  if (manifest.format !== 1 || !Array.isArray(manifest.files) || !manifest.files.length) throw new Error('Unsupported or empty release manifest.')
  const expected = new Set(['release-manifest.json'])
  for (const file of manifest.files) {
    validateName(file.path)
    if (expected.has(file.path)) throw new Error('Duplicate release manifest entry.')
    expected.add(file.path)
    const target = safePath(root, file.path)
    if (!(await lstat(target)).isFile()) throw new Error(`Not a regular release file: ${file.path}`)
    const bytes = await readFile(target)
    if (bytes.length !== file.bytes || createHash('sha256').update(bytes).digest('hex') !== file.sha256) throw new Error(`Release checksum failed: ${file.path}`)
  }
  for (const name of actual) if (!expected.has(name)) throw new Error(`Unexpected file in release: ${name}`)
  if (actual.length !== expected.size) throw new Error('Release files are missing.')
  return { files: manifest.files.length, bytes: manifest.files.reduce((sum, file) => sum + file.bytes, 0) }
}
