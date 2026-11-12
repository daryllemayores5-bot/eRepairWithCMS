import { expect, it, vi } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/vue'
import { createMemoryHistory, createRouter, type RouteLocationRaw } from 'vue-router'
import NavLink from './NavLink.vue'

async function open(path: string, to: RouteLocationRaw, end = false, onClick = vi.fn()) {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/', component: {} },
    { path: '/technicians', component: {} },
    { path: '/technicians/:id', name: 'technician', component: {} },
    { path: '/dashboard', component: {} },
    { path: '/dashboard/repairs', component: {} },
    { path: '/dashboard/repairs/:id', component: {} },
    { path: '/:pathMatch(.*)*', component: {} },
  ] })
  await router.push(path)
  await router.isReady()
  render(NavLink, {
    props: { to, end, navClass: ({ isActive }: { isActive: boolean }) => isActive ? 'selected' : 'unselected' },
    attrs: { onClick, 'aria-label': 'Navigation destination' },
    slots: { default: 'Destination' },
    global: { plugins: [router] },
  })
  return { router, link: screen.getByRole('link', { name: 'Navigation destination' }), onClick }
}

it.each([
  ['/technicians/42', '/technicians', false, true],
  ['/dashboard/repairs/42', '/dashboard/repairs', false, true],
  ['/dashboard/repairs/new', '/dashboard/repairs', false, true],
  ['/TECHNICIANS/42', '/technicians', false, true],
  ['/technicians/42', '/technicians/', false, true],
  ['/technicians?city=Laguna#results', '/technicians', false, true],
  ['/technicians-elsewhere', '/technicians', false, false],
  ['/dashboard/repairs', '/dashboard', true, false],
  ['/dashboard', '/dashboard', true, true],
  ['/technicians', '/', false, false],
  ['/', '/', false, true],
] as const)('preserves active navigation at %s for %s (end=%s)', async (path, to, end, active) => {
  const { link } = await open(path, to, end)
  expect(link).toHaveClass(active ? 'selected' : 'unselected')
  if (active) expect(link).toHaveAttribute('aria-current', 'page')
  else expect(link).not.toHaveAttribute('aria-current')
})

it('updates the active state through real navigation and forwards click handlers', async () => {
  const { router, link, onClick } = await open('/technicians', '/dashboard/repairs')
  expect(link).not.toHaveAttribute('aria-current')
  await fireEvent.click(link)
  await waitFor(() => expect(router.currentRoute.value.path).toBe('/dashboard/repairs'))
  expect(onClick).toHaveBeenCalledOnce()
  expect(link).toHaveClass('selected')
  await router.push('/dashboard/repairs/42')
  expect(link).toHaveAttribute('aria-current', 'page')
  await router.push('/technicians')
  expect(link).toHaveClass('unselected')
})

it('resolves named destinations before matching the active path', async () => {
  const { link } = await open('/technicians/42?reviews=2', { name: 'technician', params: { id: 42 } })
  expect(link).toHaveAttribute('href', '/technicians/42')
  expect(link).toHaveAttribute('aria-current', 'page')
})
