import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { toast } from 'vue-sonner';
import DirectoryPage from './DirectoryPage.vue';
import { api } from '@/services/api';

vi.mock('@/services/api', () => ({ api: { get: vi.fn() } }));
vi.mock('vue-sonner', () => ({ toast: { info: vi.fn() } }));

const clients: QueryClient[] = [];
const position = { coords: { latitude: 14.28456, longitude: 121.42567 } } as GeolocationPosition;
let locate: ReturnType<typeof vi.fn<Geolocation['getCurrentPosition']>>;
let failResults = false;
let technicianName = 'Luis Santos';

beforeEach(() => {
  failResults = false;
  technicianName = 'Luis Santos';
  locate = vi.fn<Geolocation['getCurrentPosition']>();
  vi.stubGlobal('navigator', { geolocation: { getCurrentPosition: locate } });
  vi.mocked(api.get).mockImplementation(async url => {
    if (url === '/lookups') return { data: { data: { device_types: [], repair_categories: [], specializations: [] } } };
    if (failResults) throw new Error('Connection interrupted');
    return { data: { data: [{
      id: 1, name: technicianName, city: 'Santa Cruz', province: 'Laguna',
      years_experience: 5, photo_url: null, reviews_count: 0, average_rating: 0,
      availability: 'available', verified: true, device_types: [], specializations: [],
      matching: { score: 0, reasons: {} },
    }], meta: { current_page: 1, last_page: 1, total: 1 } } };
  });
});

afterEach(() => {
  cleanup();
  clients.splice(0).forEach(client => client.clear());
  vi.unstubAllGlobals();
  vi.clearAllMocks();
});

async function mount(path = '/technicians') {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/technicians', component: DirectoryPage },
    { path: '/technicians/:id', component: { template: '<div />' } },
    { path: '/compare', component: { template: '<div />' } },
  ] });
  await router.push(path);
  await router.isReady();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  clients.push(client);
  const view = render(DirectoryPage, { global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
  return { ...view, router, client };
}

it('shows location progress, prevents duplicate requests and keeps filters when applying rounded coordinates', async () => {
  const { router } = await mount('/technicians?city=Santa+Cruz&device_type=1&page=2&compare=1,2');
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  const pending = screen.getByRole('button', { name: 'Finding your location…' });
  expect(pending).toBeDisabled();
  expect(pending).toHaveAttribute('aria-busy', 'true');
  await fireEvent.click(pending);
  expect(locate).toHaveBeenCalledTimes(1);
  locate.mock.calls[0][0](position);
  await waitFor(() => expect(router.currentRoute.value.query.latitude).toBe('14.28'));
  expect(router.currentRoute.value.query).toMatchObject({ longitude: '121.43', city: 'Santa Cruz', device_type: '1', compare: '1,2', sort: 'distance' });
  expect(router.currentRoute.value.query.page).toBeUndefined();
  expect(screen.getByRole('button', { name: 'Use approximate location' })).toBeEnabled();
  expect(screen.getByText('Approximate location is applied to this search.')).toBeInTheDocument();
});

it('lets users retry after location permission is denied without changing their search', async () => {
  const { router } = await mount('/technicians?city=Pila');
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  locate.mock.calls[0][1]?.({ code: 1 } as GeolocationPositionError);
  await waitFor(() => expect(screen.getByRole('button', { name: 'Use approximate location' })).toBeEnabled());
  expect(router.currentRoute.value.query).toEqual({ city: 'Pila' });
  expect(toast.info).toHaveBeenCalledWith(expect.stringContaining('enter your city manually'));
});

it('ignores an older location response after filters change, even while a newer request is pending', async () => {
  const { router } = await mount('/technicians?city=Pila');
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  await router.push('/technicians?city=Calamba');
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  locate.mock.calls[0][0](position);
  locate.mock.calls[0][1]?.({ code: 1 } as GeolocationPositionError);
  expect(router.currentRoute.value.query).toEqual({ city: 'Calamba' });
  expect(screen.getByRole('button', { name: 'Finding your location…' })).toBeDisabled();
  expect(toast.info).not.toHaveBeenCalled();
  locate.mock.calls[1][0](position);
  await waitFor(() => expect(router.currentRoute.value.query.latitude).toBe('14.28'));
  expect(router.currentRoute.value.query.city).toBe('Calamba');
});

it('ignores location callbacks after leaving the finder', async () => {
  const { router, unmount } = await mount();
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  unmount();
  await router.push('/compare?ids=1,2');
  locate.mock.calls[0][0](position);
  locate.mock.calls[0][1]?.({ code: 1 } as GeolocationPositionError);
  expect(router.currentRoute.value.path).toBe('/compare');
  expect(toast.info).not.toHaveBeenCalled();
});

it('cancels pending location when resetting an already empty search', async () => {
  const { router } = await mount();
  await fireEvent.click(screen.getByRole('button', { name: 'Use approximate location' }));
  await fireEvent.click(screen.getByRole('button', { name: 'Reset filters' }));
  locate.mock.calls[0][0](position);
  expect(router.currentRoute.value.query).toEqual({});
  expect(screen.getByRole('button', { name: 'Use approximate location' })).toBeEnabled();
});

it.each([['distance', 'recommended'], ['experience', 'experience']])('removes location with %s sorting while retaining search and comparison', async (sort, expected) => {
  const { router } = await mount(`/technicians?latitude=14.28&longitude=121.43&city=Pila&device_type=1&page=2&compare=1,2&sort=${sort}`);
  await fireEvent.click(screen.getByRole('button', { name: 'Remove approximate location' }));
  await waitFor(() => expect(router.currentRoute.value.query.latitude).toBeUndefined());
  expect(router.currentRoute.value.query).toEqual({ city: 'Pila', device_type: '1', compare: '1,2', sort: expected });
  expect(screen.queryByText('Approximate location is applied to this search.')).not.toBeInTheDocument();
});

it('keeps successful results visible when a refresh fails and recovers on retry', async () => {
  const { client } = await mount();
  await screen.findByRole('heading', { name: /Luis Santos/ });
  failResults = true;
  await client.invalidateQueries({ queryKey: ['technicians'] });
  expect(await screen.findByRole('alert')).toHaveTextContent('Showing the last successful search');
  expect(screen.getByRole('heading', { name: /Luis Santos/ })).toBeInTheDocument();
  failResults = false;
  technicianName = 'Maria Reyes';
  await fireEvent.click(screen.getByRole('button', { name: 'Retry results refresh' }));
  await screen.findByRole('heading', { name: /Maria Reyes/ });
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});

it('shows an initial search error without claiming to be searching and recovers', async () => {
  failResults = true;
  await mount();
  expect(await screen.findByRole('alert')).toHaveTextContent('Connection interrupted');
  expect(screen.getByText('Search unavailable')).toBeInTheDocument();
  failResults = false;
  await fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
  await screen.findByRole('heading', { name: /Luis Santos/ });
});
