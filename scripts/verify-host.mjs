import assert from 'node:assert/strict'
import { pathToFileURL } from 'node:url'

// Use the generated native CP references rather than assuming hashed filenames.
export async function verifyControlPanelAssets(markup, request) {
  const assets = [...new Set([...markup.matchAll(/(?:src|href)\s*=\s*["']([^"']+)["']/gi)]
    .map(match => match[1].replaceAll('&amp;', '&'))
    .filter(path => /\/vendor\/statamic\/cp\/[^?#]+\.(?:js|css)(?:[?#]|$)/i.test(path)))]
  const stylesheet = path => /\.css(?:[?#]|$)/i.test(path)
  assert.ok(assets.some(stylesheet) && assets.some(path => !stylesheet(path)),
    'The native Control Panel must reference both a script and stylesheet.')
  for (const path of assets) {
    const response = await request(path)
    assert.match(response.headers.get('content-type') ?? '',
      stylesheet(path) ? /text\/css/i : /javascript|ecmascript/i,
      'Native Control Panel assets must have stylesheet/script MIME types.')
    assert.ok((await response.arrayBuffer()).byteLength > 0,
      'Native Control Panel assets must not be empty.')
  }
}

// No login, repairs, mail or uploads. CSRF creates only an anonymous session.
export async function verifyHost(value, { fetchImpl = fetch, report = () => {} } = {}) {
  const origin = new URL(value)
  assert.ok(origin.protocol === 'https:' && !origin.username && !origin.password
    && origin.pathname === '/' && !origin.search && !origin.hash,
  'Provide the root HTTPS origin without credentials, path, query or fragment.')

  async function request(path, status = 200, headers = {}) {
    const url = new URL(path, origin)
    assert.equal(url.origin, origin.origin, 'Application assets must use the same origin.')
    const response = await fetchImpl(url, {
      headers, redirect: 'error', signal: AbortSignal.timeout(15000),
    })
    assert.equal(response.status, status, `${url.pathname}: expected HTTP ${status}.`)
    return response
  }

  const html = await (await request('/')).text()
  assert.match(html, /id="root"/, 'The root must serve the frontend application.')
  for (const path of ['/install', '/technicians', '/dashboard/repairs/1', '/dashboard/subscription']) {
    assert.equal(await (await request(path)).text(), html, 'SPA deep links must serve the public application shell.')
  }
  report('HTTPS entry and public/private SPA deep links')

  const assets = [...html.matchAll(/(?:src|href)="([^"]+)"/g)]
    .map(match => match[1]).filter(path => path.includes('/assets/'))
  assert.ok(assets.some(path => path.endsWith('.js')) && assets.some(path => path.endsWith('.css')), 'Built script and stylesheet references are required.')
  for (const path of assets) {
    const response = await request(path)
    assert.match(response.headers.get('content-type') ?? '', path.endsWith('.css') ? /text\/css/i : /javascript|ecmascript/i, 'Assets must have executable stylesheet/script MIME types.')
    assert.ok((await response.arrayBuffer()).byteLength > 0, 'Built assets must not be empty.')
  }
  report('Same-origin production scripts and stylesheets')

  assert.doesNotMatch(html, /rel=["']manifest["']|apple-mobile-web-app-capable/i, 'This is a regular website without installation metadata.')
  const worker = await request('/sw.js')
  assert.match(worker.headers.get('content-type') ?? '', /javascript|ecmascript/i)
  assert.match(worker.headers.get('cache-control') ?? '', /no-cache|no-store|max-age=0/i, 'Retirement worker updates must not be trapped in a long-lived HTTP cache.')
  const source = await worker.text()
  assert.ok(source.includes('registration.unregister') && !source.includes('__WB_MANIFEST'), 'The legacy worker must retire without precaching.')
  report('Regular website metadata and legacy worker retirement delivery')

  const health = await (await request('/api/v1/health', 200, { Accept: 'application/json' })).json()
  assert.equal(health.data.application, 'eRepair')
  assert.equal(health.data.status, 'ok')
  assert.equal(health.data.database, 'connected')
  for (const path of ['/api/v1/user', '/api/v1/dashboard', '/api/v1/repair-requests', '/api/v1/technician/subscription', '/api/v1/technician/subscription/analytics', '/cp/erepair', '/cp/erepair/subscriptions', '/cp/erepair/revenue']) {
    const privateResponse = await request(path, 401, { Accept: 'application/json' })
    assert.match(privateResponse.headers.get('content-type') ?? '', /application\/json/i, 'Private denial responses must have a JSON MIME type.')
    assert.match(privateResponse.headers.get('cache-control') ?? '', /no-store/i)
    assert.equal(privateResponse.headers.get('x-content-type-options'), 'nosniff')
    const denial = await privateResponse.json()
    assert.ok(typeof denial?.message === 'string' && denial.message.trim().length > 0, 'Private denial responses must contain a JSON error message.')
    assert.ok(!['data', 'trace', 'exception', 'file', 'line'].some(key => Object.hasOwn(denial, key)), 'Private denial responses must not include records or debug details.')
  }
  report('PostgreSQL health and unauthenticated marketplace, subscription and administrator denial')

  const csrf = await request('/sanctum/csrf-cookie', 204, { Origin: origin.origin })
  assert.equal(csrf.headers.get('access-control-allow-origin'), origin.origin)
  assert.equal(csrf.headers.get('access-control-allow-credentials'), 'true')
  const cookies = csrf.headers.getSetCookie()
  assert.ok(cookies.some(cookie => /^XSRF-TOKEN=/i.test(cookie)), 'Sanctum must issue an XSRF cookie.')
  const sessionCookies = cookies.filter(cookie => !/^XSRF-TOKEN=/i.test(cookie))
  assert.ok(sessionCookies.length > 0, 'Sanctum must issue a session cookie.')
  for (const cookie of cookies) {
    assert.match(cookie, /;\s*secure(?:;|$)/i, 'Production cookies must be Secure.')
    assert.match(cookie, /;\s*samesite=(?:lax|strict)(?:;|$)/i, 'Production cookies must use SameSite lax or strict.')
    assert.ok(!/;\s*domain=/i.test(cookie), 'Production cookies must remain host-only.')
  }
  for (const cookie of sessionCookies) assert.match(cookie, /;\s*httponly(?:;|$)/i, 'Session cookies must be HttpOnly.')
  report('Credentialed CORS, CSRF and secure host-only session cookies')

  const cp = await request('/cp/auth/login')
  const cpMarkup = await cp.text()
  assert.match(cpMarkup, /Statamic|Sign in|Log in/i)
  assert.match(cp.headers.get('cache-control') ?? '', /no-store/i)
  await verifyControlPanelAssets(cpMarkup, request)
  const cpStyleUrl = cpMarkup.match(/<link[^>]*href="(\/vendor\/erepair\/css\/operations\.css\?v=[a-f0-9]+)"[^>]*>/i)?.[1]
  assert.ok(cpStyleUrl, 'The native Control Panel must link the published eRepair stylesheet.')
  const cpStyles = await request(cpStyleUrl)
  assert.match(cpStyles.headers.get('content-type') ?? '', /text\/css/i)
  assert.ok((await cpStyles.text()).includes('.erepair-ops .ops-stats'), 'The operations stylesheet must contain the eRepair layout rules.')
  const projection = await request('/vendor/erepair/js/revenue-projection.js')
  assert.match(projection.headers.get('content-type') ?? '', /javascript|ecmascript/i, 'The revenue projection asset must have a JavaScript MIME type.')
  assert.ok((await projection.arrayBuffer()).byteLength > 0, 'The revenue projection script must not be empty.')
  for (const path of ['/.env', '/backend/.env', '/.git/config', '/.local/postgres-password', '/storage/missing-file.png']) {
    const url = new URL(path, origin)
    const response = await fetchImpl(url, { redirect: 'error', signal: AbortSignal.timeout(15000) })
    assert.ok([403, 404].includes(response.status), 'Sensitive files and missing uploads must be denied, never served by the SPA fallback.')
    await response.body?.cancel()
  }
  report('Native CMS, eRepair stylesheet delivery and sensitive-file denial')
}

if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) {
  try {
    await verifyHost(process.argv[2], { report: message => console.log(`[PASS] ${message}`) })
    console.log('Read-only HTTPS host checks passed. Browser workflows, mail delivery and recovery acceptance remain separate.')
  } catch (error) {
    // Do not serialize response bodies, cookies, tokens or raw network diagnostics.
    console.error(`[FAIL] ${error instanceof assert.AssertionError ? error.message : 'The host could not be verified. Check the HTTPS origin, TLS, reachability and response format.'}`)
    process.exitCode = 1
  }
}
