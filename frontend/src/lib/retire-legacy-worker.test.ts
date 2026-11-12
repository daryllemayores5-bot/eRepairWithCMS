import { describe, expect, it, vi } from 'vitest'
import { retireLegacyWorker } from './retire-legacy-worker'

describe('retiring the former installation', () => {
  it('unregisters only the legacy root worker and deletes only its scoped precaches', async () => {
    const origin = 'https://erepair.example'
    const owned = { scope: `${origin}/`, active: { scriptURL: `${origin}/sw.js` }, unregister: vi.fn(async () => true) }
    const other = { scope: `${origin}/other/`, active: { scriptURL: `${origin}/other/sw.js` }, unregister: vi.fn() }
    const cacheStorage = { keys: vi.fn(async () => [`workbox-precache-v2-${origin}/`, `workbox-precache-v2-${origin}/other/`, 'other-app']), delete: vi.fn(async () => true) }
    await retireLegacyWorker({ origin, serviceWorker: { getRegistrations: vi.fn(async () => [owned, other] as unknown as ServiceWorkerRegistration[]) }, cacheStorage })
    expect(owned.unregister).toHaveBeenCalledOnce()
    expect(other.unregister).not.toHaveBeenCalled()
    expect(cacheStorage.delete).toHaveBeenCalledExactlyOnceWith(`workbox-precache-v2-${origin}/`)
  })

  it('does not block the web application when browser worker access is unavailable', async () => {
    await expect(retireLegacyWorker({ origin: 'https://erepair.example', serviceWorker: { getRegistrations: vi.fn(async () => { throw new Error('Storage disabled') }) } })).resolves.toBeUndefined()
  })
})
