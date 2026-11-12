type Runtime = {
  origin: string
  serviceWorker?: Pick<ServiceWorkerContainer, 'getRegistrations'>
  cacheStorage?: Pick<CacheStorage, 'keys' | 'delete'>
}

/** Remove only the old root worker and its scoped Workbox precaches. */
export async function retireLegacyWorker(runtime?: Runtime): Promise<void> {
  try {
    const { origin, serviceWorker, cacheStorage } = runtime ?? { origin: location.origin, serviceWorker: navigator.serviceWorker, cacheStorage: window.caches }
    if (!serviceWorker) return
    for (const registration of await serviceWorker.getRegistrations()) {
      const worker = registration.active ?? registration.waiting ?? registration.installing
      if (!worker || registration.scope !== `${origin}/`) continue
      const url = new URL(worker.scriptURL)
      if (url.origin !== origin || url.pathname !== '/sw.js') continue
      await registration.unregister()
      if (cacheStorage) {
        const keys = await cacheStorage.keys()
        await Promise.all(keys.filter(key => key.startsWith('workbox-precache-') && key.endsWith(registration.scope)).map(key => cacheStorage.delete(key)))
      }
    }
  } catch {
    // Unsupported/disabled browser storage must not block the web application.
  }
}
