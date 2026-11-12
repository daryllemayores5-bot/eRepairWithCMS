import { cleanup, fireEvent, render, screen, within } from '@testing-library/vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import TechnicianPlans from './TechnicianPlans.vue';
import { api } from '@/services/api';
import type { SubscriptionPlan } from '@/types/subscription';

const identity = vi.hoisted(() => ({ role: 'guest' }));
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ user: identity.role === 'guest' ? null : { role: identity.role } }) }));
vi.mock('@/services/api', () => ({ api: { get: vi.fn() } }));
const clients: QueryClient[] = [];
let plans: SubscriptionPlan[];

beforeEach(() => {
  identity.role = 'guest';
  plans = [
    { id: 1, slug: 'free', name: 'Free', description: 'Start your repair profile.', price: '0.00', duration_days: 30, features: ['Standard finder listing', 'Customer repair inquiries'], is_active: true },
    { id: 2, slug: 'pro', name: 'Pro Technician', description: 'Optional visibility and analytics.', price: '199.00', duration_days: 30, features: ['Pro subscription badge', 'Profile view and inquiry analytics'], is_active: true },
    { id: 3, slug: 'premium', name: 'Premium Technician', description: 'Enhanced engagement insights.', price: '399.00', duration_days: 30, features: ['All Free and Pro features', '7, 30 and 90-day analytics filters'], is_active: true },
  ];
  vi.mocked(api.get).mockResolvedValue({ data: { data: plans } });
});
afterEach(() => { cleanup(); clients.splice(0).forEach(client => client.clear()); vi.clearAllMocks(); });

async function mount() {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/', component: TechnicianPlans },
    { path: '/register/technician', component: { template: '<div>Technician registration</div>' } },
    { path: '/dashboard/subscription', component: { template: '<div>Technician subscriptions</div>' } },
  ] });
  await router.push('/'); await router.isReady();
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  clients.push(client);
  render(TechnicianPlans, { global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
  return { router, client };
}

it('lets guests compare the three plans with clear customer, verification and demo explanations', async () => {
  await mount();
  const free = await screen.findByRole('article', { name: 'Free subscription plan' });
  expect(within(free).getByText('₱0.00')).toBeInTheDocument();
  expect(within(free).getByText('No subscription fee')).toBeInTheDocument();
  expect(within(free).queryByText('/ 30 days')).not.toBeInTheDocument();
  const pro = screen.getByRole('article', { name: 'Pro Technician subscription plan' });
  expect(within(pro).getByText('₱199.00')).toBeInTheDocument();
  const premium = screen.getByRole('article', { name: 'Premium Technician subscription plan' });
  expect(within(premium).getByText('₱399.00')).toBeInTheDocument();
  expect(within(premium).getByText('7, 30 and 90-day analytics filters')).toBeInTheDocument();
  expect(screen.getByText(/Customers can search and request repairs without a subscription/)).toBeInTheDocument();
  expect(screen.getByText(/Paid badges show subscription status, not verified skills/)).toBeInTheDocument();
  expect(screen.getByText(/No real money is collected/)).toBeInTheDocument();
  expect(screen.getByText('Repair costs are quoted separately by your technician.')).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Create technician account' })).toHaveAttribute('href', '/register/technician');
  expect(screen.queryByRole('button', { name: /Simulate Payment/i })).not.toBeInTheDocument();
  expect(api.get).toHaveBeenCalledWith('/subscription-plans', expect.objectContaining({ signal: expect.any(AbortSignal) }));
});

it('shows current API amounts and duration instead of fixed marketing prices', async () => {
  plans[2].price = '425.00'; plans[2].duration_days = 45;
  plans[2].features = ['Updated analytics benefit'];
  await mount();
  const premium = await screen.findByRole('article', { name: 'Premium Technician subscription plan' });
  expect(within(premium).getByText('₱425.00')).toBeInTheDocument();
  expect(within(premium).getByText('/ 45 days')).toBeInTheDocument();
  expect(within(premium).getByText('Updated analytics benefit')).toBeInTheDocument();
  expect(screen.queryByText('₱399.00')).not.toBeInTheDocument();
});

it('links an existing technician to the subscription workspace', async () => {
  identity.role = 'technician';
  await mount();
  expect(await screen.findByRole('link', { name: 'View plans in my workspace' })).toHaveAttribute('href', '/dashboard/subscription');
  expect(screen.queryByRole('link', { name: 'Create technician account' })).not.toBeInTheDocument();
});

it.each(['customer', 'admin'])('does not send a signed-in %s into technician checkout', async role => {
  identity.role = role;
  await mount();
  await screen.findByRole('article', { name: 'Premium Technician subscription plan' });
  expect(screen.queryByRole('link', { name: 'View plans in my workspace' })).not.toBeInTheDocument();
  expect(screen.queryByRole('link', { name: 'Create technician account' })).not.toBeInTheDocument();
  expect(screen.getByText('These optional plans are for technician accounts.')).toBeInTheDocument();
});

it('recovers a failed public catalog request without inventing plan prices', async () => {
  vi.mocked(api.get).mockRejectedValueOnce(new Error('Plan details could not load'));
  await mount();
  expect(await screen.findByRole('alert')).toHaveTextContent('Plan details could not load');
  expect(screen.queryAllByRole('article')).toHaveLength(0);
  await fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
  await screen.findByRole('article', { name: 'Premium Technician subscription plan' });
  expect(screen.queryByRole('alert')).not.toBeInTheDocument();
});

it('handles an empty catalog without showing unavailable plans or signup offers', async () => {
  vi.mocked(api.get).mockResolvedValue({ data: { data: [] } });
  await mount();
  await screen.findByRole('heading', { name: 'Plan details are currently unavailable' });
  expect(screen.queryAllByRole('article')).toHaveLength(0);
  expect(screen.queryByRole('link', { name: 'Create technician account' })).not.toBeInTheDocument();
});
