import { render, screen, fireEvent, cleanup } from '@testing-library/vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, expect, it, vi } from 'vitest';
import ComparePage from './ComparePage.vue';
import { api } from '@/services/api';

vi.mock('@/services/api', () => ({ api: { get: vi.fn() } }));
afterEach(cleanup);

it('recovers a failed comparison profile without losing the other profile', async () => {
  const profile = (id: number) => ({
    id, name: `Technician ${id}`, city: 'Santa Cruz', province: 'Laguna',
    years_experience: 5, photo_url: null, reviews_count: 0, average_rating: 0,
    availability: 'available', specializations: [{ id: 1, name: 'Battery' }],
    device_types: [{ id: 1, name: 'Laptop' }],
    service_areas: [{ city: 'Santa Cruz', province: 'Laguna' }, { city: 'Pila', province: 'Laguna' }],
  });
  let failed = false;
  vi.mocked(api.get).mockImplementation(async url => {
    if (url === '/technicians/2' && !failed) {
      failed = true;
      throw new Error('Connection interrupted');
    }
    return { data: { data: profile(url === '/technicians/1' ? 1 : 2) } };
  });
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/compare', component: ComparePage },
    { path: '/technicians/:id?', component: { template: '<div />' } },
  ] });
  await router.push('/compare?ids=1,2');
  await router.isReady();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  render(ComparePage, { global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
  try {
    await screen.findByRole('heading', { name: 'Technician 1' });
    await fireEvent.click(await screen.findByRole('button', { name: 'Try again' }));
    await screen.findByRole('heading', { name: 'Technician 2' });
    expect(screen.getAllByText('Laptop')).toHaveLength(2);
    expect(screen.getAllByText('Pila, Laguna')).toHaveLength(2);
    expect(vi.mocked(api.get).mock.calls.filter(([url]) => url === '/technicians/1')).toHaveLength(1);
    expect(vi.mocked(api.get).mock.calls.filter(([url]) => url === '/technicians/2')).toHaveLength(2);
  } finally {
    cleanup();
    client.clear();
  }
});
