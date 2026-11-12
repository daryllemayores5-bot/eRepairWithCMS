import { cleanup, render, screen, within } from '@testing-library/vue';
import { afterEach, expect, it, vi } from 'vitest';
import SubscriptionHistory from './SubscriptionHistory.vue';
import type { DemoPayment } from '@/types/subscription';

afterEach(() => { cleanup(); vi.useRealTimers(); });
const payment = (id: number, status: DemoPayment['status'], cycle: DemoPayment['subscription']['status'], starts: string | null = null, expires: string | null = null): DemoPayment => ({
  id, amount: '399.00', plan_name: 'Premium Technician', duration_days: 30,
  transaction_reference: `DEMO-INTERNAL-${id}`, display_reference: `SUB-${id}`, status, is_demo: true,
  created_at: '2026-11-01T00:00:00Z', approved_at: status === 'approved' ? '2026-11-01T00:00:00Z' : null, rejected_at: status === 'rejected' ? '2026-11-02T00:00:00Z' : null,
  subscription: { id, status: cycle, starts_at: starts, expires_at: expires },
});

it('distinguishes replaced renewal cycles from rejected requests in cards and the desktop table', () => {
  render(SubscriptionHistory, { props: { payments: [payment(1, 'approved', 'cancelled'), payment(2, 'rejected', 'cancelled'), payment(3, 'pending', 'pending')] } });
  const cards = within(screen.getByRole('list', { name: 'Subscription request cards' }));
  expect(cards.getByText('Replaced by renewal')).toBeInTheDocument();
  expect(cards.getByText('Not activated')).toBeInTheDocument();
  expect(cards.getByText('Pending Admin Approval')).toBeInTheDocument();
  expect(cards.queryByText(/DEMO-INTERNAL/)).not.toBeInTheDocument();
  const table = within(screen.getByRole('table', { name: 'Your subscription requests and access periods' }));
  expect(table.getByText('Replaced by renewal')).toBeInTheDocument();
  expect(table.getByText('Not activated')).toBeInTheDocument();
  expect(screen.getByRole('region', { name: 'Subscription history table' })).toHaveAttribute('tabindex', '0');
});

it('shows scheduled, current and expired access correctly at period boundaries', () => {
  vi.useFakeTimers();
  vi.setSystemTime(new Date('2026-11-12T00:00:00Z'));
  render(SubscriptionHistory, { props: { payments: [
    payment(1, 'approved', 'scheduled', '2026-12-01T00:00:00Z', '2026-12-31T00:00:00Z'),
    payment(2, 'approved', 'scheduled', '2026-11-12T00:00:00Z', '2026-12-12T00:00:00Z'),
    payment(3, 'approved', 'active', '2026-10-13T00:00:00Z', '2026-11-12T00:00:00Z'),
  ] } });
  const cards = within(screen.getByRole('list', { name: 'Subscription request cards' }));
  expect(cards.getByText('Scheduled')).toBeInTheDocument();
  expect(cards.getByText('Active')).toBeInTheDocument();
  expect(cards.getByText('Expired')).toBeInTheDocument();
});
