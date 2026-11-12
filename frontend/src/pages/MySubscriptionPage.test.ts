import { cleanup, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import MySubscriptionPage from './MySubscriptionPage.vue';
import ProBadge from '@/components/technician/ProBadge.vue';
import SubscriptionBadge from '@/components/technician/SubscriptionBadge.vue';
import { api, initializeCsrf } from '@/services/api';
import type { SubscriptionOverview } from '@/types/subscription';

const identity = vi.hoisted(() => ({ role: 'technician' }));
vi.mock('@/stores/auth', () => ({ useAuthStore: () => ({ user: { role: identity.role } }) }));
vi.mock('@/services/api', () => ({ api: { get: vi.fn(), post: vi.fn() }, initializeCsrf: vi.fn() }));
let client: QueryClient;
let data: SubscriptionOverview;
beforeEach(() => {
  identity.role = 'technician';
  vi.clearAllMocks();
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  // jsdom has no native dialog focus behavior; browser acceptance stays separate.
  HTMLDialogElement.prototype.showModal = function () { this.open = true; };
  HTMLDialogElement.prototype.close = function () { this.open = false; };
  data = {
    is_demo: true, is_pro: false, current_plan: 'Free', status: 'free', starts_at: null, expires_at: null, days_remaining: 0,
    plans: [
      { id: 1, slug: 'free', name: 'Free', price: '0.00', duration_days: 30, description: 'Free repair inquiries', features: ['Standard finder listing'], is_active: true },
      { id: 2, slug: 'pro', name: 'Pro Technician', price: '199.00', duration_days: 30, description: 'Featured visibility and profile analytics', features: ['Profile analytics'], is_active: true },
      { id: 3, slug: 'premium', name: 'Premium Technician', price: '399.00', duration_days: 30, description: 'All Pro benefits with enhanced visibility and engagement statistics', features: ['All Free and Pro features', 'Date-range analytics'], is_active: true },
    ], pending_payment: null, payments: { data: [], current_page: 1, last_page: 1, total: 0 },
  };
  vi.mocked(api.get).mockImplementation(async (url, config) => {
    if (url === '/technician/subscription/analytics') return { data: { data: { total_profile_views: 3, total_customer_inquiries: 2, months: [{ month: '2026-11', views: 3, inquiries: 2 }], recent_activity: [{ viewed_on: '2026-11-11', views: 3 }],
      ...(data.is_premium ? { premium: { range_days: (config?.params as { range_days?: number } | undefined)?.range_days ?? 30, from: '2026-11-06', through: '2026-11-12', views: 3, inquiries: 2, previous_period: { views: 1, inquiries: 0 }, daily: [{ date: '2026-11-12', views: 3, inquiries: 2 }] } } : {}),
    } } };
    return { data: { data } };
  });
});
afterEach(() => { cleanup(); client.clear(); });
const show = () => render(MySubscriptionPage, { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } });

it('keeps Free functionality visible and analytics locked without making analytics requests', async () => {
  show();
  await screen.findByRole('heading', { name: 'Current plan: Free' });
  expect(screen.getByText('₱199.00')).toBeInTheDocument();
  expect(screen.getByText(/Subscription requests do not charge you/)).toBeInTheDocument();
  expect(screen.getByRole('heading', { name: 'Upgrade to Pro for analytics' })).toBeInTheDocument();
  expect(vi.mocked(api.get).mock.calls.some(([url]) => url.includes('analytics'))).toBe(false);
});

it('requires confirmation, blocks repeated clicks and shows pending administrator approval', async () => {
  let finish!: (value: unknown) => void;
  vi.mocked(api.post).mockImplementation(() => new Promise(resolve => { finish = resolve; }));
  show();
  await fireEvent.click(await screen.findByRole('button', { name: 'Upgrade to Pro' }));
  const dialog = screen.getByRole('dialog', { name: 'Confirm your subscription' });
  expect(api.post).not.toHaveBeenCalled();
  await fireEvent.click(within(dialog).getByRole('button', { name: 'Simulate Payment' }));
  await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
  expect(within(dialog).getByRole('button', { name: 'Submitting request…' })).toBeDisabled();
  expect(api.post).toHaveBeenCalledWith('/technician/subscription/demo-payments', { expected_price: '199.00', plan_slug: 'pro', confirm_demo: true, idempotency_key: expect.any(String) });
  const pending: NonNullable<SubscriptionOverview['pending_payment']> = {
    id: 1, transaction_reference: 'DEMO-EXAMPLE', display_reference: 'SUB-000001', amount: '199.00',
    plan_name: 'Pro Technician', duration_days: 30, status: 'pending', is_demo: true,
    created_at: '2026-11-11T00:00:00Z', approved_at: null, rejected_at: null,
    subscription: { id: 1, status: 'pending', starts_at: null, expires_at: null },
  };
  data = { ...data, pending_payment: pending, payments: { data: [pending], current_page: 1, last_page: 1, total: 1 } };
  finish({ data: { message: 'Subscription request submitted. Pending Admin Approval. You have not been charged.' } });
  await screen.findByText(/Subscription request submitted/);
  await screen.findByText(/Pending Admin Approval · SUB-000001/);
  expect(screen.getByRole('button', { name: 'Upgrade to Pro' })).toBeDisabled();
  expect(initializeCsrf).toHaveBeenCalledTimes(1);
  expect(screen.getByText(/DEMO simulation only/)).toBeInTheDocument();
  expect(screen.queryByText(/DEMO-EXAMPLE/)).not.toBeInTheDocument();
});

