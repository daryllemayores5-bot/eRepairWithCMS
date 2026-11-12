import { beforeEach, expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { defineComponent, reactive, ref } from 'vue'
import { createRouter, createMemoryHistory, RouterView } from 'vue-router'
import ProtectedRoute from './ProtectedRoute.vue'
import type { User } from '@/types'

const ana: User = { id: 11, name: 'Ana', email: 'ana@example.test', role: 'customer' }
const auth = reactive({ user: ana as User | null, pending: false, error: false, refresh: vi.fn() })
vi.mock('@/stores/auth', () => ({ useAuthStore: () => auth }))
const PrivateForm = defineComponent({
  setup: () => ({ note: ref('') }),
  template: '<form><label>Private note<input v-model="note" /></label><label>Private photo<input type="file" /></label></form>',
})
beforeEach(() => { auth.user = ana; auth.pending = false; auth.error = false; auth.refresh.mockClear() })

async function open() {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/login', component: { template: '<h1>Sign in</h1>' } },
    { path: '/dashboard', component: ProtectedRoute, children: [{ path: '', component: PrivateForm }] },
  ] })
  await router.push('/dashboard?filter=active')
  await router.isReady()
  render(defineComponent({ components: { RouterView }, template: '<RouterView />' }), { global: { plugins: [router] } })
  return router
}

it('discards the previous identity private draft and selected upload after an account switch', async () => {
  await open()
  await fireEvent.update(await screen.findByLabelText('Private note'), 'Ana private repair note')
  const oldUpload = screen.getByLabelText('Private photo') as HTMLInputElement
  Object.defineProperty(oldUpload, 'files', { value: [new File(['private'], 'device.png', { type: 'image/png' })], configurable: true })
  auth.user = { id: 12, name: 'Ben', email: 'ben@example.test', role: 'customer' }
  await waitFor(() => expect((screen.getByLabelText('Private note') as HTMLInputElement).value).toBe(''))
  const newUpload = screen.getByLabelText('Private photo') as HTMLInputElement
  expect(newUpload).not.toBe(oldUpload)
  expect(newUpload.files?.length).toBe(0)
})

it('retains the full requested destination when redirecting a guest', async () => {
  auth.user = null
  const router = await open()
  await screen.findByRole('heading', { name: 'Sign in' })
  expect(router.currentRoute.value.path).toBe('/login')
  expect(router.options.history.state.from).toBe('/dashboard?filter=active')
  expect(screen.queryByLabelText('Private note')).not.toBeInTheDocument()
})

it('holds private forms behind pending and failed session checks', async () => {
  auth.user = null; auth.pending = true
  const router = await open()
  expect(screen.getByRole('status')).toBeInTheDocument()
  expect(router.currentRoute.value.path).toBe('/dashboard')
  auth.pending = false; auth.error = true
  await screen.findByRole('alert')
  expect(screen.queryByLabelText('Private note')).not.toBeInTheDocument()
  await fireEvent.click(screen.getByRole('button', { name: 'Try again' }))
  expect(auth.refresh).toHaveBeenCalledOnce()
  auth.user = ana; auth.error = false
  expect(await screen.findByLabelText('Private note')).toBeInTheDocument()
})

it('routes administrator access to the existing native Statamic interface', async () => {
  auth.user = { ...ana, role: 'admin' }
  await open()
  await screen.findByRole('heading', { name: 'Administrator account' })
  expect(screen.getByRole('link', { name: 'Open Statamic' }).getAttribute('href')).toMatch(/\/cp$/)
  expect(screen.queryByLabelText('Private note')).not.toBeInTheDocument()
})
