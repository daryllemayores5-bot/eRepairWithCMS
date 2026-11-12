import { ref } from 'vue'
import type { QueryClient } from '@tanstack/query-core'
import axios from 'axios'
import { api, initializeCsrf } from '@/services/api'
import { authKey, discardPrivateData } from '@/lib/session-cache'
import type { User } from '@/types'

/** Session behavior shared by the Pinia store and transport-level regressions. */
export function createAuthSession(cache: QueryClient) {
  const signingOut = ref(false)
  let identity = cache.getQueryData<User | null>(authKey)?.id ?? null
  let generation = 0
  let active = true

  async function forgetSession() {
    generation++
    identity = null
    await cache.cancelQueries({ queryKey: authKey, exact: true })
    await discardPrivateData(cache)
    cache.setQueryData(authKey, null)
  }

  async function fetchUser({ signal }: { signal: AbortSignal }): Promise<User | null> {
    let next: User | null
    try { next = (await api.get<{ data: User }>('/user', { signal })).data.data }
    catch (error) {
      if (axios.isAxiosError(error) && [401, 403].includes(error.response?.status ?? 0)) next = null
      else throw error
    }
    signal.throwIfAborted()
    if (identity !== (next?.id ?? null)) {
      generation++
      await discardPrivateData(cache)
      signal.throwIfAborted()
      identity = next?.id ?? null
    }
    return next
  }

  const requests = new WeakMap<object, number>()
  const requestInterceptor = api.interceptors.request.use(config => {
    requests.set(config, generation)
    return config
  })
  const responseInterceptor = api.interceptors.response.use(response => response, async (error: unknown) => {
    if (active && axios.isAxiosError(error) && error.config && identity !== null
      && requests.get(error.config) === generation
      && !/^\/(?:user|login|register|logout|forgot-password|reset-password)(?:$|\?)/.test(error.config.url ?? '')) {
      if (error.response?.status === 419) {
        await cache.invalidateQueries({ queryKey: authKey, exact: true })
      } else if (error.response?.status === 401
        || (error.response?.status === 403 && error.response.data?.code === 'account_inactive')) {
        await forgetSession()
      }
    }
    throw error
  })

  async function acceptUser(user: User) {
    generation++
    await cache.cancelQueries({ queryKey: authKey, exact: true })
    await discardPrivateData(cache)
    identity = user.id
    cache.setQueryData(authKey, user)
    return user
  }

  async function logout(onSignedOut?: () => void | Promise<unknown>) {
    signingOut.value = true
    try {
      await initializeCsrf()
      try { await api.post('/logout') }
      catch (error) { if (!axios.isAxiosError(error) || error.response?.status !== 401) throw error }
      await forgetSession()
      await onSignedOut?.()
    } finally { signingOut.value = false }
  }

  function dispose() {
    active = false
    api.interceptors.request.eject(requestInterceptor)
    api.interceptors.response.eject(responseInterceptor)
  }

  return { signingOut, fetchUser, acceptUser, logout, dispose }
}
