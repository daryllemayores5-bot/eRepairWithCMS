import test from 'node:test'
import assert from 'node:assert/strict'
import { copyFile, mkdir, mkdtemp, readFile, rm, writeFile } from 'node:fs/promises'
import { tmpdir } from 'node:os'
import { join, resolve, sep } from 'node:path'
import { execFile } from 'node:child_process'
import { promisify } from 'node:util'
import { vercelConfig } from '../scripts/lib/vercel-config.mjs'

test('all backend paths use an external rewrite before SPA fallback', () => {
  const config = vercelConfig('https://erepair-api.onrender.com')
  assert.equal(config.outputDirectory, 'frontend/dist')
  assert.equal(config.rewrites.length, 9)
  for (const prefix of ['api', 'sanctum', 'cp', 'vendor', 'storage', 'content', 'img', 'up']) {
    assert.deepEqual(config.rewrites.find(row => row.source === `/${prefix}/:path*`), {
      source: `/${prefix}/:path*`, destination: `https://erepair-api.onrender.com/${prefix}/:path*`,
    })
    assert.equal(config.headers.find(row => row.source === `/${prefix}/:path*`).headers[0].value, 'private, no-store')
  }
  assert.equal(config.rewrites.at(-1).destination, '/index.html')
  assert.equal(config.headers.find(row => row.source === '/sw.js').headers[0].value, 'no-cache')
})

test('rejects credentials, alternate hosts, local addresses and nonroot backend URLs', () => {
  for (const value of [undefined, '', 'http://service.onrender.com', 'https://service.onrender.com.evil.test', 'https://user:secret@service.onrender.com', 'https://service.onrender.com/api', 'https://service.onrender.com?key=secret', 'https://127.0.0.1', 'https://service.onrender.com#fragment', 'https://service.onrender.com:444']) {
    assert.throws(() => vercelConfig(value))
  }
})

test('SPA fallback preserves application links and excludes private paths and static asset misses', () => {
  const config = vercelConfig('https://erepair-api.onrender.com')
  const fallback = new RegExp(`^${config.rewrites.at(-1).source}$`)
  for (const path of ['/', '/install', '/technicians', '/technicians/42', '/dashboard/subscription', '/pages/privacy']) {
    assert.equal(fallback.test(path), true, `Application deep link must retain SPA routing: ${path}`)
  }
  for (const path of ['/.env', '/.git/config', '/.local/demo-password', '/backend/.env', '/backend/composer.json', '/deployment/INSTALL.md', '/scripts/verify-host.mjs', '/tests/fixture', '/docs/private', '/nested/.env', '/assets/missing.js', '/sw.js', '/favicon.ico']) {
    assert.equal(fallback.test(path), false, `Private paths and static assets must not receive the SPA shell: ${path}`)
  }
})

test('compiled release uses its existing assets without nonexistent frontend sources or dependencies', () => {
  const source = vercelConfig('https://erepair-api.onrender.com')
  const compiled = vercelConfig('https://erepair-api.onrender.com', { staticBuild: true })
  assert.equal(compiled.installCommand, '')
  assert.equal(compiled.buildCommand, '')
  assert.equal(compiled.outputDirectory, 'frontend/dist')
  assert.deepEqual(compiled.rewrites, source.rewrites)
  assert.deepEqual(compiled.headers, source.headers)
  assert.equal(source.installCommand, 'npm --prefix frontend ci')
  assert.equal(source.buildCommand, 'npm --prefix frontend run build:production')
})

test('packaged CLI detects static output and refuses to replace reviewed configuration', async t => {
  const root = await mkdtemp(join(tmpdir(), 'erepair-vercel-static-'))
  t.after(async () => {
    assert.ok(resolve(root).startsWith(resolve(tmpdir()) + sep))
    await rm(root, { recursive: true, force: true })
  })
  await mkdir(join(root, 'scripts/lib'), { recursive: true })
  await mkdir(join(root, 'frontend/dist'), { recursive: true })
  await copyFile(new URL('../scripts/configure-vercel.mjs', import.meta.url), join(root, 'scripts/configure-vercel.mjs'))
  await copyFile(new URL('../scripts/lib/vercel-config.mjs', import.meta.url), join(root, 'scripts/lib/vercel-config.mjs'))
  await writeFile(join(root, 'frontend/dist/index.html'), '<div id="root"></div>')
  const run = promisify(execFile)
  const command = [join(root, 'scripts/configure-vercel.mjs'), 'https://erepair-api.onrender.com']
  await run(process.execPath, command, { cwd: root })
  const original = await readFile(join(root, 'vercel.json'), 'utf8')
  const config = JSON.parse(original)
  assert.equal(config.installCommand, '')
  assert.equal(config.buildCommand, '')
  assert.equal(config.outputDirectory, 'frontend/dist')
  await assert.rejects(run(process.execPath, command, { cwd: root }), /already exists/)
  assert.equal(await readFile(join(root, 'vercel.json'), 'utf8'), original)
})

