import test from 'node:test'
import assert from 'node:assert/strict'
import { verifyHost } from '../scripts/verify-host.mjs'

function fixture(options = {}) {
  const requested = []
  const html = `<div id="root"></div><script src="${options.externalAsset ? 'https://unrelated.test' : ''}/assets/app.js"></script><link href="/assets/app.css">`
  async function fetchImpl(url, init) {
    requested.push({ url, init })
    const path = url.pathname
    if (['/', '/install', '/technicians', '/dashboard/repairs/1', '/dashboard/subscription'].includes(path)) return new Response(html)
    if (path === '/assets/app.js') return new Response('console.log("eRepair")', { headers: { 'content-type': 'application/javascript' } })
    if (path === '/assets/app.css') return new Response('body{}', { headers: { 'content-type': 'text/css' } })
    if (path === '/sw.js') return new Response('self.registration.unregister()', { headers: {
      'content-type': 'application/javascript', 'cache-control': options.cachedWorker ? 'max-age=31536000' : 'no-cache',
    } })
    if (path === '/api/v1/health') return Response.json({ data: { application: 'eRepair', status: 'ok', database: 'connected' } })
    if (['/api/v1/user', '/api/v1/dashboard', '/api/v1/repair-requests', '/api/v1/technician/subscription', '/api/v1/technician/subscription/analytics', '/cp/erepair', '/cp/erepair/subscriptions', '/cp/erepair/revenue'].includes(path)) {
      const status = options.exposedRoute === path || (path === '/api/v1/user' && options.exposedUser) ? 200 : 401
      const headers = { 'cache-control': options.cachedPrivateRoute === path ? 'public, max-age=3600' : 'no-store, private', 'x-content-type-options': 'nosniff' }
      return options.htmlPrivateRoute === path
        ? new Response(html, { status, headers: { ...headers, 'content-type': 'text/html' } })
        : Response.json({ message: 'Unauthenticated', ...(options.debugPrivateRoute === path ? { trace: ['synthetic debug detail'] } : {}), ...(options.recordPrivateRoute === path ? { data: [] } : {}) }, { status, headers })
    }
    if (path === '/sanctum/csrf-cookie') {
      const headers = new Headers({ 'access-control-allow-origin': 'https://erepair.test', 'access-control-allow-credentials': 'true' })
      headers.append('set-cookie', 'XSRF-TOKEN=synthetic; Path=/; Secure; SameSite=Lax')
      headers.append('set-cookie', `session=synthetic; Path=/; ${options.insecureSession ? '' : 'Secure; '}HttpOnly; SameSite=Lax`)
      return new Response(null, { status: 204, headers })
    }
    if (path === '/cp/auth/login') return new Response(`<head>${options.missingNativeReferences ? '' : `<link href="/vendor/statamic/cp/build/assets/app.css" rel="stylesheet"><script src="${options.externalNativeAsset ? 'https://unrelated.test' : ''}/vendor/statamic/cp/build/assets/app.js"></script>`}<link href="/vendor/erepair/css/operations.css?v=abc123" rel="stylesheet"></head>Statamic Sign in`, { headers: { 'cache-control': 'no-store, private' } })
    if (path === '/vendor/statamic/cp/build/assets/app.css') return new Response('body{color:navy}', { status: options.missingNativeCss ? 404 : 200, headers: { 'content-type': options.nativeHtmlFallback ? 'text/html' : 'text/css' } })
    if (path === '/vendor/statamic/cp/build/assets/app.js') return new Response(options.emptyNativeScript ? '' : 'export const boot = true', { status: options.missingNativeScript ? 404 : 200, headers: { 'content-type': 'application/javascript' } })
    if (path === '/vendor/erepair/css/operations.css') return new Response('.erepair-ops .ops-stats{display:grid}', { status: options.missingCpCss ? 404 : 200, headers: { 'content-type': 'text/css' } })
    if (path === '/vendor/erepair/js/revenue-projection.js') return new Response(options.emptyProjectionScript ? '' : 'document.addEventListener("input", () => {})', { status: options.missingProjectionScript ? 404 : 200, headers: { 'content-type': options.projectionHtmlFallback ? 'text/html' : 'application/javascript' } })
    return new Response(options.sensitiveFallback ? html : '', { status: options.sensitiveFallback ? 200 : 404 })
  }
  return { requested, fetchImpl }
}

