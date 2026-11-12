import assert from 'node:assert/strict'

// Checks actual production-build delivery and API integration, without claiming
// to execute the frontend or a service worker in a browser.
const origin = new URL(process.argv[2] ?? 'http://127.0.0.1:5173')
assert.ok(['127.0.0.1', 'localhost'].includes(origin.hostname), 'Use a loopback preview server only.')

async function request(path, expected = 200) {
  const url = new URL(path, origin)
  assert.equal(url.origin, origin.origin, 'Build references must stay on the application origin.')
  const response = await fetch(url, { signal: AbortSignal.timeout(15000), redirect: 'error' })
  assert.equal(response.status, expected, `${url.pathname}: expected ${expected}, received ${response.status}`)
  return response
}

function passed(message) { process.stdout.write(`[PASS] ${message}\n`) }

try {
  const html = await (await request('/')).text()
  assert.match(html, /id="root"/)
  passed('Built frontend entry document is served')
  const assets = [...html.matchAll(/(?:src|href)="([^"]+)"/g)].map(match => match[1]).filter(path => path.includes('/assets/'))
  assert.ok(assets.some(path => path.endsWith('.js')))
  assert.ok(assets.some(path => path.endsWith('.css')))
  for (const path of assets) assert.ok((await (await request(path)).arrayBuffer()).byteLength > 0)
  passed('Production JavaScript and stylesheet assets are served')
  for (const path of ['/install', '/technicians?city=Santa+Cruz', '/dashboard/repairs/1', '/dashboard/subscription']) {
    assert.equal(await (await request(path)).text(), html, 'Deep routes must receive the public SPA shell.')
  }
  passed('Public and private deep links receive only the static application shell')
  assert.doesNotMatch(html, /rel=["']manifest["']|apple-mobile-web-app-capable/i)
  const retirement = await request('/sw.js')
  assert.match(retirement.headers.get('content-type') ?? '', /javascript|ecmascript/i)
  assert.ok((await retirement.text()).includes('registration.unregister'), 'Old workers must retire without enabling offline caching.')
  passed('Regular web build has no install manifest and serves the legacy worker retirement script')
  const health = await (await request('/api/v1/health')).json()
  assert.equal(health.data.database, 'connected')
  for (const path of ['/api/v1/user', '/api/v1/dashboard', '/api/v1/repair-requests', '/api/v1/technician/subscription', '/api/v1/technician/subscription/analytics']) {
    const privateResponse = await request(path, 401)
    assert.match(privateResponse.headers.get('cache-control') ?? '', /no-store/)
    assert.match(privateResponse.headers.get('content-type') ?? '', /application\/json/i)
    assert.equal(privateResponse.headers.get('x-content-type-options'), 'nosniff')
    assert.equal(typeof (await privateResponse.json()).message, 'string')
  }
  passed('Built preview reaches PostgreSQL through the API proxy and private responses are not cacheable')
  process.stdout.write('Frontend HTTP checks passed. Browser rendering and responsive behavior still require browser verification.\n')
} catch (error) {
  process.stderr.write(`[FAIL] ${error.message}\n`)
  process.exitCode = 1
}
