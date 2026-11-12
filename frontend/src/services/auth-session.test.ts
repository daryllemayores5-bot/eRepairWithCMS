import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { QueryClient, QueryObserver } from '@tanstack/query-core'
import axios, { AxiosError, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios'
import { createAuthSession } from './auth-session'
import { api } from './api'
import { authKey } from '@/lib/session-cache'
import type { User } from '@/types'

const ana: User = { id: 11, name: 'Ana', email: 'ana@example.test', role: 'customer' }
const ben: User = { id: 12, name: 'Ben', email: 'ben@example.test', role: 'customer' }
const originalAdapter = api.defaults.adapter
let serverUser: User | null
let client: QueryClient
let session: ReturnType<typeof createAuthSession>
let observer: QueryObserver<User | null>
let unsubscribe: (() => void) | undefined
let userRequests: number
let protectedReply: (config: InternalAxiosRequestConfig) => Promise<AxiosResponse>
let deferredUser: ((config: InternalAxiosRequestConfig) => Promise<AxiosResponse>) | undefined

function reply(config: InternalAxiosRequestConfig, data: unknown = null): AxiosResponse {
  return { config, data, status: 200, statusText: 'OK', headers: {} }
}
function rejected(config: InternalAxiosRequestConfig, status: number, data: Record<string, unknown> = {}) {
  return new AxiosError('Request failed', 'ERR_BAD_REQUEST', config, undefined, { ...reply(config, data), status })
}
beforeEach(() => {
  serverUser = ana
  userRequests = 0
  deferredUser = undefined
  protectedReply = async config => reply(config)
  vi.spyOn(axios, 'get').mockResolvedValue({ data: null })
  client = new QueryClient({ defaultOptions: { queries: { retry: false, staleTime: Infinity } } })
  api.defaults.adapter = async config => {
    if (config.url === '/user') {
      userRequests++
      if (deferredUser) return deferredUser(config)
      if (!serverUser) throw rejected(config, 401)
      return reply(config, { data: serverUser })
    }
    if (config.url === '/logout') { serverUser = null; return reply(config) }
    return protectedReply(config)
  }
  session = createAuthSession(client)
  observer = new QueryObserver<User | null>(client, { queryKey: authKey, retry: false, queryFn: session.fetchUser })
})
afterEach(() => {
  unsubscribe?.()
  unsubscribe = undefined
  observer.destroy()
  session.dispose()
  client.clear()
  api.defaults.adapter = originalAdapter
  vi.restoreAllMocks()
})
async function signIn() {
  unsubscribe = observer.subscribe(() => {})
  await observer.refetch({ throwOnError: true })
  expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
  client.setQueryData(['dashboard'], { privateRepair: 'Ana repair' })
  client.setQueryData(['website-content'], { publicTitle: 'eRepair' })
}

describe('Vue authentication session transport and cache boundaries', () => {
  it('discards private data on account switch and retains public content', async () => {
    await signIn()
    serverUser = ben
    await observer.refetch()
    expect(client.getQueryData<User>(authKey)?.id).toBe(ben.id)
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
    expect(client.getQueryData(['website-content'])).toEqual({ publicTitle: 'eRepair' })
  })

  it('retains private data during a refresh of the same account', async () => {
    await signIn()
    await observer.refetch()
    expect(client.getQueryData(['dashboard'])).toEqual({ privateRepair: 'Ana repair' })
  })

  it('removes private data when the server session expires', async () => {
    await signIn()
    serverUser = null
    await observer.refetch()
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
  })

  it('cancels private requests so late results cannot refill the previous cache', async () => {
    await signIn()
    let finish!: (data: string) => void
    const old = client.fetchQuery({ queryKey: ['repair', 99], queryFn: () => new Promise<string>(resolve => { finish = resolve }) }).catch(() => undefined)
    await session.acceptUser(ben)
    finish('Ana private documentation')
    await old
    expect(client.getQueryData(['repair', 99])).toBeUndefined()
    expect(client.getQueryData<User>(authKey)?.id).toBe(ben.id)
  })

  it('ends the local session after a protected request returns 401', async () => {
    await signIn()
    protectedReply = async config => { throw rejected(config, 401) }
    await api.get('/repair-requests').catch(() => undefined)
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
  })

  it('ignores an unauthorized response from an earlier account generation', async () => {
    await signIn()
    let rejectOld!: (reason: AxiosError) => void
    let oldConfig!: InternalAxiosRequestConfig
    protectedReply = config => {
      oldConfig = config
      return new Promise((_resolve, reject) => { rejectOld = reject })
    }
    const old = api.get('/repair-requests').catch(() => undefined)
    await vi.waitFor(() => expect(rejectOld).toBeDefined())
    await session.acceptUser(ben)
    rejectOld(rejected(oldConfig, 401))
    await old
    expect(client.getQueryData<User>(authKey)?.id).toBe(ben.id)
  })

  it('keeps the successful login identity when an older auth request completes late', async () => {
    await signIn()
    let finish!: (response: AxiosResponse) => void
    let oldConfig!: InternalAxiosRequestConfig
    deferredUser = config => {
      oldConfig = config
      return new Promise(resolve => { finish = resolve })
    }
    const old = observer.refetch()
    await vi.waitFor(() => expect(finish).toBeDefined())
    await session.acceptUser(ben)
    finish(reply(oldConfig, { data: ana }))
    await old
    expect(client.getQueryData<User>(authKey)?.id).toBe(ben.id)
  })

  it('retains the session for a forbidden record and ends it for an inactive account', async () => {
    await signIn()
    protectedReply = async config => { throw rejected(config, 403) }
    await api.get('/repair-requests/99').catch(() => undefined)
    expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
    expect(client.getQueryData(['dashboard'])).toEqual({ privateRepair: 'Ana repair' })
    protectedReply = async config => { throw rejected(config, 403, { code: 'account_inactive' }) }
    await api.get('/repair-requests').catch(() => undefined)
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
  })

  it('rechecks identity on CSRF failure and clears an expired account', async () => {
    await signIn()
    serverUser = null
    protectedReply = async config => { throw rejected(config, 419) }
    await api.post('/repair-requests').catch(() => undefined)
    expect(userRequests).toBe(2)
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
  })

  it('retains same-account data when CSRF revalidation still finds that account', async () => {
    await signIn()
    protectedReply = async config => { throw rejected(config, 419) }
    await api.post('/repair-requests').catch(() => undefined)
    expect(userRequests).toBe(2)
    expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
    expect(client.getQueryData(['dashboard'])).toEqual({ privateRepair: 'Ana repair' })
  })

  it('does not reinterpret a server connection failure as a successful guest session', async () => {
    await signIn()
    deferredUser = async config => { throw rejected(config, 503) }
    const result = await observer.refetch()
    expect(result.isError).toBe(true)
    expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
  })

  it('holds sign-out pending until navigation finishes', async () => {
    await signIn()
    let finishNavigation!: () => void
    const navigation = vi.fn(() => new Promise<void>(resolve => { finishNavigation = resolve }))
    const signedOut = session.logout(navigation)
    await vi.waitFor(() => expect(navigation).toHaveBeenCalledOnce())
    expect(session.signingOut.value).toBe(true)
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
    finishNavigation()
    await signedOut
    expect(session.signingOut.value).toBe(false)
  })

  it('finishes sign-out if the server session has already expired', async () => {
    await signIn()
    api.defaults.adapter = async config => { throw rejected(config, 401) }
    await session.logout()
    expect(client.getQueryData(authKey)).toBeNull()
    expect(client.getQueryData(['dashboard'])).toBeUndefined()
    expect(session.signingOut.value).toBe(false)
  })

  it('preserves the current session if sign-out fails before server confirmation', async () => {
    await signIn()
    api.defaults.adapter = async config => { throw rejected(config, 503) }
    const navigate = vi.fn()
    await expect(session.logout(navigate)).rejects.toBeInstanceOf(AxiosError)
    expect(navigate).not.toHaveBeenCalled()
    expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
    expect(session.signingOut.value).toBe(false)
  })

  it('ejects transport interceptors when the owning store is disposed', async () => {
    await signIn()
    session.dispose()
    protectedReply = async config => { throw rejected(config, 401) }
    await api.get('/repair-requests').catch(() => undefined)
    expect(client.getQueryData<User>(authKey)?.id).toBe(ana.id)
    expect(client.getQueryData(['dashboard'])).toEqual({ privateRepair: 'Ana repair' })
  })
})