it('retains the idempotency key for an ambiguous network retry', async () => {
  vi.mocked(api.post).mockRejectedValueOnce(new Error('Connection interrupted')).mockResolvedValueOnce({ data: { message: 'Subscription request submitted' } });
  show();
  await fireEvent.click(await screen.findByRole('button', { name: 'Upgrade to Pro' }));
  await fireEvent.click(screen.getByRole('button', { name: 'Simulate Payment' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Connection interrupted');
  await waitFor(() => expect(screen.getByRole('button', { name: 'Simulate Payment' })).toBeEnabled());
  await fireEvent.click(screen.getByRole('button', { name: 'Simulate Payment' }));
  await screen.findByText('Subscription request submitted');
  expect(vi.mocked(api.post).mock.calls[0]![1]).toEqual(vi.mocked(api.post).mock.calls[1]![1]);
});

it('shows active dates, renewal and actual Pro analytics', async () => {
  data = { ...data, current_plan: 'Pro Technician', is_pro: true, status: 'active', starts_at: '2026-11-11T00:00:00Z', expires_at: '2026-12-11T00:00:00Z', days_remaining: 30 };
  show();
  await screen.findByRole('heading', { name: 'Current plan: Pro Technician' });
  expect(screen.getByRole('button', { name: 'Renew Subscription' })).toBeEnabled();
  await screen.findByText('2026-11-11 · 3 view(s)');
  expect(screen.getByText('Total customer inquiries')).toBeInTheDocument();
  expect(screen.queryByRole('heading', { name: 'Upgrade to Pro for analytics' })).not.toBeInTheDocument();
});

it('denies a customer page without sending subscription requests', async () => {
  identity.role = 'customer';
  show();
  expect(screen.getByRole('heading', { name: 'Technician account required' })).toBeInTheDocument();
  expect(api.get).not.toHaveBeenCalled();
});

it('supports recoverable subscription loading errors', async () => {
  vi.mocked(api.get).mockRejectedValueOnce(new Error('Subscription connection interrupted'));
  show();
  await fireEvent.click(await screen.findByRole('button', { name: 'Try again' }));
  await screen.findByRole('heading', { name: 'Current plan: Free' });
});

it('only shows a Pro badge when enabled and identifies it as a subscription', () => {
  const view = render(ProBadge, { props: { active: false } });
  expect(screen.queryByText('Pro Technician')).not.toBeInTheDocument();
  view.unmount();
  render(ProBadge, { props: { active: true } });
  expect(screen.getByText(/subscription badge, not verification/)).toBeInTheDocument();
});

it('refreshes finder caches when approval arrives and clears Pro analytics after expiration', async () => {
  const keys = ['my-technician-profile', 'technicians', 'technician', 'featured-technicians', 'request-technicians'];
  for (const key of keys) client.setQueryData([key], { is_pro: false });
  show();
  await screen.findByRole('heading', { name: 'Current plan: Free' });
  client.setQueryData(['my-subscription', 1], { ...data, is_pro: true, status: 'active', current_plan: 'Pro Technician', expires_at: '2026-12-11T00:00:00Z' });
  await screen.findByText('2026-11-11 · 3 view(s)');
  for (const key of keys) expect(client.getQueryState([key])?.isInvalidated).toBe(true);
  client.setQueryData(['my-subscription', 1], { ...data, status: 'expired' });
  await screen.findByRole('heading', { name: 'Upgrade to Pro for analytics' });
  await waitFor(() => expect(client.getQueriesData({ queryKey: ['subscription-analytics'] })).toHaveLength(0));
});

it('keeps the confirmed price stable when plan polling changes the listed price', async () => {
  vi.mocked(api.post).mockResolvedValueOnce({ data: { message: 'Subscription request submitted' } });
  show();
  await fireEvent.click(await screen.findByRole('button', { name: 'Upgrade to Pro' }));
  const dialog = screen.getByRole('dialog');
  client.setQueryData(['my-subscription', 1], { ...data, plans: data.plans.map(plan => plan.slug === 'pro' ? { ...plan, price: '249.00' } : plan) });
  await screen.findByText('₱249.00');
  expect(within(dialog).getByText(/₱199.00 for 30 days/)).toBeInTheDocument();
  await fireEvent.click(within(dialog).getByRole('button', { name: 'Simulate Payment' }));
  await screen.findByText('Subscription request submitted');
  expect(api.post).toHaveBeenCalledWith('/technician/subscription/demo-payments', expect.objectContaining({ expected_price: '199.00' }));
});

it('offers three plans and submits the selected Premium quote for simulated payment', async () => {
  vi.mocked(api.post).mockResolvedValueOnce({ data: { message: 'Pending Admin Approval. You have not been charged.' } });
  show();
  await screen.findByRole('heading', { name: 'Current plan: Free' });
  expect(screen.getByRole('region', { name: 'Free plan' })).toHaveTextContent('Your active plan');
  expect(screen.getByRole('region', { name: 'Pro Technician plan' })).toHaveTextContent('₱199.00');
  expect(screen.getByRole('region', { name: 'Premium Technician plan' })).toHaveTextContent('₱399.00');
  await fireEvent.click(screen.getByRole('button', { name: 'Upgrade to Premium' }));
  const dialog = screen.getByRole('dialog');
  expect(within(dialog).getByText(/Premium Technician · ₱399.00 for 30 days/)).toBeInTheDocument();
  expect(within(dialog).getByText(/schedules the new plan after your current paid period ends/)).toBeInTheDocument();
  await fireEvent.click(within(dialog).getByRole('button', { name: 'Simulate Payment' }));
  await waitFor(() => expect(api.post).toHaveBeenCalledWith('/technician/subscription/demo-payments', expect.objectContaining({ plan_slug: 'premium', expected_price: '399.00', confirm_demo: true })));
});

it('highlights active Premium, supports renewal and requests enhanced analytics for the chosen range', async () => {
  data = { ...data, current_plan: 'Premium Technician', current_plan_slug: 'premium', is_pro: true, is_premium: true, status: 'active', expires_at: '2026-12-12T00:00:00Z' };
  show();
  await screen.findByRole('heading', { name: 'Current plan: Premium Technician' });
  expect(screen.getByRole('region', { name: 'Premium Technician plan' })).toHaveTextContent('Your active plan');
  expect(screen.getByRole('button', { name: 'Renew Subscription' })).toBeEnabled();
  await screen.findByRole('heading', { name: 'Premium engagement statistics' });
  await fireEvent.update(screen.getByRole('combobox', { name: 'Date range' }), '7');
  await waitFor(() => expect(api.get).toHaveBeenCalledWith('/technician/subscription/analytics', expect.objectContaining({ params: { range_days: 7 } })));
  expect(await screen.findByText('Previous period: 1')).toBeInTheDocument();
  expect(screen.getByRole('table', { name: 'Daily profile views and inquiries for the selected date range' })).toHaveTextContent('2026-11-12');
});

it('shows the queued Premium period while keeping Pro active and blocking conflicting requests', async () => {
  data = { ...data, current_plan: 'Pro Technician', current_plan_slug: 'pro', is_pro: true, status: 'active', scheduled_subscription: {
    id: 5, status: 'scheduled', starts_at: '2026-12-12T00:00:00Z', expires_at: '2027-01-11T00:00:00Z', plan: data.plans[2]!,
  } };
  show();
  await screen.findByRole('heading', { name: 'Current plan: Pro Technician' });
  expect(screen.getByText(/Scheduled plan: Premium Technician/)).toBeInTheDocument();
  expect(screen.getByRole('button', { name: 'Upgrade to Premium' })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'Renew Subscription' })).toBeDisabled();
  expect(screen.queryByRole('heading', { name: 'Premium engagement statistics' })).not.toBeInTheDocument();
});

it('renders Premium and Pro subscription badges distinctly and gives Free no badge', () => {
  const badge = render(SubscriptionBadge, { props: { tier: 'premium' } });
  expect(screen.getByText('Premium Technician')).toHaveAttribute('title', expect.stringContaining('does not indicate verification'));
  badge.unmount();
  const pro = render(SubscriptionBadge, { props: { tier: 'pro' } });
  expect(screen.getByText('Pro Technician')).toBeInTheDocument();
  pro.unmount();
  render(SubscriptionBadge, { props: { tier: 'free' } });
  expect(screen.queryByText(/Technician/)).not.toBeInTheDocument();
});
