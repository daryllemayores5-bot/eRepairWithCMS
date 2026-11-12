import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import LandingPage from './LandingPage.vue';
import { api } from '@/services/api';
import type { Review } from '@/types';

vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ user: null }) }));
vi.mock('@/services/api', () => ({ api: { get: vi.fn() } }));
const clients: QueryClient[] = [];
let publishedTitle: string | null;
let publishedFaqs: { question: string; answer: string }[];
let reviews: Record<number, Review[]>;
let reviewFailure: boolean;
const profile = (id: number) => ({
  id, name: 'Technician ' + id, biography: null, city: 'Pila', province: 'Laguna',
  years_experience: 5, photo_url: null, availability: 'available', verified: true,
  average_rating: 4.5, reviews_count: 2, specializations: [], device_types: [],
  matching: { score: 0, reasons: {} }, service_areas: [], latitude: null, longitude: null,
});

beforeEach(() => {
  publishedTitle = null; publishedFaqs = []; reviews = {}; reviewFailure = false;
  vi.mocked(api.get).mockImplementation(async url => {
    if (url === '/content') return { data: { data: { hero_title: publishedTitle, hero_subtitle: null, faqs: publishedFaqs, announcements: [], services: [], pages: [] } } };
    if (url === '/lookups') return { data: { data: {
      device_types: [{ id: 7, name: 'Laptop' }, { id: 8, name: 'Mobile' }], device_brands: [],
      specializations: [{ id: 3, name: 'Battery repair' }],
      repair_categories: [{ id: 11, name: 'Laptop battery', device_type_id: 7 }, { id: 12, name: 'Mobile battery', device_type_id: 8 }],
    } } };
    if (url === '/subscription-plans') return { data: { data: [] } };
    if (url.startsWith('/technicians?')) return { data: { data: [profile(1), profile(2), profile(3)] } };
    const match = url.match(/^\/technicians\/(\d+)\/reviews$/);
    if (match) {
      if (reviewFailure) throw new Error('Customer feedback is unavailable');
      return { data: { data: reviews[Number(match[1])] ?? [] } };
    }
    throw new Error('Unexpected public API request');
  });
});
afterEach(() => { cleanup(); clients.splice(0).forEach(client => client.clear()); vi.clearAllMocks(); });

async function mount() {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/', component: LandingPage },
    ...['/technicians', '/technicians/:id', '/dashboard', '/dashboard/subscription', '/register/technician', '/how-it-works'].map(path => ({ path, component: { template: '<div />' } })),
  ] });
  await router.push('/'); await router.isReady();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  clients.push(client);
  render(LandingPage, { global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
  return router;
}

it('keeps the illustrated hero and submits the real device, repair problem and city filters', async () => {
  const router = await mount();
  expect(screen.getByRole('img', { name: /Illustrative scene of a technician/ })).toHaveAttribute('src', '/images/repair-technician.webp');
  expect(screen.getByRole('link', { name: 'Manage a repair' })).toHaveAttribute('href', '/dashboard');
  await screen.findByRole('option', { name: 'Laptop' });
  await fireEvent.update(screen.getByRole('combobox', { name: 'Device type' }), '7');
  await fireEvent.update(screen.getByRole('combobox', { name: 'Repair problem' }), '11');
  await fireEvent.update(screen.getByRole('textbox', { name: 'Location' }), 'Santa Cruz');
  await fireEvent.submit(screen.getByRole('form', { name: 'Find a repair technician' }));
  await waitFor(() => expect(router.currentRoute.value.path).toBe('/technicians'));
  expect(router.currentRoute.value.query).toEqual({ city: 'Santa Cruz', device_type: '7', repair_category: '11' });
});

it('clears an incompatible repair category when the selected device changes', async () => {
  await mount();
  await screen.findByRole('option', { name: 'Laptop' });
  const device = screen.getByRole('combobox', { name: 'Device type' });
  const problem = screen.getByRole('combobox', { name: 'Repair problem' });
  await fireEvent.update(device, '7');
  await fireEvent.update(problem, '11');
  await fireEvent.update(device, '8');
  expect(problem).toHaveValue('');
  expect(within(problem).queryByRole('option', { name: 'Laptop battery' })).not.toBeInTheDocument();
  expect(within(problem).getByRole('option', { name: 'Mobile battery' })).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Laptop repair' })).toHaveAttribute('href', '/technicians?device_type=7');
});

it('shows actual public review text and ratings, with newest feedback first', async () => {
  reviews = {
    1: [{ id: 11, rating: 5, body: 'The estimate was clear.', created_at: '2026-11-10T12:00:00Z', customer: { name: 'Ana L.' } }],
    2: [{ id: 12, rating: 3, body: 'The repair updates were helpful.', created_at: '2026-11-12T12:00:00Z', customer: { name: 'Miguel R.' } }],
  };
  await mount();
  const feedback = await screen.findByRole('region', { name: 'What customers say' });
  await within(feedback).findByText('The repair updates were helpful.');
  expect([...feedback.querySelectorAll('blockquote')].map(item => item.textContent)).toEqual(['The repair updates were helpful.', 'The estimate was clear.']);
  expect(within(feedback).getByLabelText('3 out of 5 stars')).toBeInTheDocument();
  expect(within(feedback).getByRole('link', { name: 'Repair by Technician 2' })).toHaveAttribute('href', '/technicians/2');
});

it('does not invent testimonials when the public review endpoints are empty', async () => {
  await mount();
  expect(await screen.findByText(/Reviews from completed repairs will appear here/)).toBeInTheDocument();
  expect(document.querySelectorAll('blockquote')).toHaveLength(0);
});

it('lets users retry unavailable customer feedback', async () => {
  reviewFailure = true;
  await mount();
  const feedback = await screen.findByRole('region', { name: 'What customers say' });
  expect(await within(feedback).findByRole('alert')).toHaveTextContent('Customer feedback is unavailable');
  expect(feedback.querySelectorAll('blockquote')).toHaveLength(0);
  reviewFailure = false;
  reviews[1] = [{ id: 1, rating: 4, body: 'Recovered published review.', created_at: '2026-11-12T12:00:00Z', customer: { name: 'Ana' } }];
  await fireEvent.click(within(feedback).getByRole('button', { name: 'Try again' }));
  await within(feedback).findByText('Recovered published review.');
  expect(within(feedback).queryByRole('alert')).not.toBeInTheDocument();
});

it('keeps published CMS headings and FAQs editable after the redesign', async () => {
  publishedTitle = 'Published local repair guidance';
  publishedFaqs = [{ question: 'A published FAQ', answer: 'The current CMS answer.' }];
  await mount();
  await screen.findByRole('heading', { name: 'Published local repair guidance', level: 1 });
  expect(screen.getByText('A published FAQ')).toBeInTheDocument();
  expect(screen.getByText('The current CMS answer.')).toBeInTheDocument();
  expect(screen.queryByText('When does repair work begin?')).not.toBeInTheDocument();
});
