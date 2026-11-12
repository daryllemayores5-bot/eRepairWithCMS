// Retirement only: existing installations can update their old /sw.js.
// No fetch handler, precache, offline mode, install prompt or forced navigation.
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()))
self.addEventListener('activate', event => event.waitUntil((async () => {
  const scope = self.registration.scope
  try {
    const keys = await caches.keys()
    await Promise.all(keys.filter(key => key.startsWith('workbox-precache-') && key.endsWith(scope)).map(key => caches.delete(key)))
  } catch {
    // Disabled cache access must not prevent unregistering the obsolete worker.
  }
  await self.registration.unregister()
})()))
