import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { fileURLToPath, pathToFileURL } from 'node:url'
import { join } from 'node:path'

test('revenue projection follows the selected plan and technician count using configured centavos', async () => {
  const require = createRequire(new URL('../frontend/package.json', import.meta.url))
  const { JSDOM } = require('jsdom')
  const dom = new JSDOM(`<form id="revenue-projection" data-price-cents="19900">
    <input id="projection-count" type="number" min="0" max="1000000" step="1" value="10">
    <select id="projection-plan"><option data-price-cents="19900" data-name="Pro Technician" value="pro">Pro</option><option data-price-cents="39900" data-name="Premium Technician" value="premium">Premium</option></select>
    <span id="projection-monthly"></span><span id="projection-annual"></span><span id="projection-plan-name"></span><span id="projection-price"></span>
    </form>`, { runScripts: 'outside-only' })
  try {
    const source = await readFile(new URL('../backend/resources/js/revenue-projection.js', import.meta.url), 'utf8')
    dom.window.eval(source)
    const select = dom.window.document.querySelector('#projection-plan')
    select.value = 'premium'
    select.dispatchEvent(new dom.window.Event('input', { bubbles: true }))
    assert.equal(dom.window.document.querySelector('#projection-monthly').textContent, '₱3,990.00')
    assert.equal(dom.window.document.querySelector('#projection-annual').textContent, '₱47,880.00')
    assert.equal(dom.window.document.querySelector('#projection-plan-name').textContent, 'Premium Technician')
    const count = dom.window.document.querySelector('#projection-count')
    count.value = '2'
    count.dispatchEvent(new dom.window.Event('input', { bubbles: true }))
    assert.equal(dom.window.document.querySelector('#projection-monthly').textContent, '₱798.00')
    assert.equal(await readFile(new URL('../backend/public/vendor/erepair/js/revenue-projection.js', import.meta.url), 'utf8'), source)
  } finally {
    dom.window.close()
  }
})

test('eRepair styles survive Statamic 6 native Vue compilation when loaded in the head', async () => {
  const workspace = fileURLToPath(new URL('../', import.meta.url))
  const require = createRequire(new URL('../frontend/package.json', import.meta.url))
  const { JSDOM } = require('jsdom')
  const dom = new JSDOM('<!doctype html><html><head></head><body><div id="mount"></div></body></html>')
  const previous = new Map()
  for (const key of ['window', 'document', 'Element', 'HTMLElement', 'SVGElement', 'Node']) {
    previous.set(key, Object.getOwnPropertyDescriptor(globalThis, key))
    Object.defineProperty(globalThis, key, { value: dom.window[key], configurable: true, writable: true })
  }
  let app
  try {
    // Use the installed CP's own Vue compiler, not a substitute framework build.
    const dist = join(workspace, 'backend/vendor/statamic/cms/resources/dist/build')
    const manifest = JSON.parse(await readFile(join(dist, 'manifest.json'), 'utf8'))
    const runtime = Object.entries(manifest).find(([key]) => key.startsWith('_vue.esm-bundler-'))?.[1].file
    assert.ok(runtime, 'The installed native CP Vue runtime must be available.')
    const module = await import(pathToFileURL(join(dist, runtime)).href)
    const vue = Object.values(module).find(value => value && typeof value.createApp === 'function' && typeof value.compile === 'function')
    assert.ok(vue, 'Resolve the native Vue compiler namespace from the installed bundle.')
    const css = await readFile(join(workspace, 'backend/resources/css/erepair-cp.css'), 'utf8')
    const view = await readFile(join(workspace, 'backend/resources/views/cp/erepair.blade.php'), 'utf8')
    const container = view.match(/<div class="erepair-ops"[^>]*>/)?.[0]
    assert.ok(container, 'Use the actual Blade view container and its Vue directives.')
    const content = `${container}<h1>eRepair Operations</h1><p class="user-note">Literal note: {{ 7 * 7 }}</p><div class="ops-stats"><div><strong>0</strong><p>Customers</p></div></div><form><button>Filter</button></form></div>`
    // Reproduce the old failure: Vue discards style tags inside the Blade template.
    app = vue.createApp({ template: `<div><style>${css}</style>${content}</div>` })
    app.mount('#mount')
    assert.equal(dom.window.document.querySelector('#mount style'), null)
    assert.notEqual(dom.window.getComputedStyle(dom.window.document.querySelector('.ops-stats')).display, 'grid')
    app.unmount()
    // HTTP coverage separately verifies that this CSS is linked/fetched correctly.
    const stylesheet = dom.window.document.createElement('style')
    stylesheet.textContent = css
    dom.window.document.head.append(stylesheet)
    app = vue.createApp({ template: content })
    app.mount('#mount')
    assert.equal(dom.window.getComputedStyle(dom.window.document.querySelector('.ops-stats')).display, 'grid')
    // jsdom does not resolve inherited custom properties in computed colors.
    assert.equal(dom.window.getComputedStyle(dom.window.document.querySelector('.erepair-ops')).getPropertyValue('--ops-blue'), '#0955f8')
    assert.equal(dom.window.getComputedStyle(dom.window.document.querySelector('button')).borderRadius, '9px')
    assert.equal(dom.window.getComputedStyle(dom.window.document.querySelector('.ops-stats > div')).borderRadius, '16px')
    assert.equal(dom.window.document.querySelector('h1').textContent, 'eRepair Operations')
    assert.equal(dom.window.document.querySelector('.user-note').textContent, 'Literal note: {{ 7 * 7 }}', 'User-entered text must not become a Vue template expression.')
  } finally {
    app?.unmount()
    dom.window.close()
    for (const [key, descriptor] of previous) {
      if (descriptor) Object.defineProperty(globalThis, key, descriptor)
      else delete globalThis[key]
    }
  }
})