test('container startup never seeds demo accounts and requires durable storage', async () => {
  const entrypoint = await readFile(new URL('../deployment/render/entrypoint.sh', import.meta.url), 'utf8')
  assert.match(entrypoint, /db:seed --class=CatalogSeeder --force/)
  assert.doesNotMatch(entrypoint, /DemoSeeder|migrate:fresh|key:generate/)
  assert.match(entrypoint, /EREPAIR_DURABLE_FILES/)
  assert.ok(entrypoint.indexOf('erepair:production-check --skip-database --json') < entrypoint.indexOf('php artisan migrate'))
  assert.ok(entrypoint.indexOf('initialize-hosted-files') < entrypoint.indexOf('exec /usr/bin/supervisord'))
  assert.ok(entrypoint.indexOf('erepair:production-check --json') < entrypoint.indexOf('exec /usr/bin/supervisord'))
  assert.ok(entrypoint.indexOf('nginx -t') < entrypoint.indexOf('exec /usr/bin/supervisord'))
  assert.ok(entrypoint.indexOf('php-fpm --test') < entrypoint.indexOf('exec /usr/bin/supervisord'))
})

test('container publishes CP assets without overwriting the shared CMS auth and Core configuration', async () => {
  const dockerfile = await readFile(new URL('../deployment/render/Dockerfile', import.meta.url), 'utf8')
  const publishing = dockerfile.split('\n').find(line => line.includes('vendor:publish'))
  assert.match(publishing, /--tag=statamic-cp/)
  assert.match(publishing, /--tag=statamic-frontend/)
  assert.match(publishing, /--tag=erepair-cp-assets/)
  assert.doesNotMatch(publishing, /--tag=statamic(?:\s|$)/)
  assert.match(dockerfile, /composer install .*--no-scripts/)
})

test('container supervises the real scheduler and foreground web processes with grouped shutdown', async () => {
  const config = await readFile(new URL('../deployment/render/supervisord.conf', import.meta.url), 'utf8')
  const blocks = Object.fromEntries([...config.matchAll(/\[([^\]]+)\]\s*([^\[]*)/g)].map(([, name, body]) => [name, body]))
  assert.match(blocks.supervisord, /nodaemon=true/)
  assert.match(blocks['program:php-fpm'], /command=\/usr\/local\/sbin\/php-fpm --nodaemonize/)
  assert.match(blocks['program:nginx'], /command=\/usr\/sbin\/nginx -g 'daemon off;'/)
  assert.match(blocks['program:scheduler'], /command=\/usr\/local\/bin\/php artisan schedule:work --no-interaction/)
  assert.match(blocks['program:scheduler'], /user=www-data/)
  assert.match(blocks['program:queue'], /queue:work database .*--timeout=25/)
  assert.match(blocks['program:queue'], /user=www-data/)
  for (const name of ['program:php-fpm', 'program:scheduler', 'program:queue', 'program:nginx']) {
    assert.match(blocks[name], /autorestart=true/)
    assert.match(blocks[name], /stopasgroup=true/)
    assert.match(blocks[name], /killasgroup=true/)
    assert.match(blocks[name], /stdout_logfile_maxbytes=0/)
    assert.match(blocks[name], /stderr_logfile_maxbytes=0/)
  }
  const dockerfile = await readFile(new URL('../deployment/render/Dockerfile', import.meta.url), 'utf8')
  assert.match(dockerfile, /nginx supervisor/)
  assert.match(dockerfile, /COPY deployment\/render\/supervisord.conf \/etc\/supervisor\/erepair.conf/)
  assert.match(dockerfile, /docker-php-ext-install[^\n]+pcntl/)
  const blueprint = await readFile(new URL('../render.yaml', import.meta.url), 'utf8')
  assert.match(blueprint, /key: QUEUE_CONNECTION\s+value: database/)
})