test('checks the entire HTTPS delivery chain without submitting mutations or retaining credentials', async () => {
  const { requested, fetchImpl } = fixture()
  const stages = []
  await verifyHost('https://erepair.test', { fetchImpl, report: stage => stages.push(stage) })
  assert.equal(stages.length, 6)
  assert.ok(requested.length >= 19)
  assert.ok(requested.every(({ url, init }) => url.origin === 'https://erepair.test' && !init.method && init.redirect === 'error' && init.signal))
  assert.ok(requested.every(({ init }) => !init.headers?.Cookie && !init.headers?.Authorization))
})

test('refuses insecure or credential-bearing origins before making requests', async () => {
  const { requested, fetchImpl } = fixture()
  for (const origin of ['http://erepair.test', 'https://user:secret@erepair.test', 'https://erepair.test/path', 'https://erepair.test?token=secret']) {
    await assert.rejects(verifyHost(origin, { fetchImpl }), /root HTTPS origin/)
  }
  assert.equal(requested.length, 0)
})

for (const path of ['/api/v1/dashboard', '/api/v1/repair-requests', '/api/v1/technician/subscription', '/api/v1/technician/subscription/analytics', '/cp/erepair', '/cp/erepair/subscriptions', '/cp/erepair/revenue']) {
  test(`rejects anonymous access to ${path}`, async () => {
    await assert.rejects(verifyHost('https://erepair.test', fixture({ exposedRoute: path })), /expected HTTP 401/)
  })
}

test('rejects cacheable subscription denial responses', async () => {
  await assert.rejects(verifyHost('https://erepair.test', fixture({ cachedPrivateRoute: '/api/v1/technician/subscription' })), /no-store/i)
})

test('rejects a private subscription route replaced by HTML', async () => {
  await assert.rejects(verifyHost('https://erepair.test', fixture({ htmlPrivateRoute: '/api/v1/technician/subscription/analytics' })), /JSON MIME type/)
})

test('rejects private denial responses containing debug details or record payloads', async () => {
  for (const options of [{ debugPrivateRoute: '/api/v1/technician/subscription' }, { recordPrivateRoute: '/cp/erepair/revenue' }]) {
    await assert.rejects(verifyHost('https://erepair.test', fixture(options)), /records or debug details/)
  }
})

for (const [name, options, message] of [
  ['missing', { missingProjectionScript: true }, /expected HTTP 200/],
  ['empty', { emptyProjectionScript: true }, /empty/i],
  ['HTML fallback', { projectionHtmlFallback: true }, /javascript|ecmascript/i],
]) {
  test(`rejects ${name} revenue projection scripts`, async () => {
    await assert.rejects(verifyHost('https://erepair.test', fixture(options)), message)
  })
}

for (const [name, options, message] of [
  ['cross-origin application assets', { externalAsset: true }, /same origin/],
  ['an exposed private API', { exposedUser: true }, /expected HTTP 401/],
  ['an insecure session cookie', { insecureSession: true }, /Secure/],
  ['a long-lived cached retirement worker', { cachedWorker: true }, /long-lived HTTP cache/],
  ['sensitive files swallowed by SPA fallback', { sensitiveFallback: true }, /Sensitive files/],
  ['a missing eRepair Control Panel stylesheet', { missingCpCss: true }, /expected HTTP 200/],
  ['missing native Control Panel asset references', { missingNativeReferences: true }, /native.*script.*stylesheet/i],
  ['a missing native Control Panel stylesheet', { missingNativeCss: true }, /expected HTTP 200/],
  ['a missing native Control Panel script', { missingNativeScript: true }, /expected HTTP 200/],
  ['native assets replaced by an HTML fallback', { nativeHtmlFallback: true }, /native.*MIME/i],
  ['an empty native Control Panel script', { emptyNativeScript: true }, /native.*empty/i],
  ['cross-origin native Control Panel scripts', { externalNativeAsset: true }, /same origin/],
]) {
  test(`rejects ${name}`, async () => {
    await assert.rejects(verifyHost('https://erepair.test', fixture(options)), message)
  })
}
