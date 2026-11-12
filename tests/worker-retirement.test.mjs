import test from 'node:test'
import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'
import { runInNewContext } from 'node:vm'

test('the legacy worker only removes its own caches and registration, without intercepting requests or navigating open forms', async () => {
  const source = await readFile(new URL('../frontend/public/sw.js', import.meta.url), 'utf8')
  const handlers = new Map()
  const deleted = []
  let unregistered = 0
  let skipped = 0
  const scope = 'https://erepair.example/'
  runInNewContext(source, {
    self: { addEventListener: (event, handler) => handlers.set(event, handler), skipWaiting: async () => { skipped++ }, registration: { scope, unregister: async () => { unregistered++ } } },
    caches: { keys: async () => [`workbox-precache-v2-${scope}`, `${'workbox-precache-v2-'}${scope}other/`, 'unrelated'], delete: async key => { deleted.push(key) } },
  })
  assert.deepEqual([...handlers.keys()], ['install', 'activate'])
  for (const handler of handlers.values()) {
    let pending
    handler({ waitUntil: promise => { pending = promise } })
    await pending
  }
  assert.equal(skipped, 1)
  assert.equal(unregistered, 1)
  assert.deepEqual(deleted, [`workbox-precache-v2-${scope}`])
})

test('the retirement worker unregisters even when browser cache access is disabled', async () => {
  const source = await readFile(new URL('../frontend/public/sw.js', import.meta.url), 'utf8')
  const handlers = new Map()
  let unregistered = false
  runInNewContext(source, {
    self: { addEventListener: (event, handler) => handlers.set(event, handler), registration: { scope: 'https://erepair.example/', unregister: async () => { unregistered = true } } },
    caches: { keys: async () => { throw Error('Cache access disabled') } },
  })
  let pending
  handlers.get('activate')({ waitUntil: promise => { pending = promise } })
  await pending
  assert.equal(unregistered, true)
})
