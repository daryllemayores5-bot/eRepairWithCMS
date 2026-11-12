import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/vue';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { createMemoryHistory, createRouter } from 'vue-router';
import { afterEach, expect, it, vi } from 'vitest';
import NotificationsPage from './NotificationsPage.vue';
import { api } from '@/services/api';
import { toast } from 'vue-sonner';

vi.mock('@/services/api', () => ({ api: { get: vi.fn(), patch: vi.fn() } }));
vi.mock('vue-sonner', () => ({ toast: { error: vi.fn() } }));
let client: QueryClient;
afterEach(() => { cleanup(); client?.clear(); vi.clearAllMocks(); });

async function show() {
  client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  vi.mocked(api.get).mockResolvedValue({ data: { data: [{ id: 'notice-1', data: { repair_request_id: 7, message: 'Your repair is ready for pickup.' }, created_at: '2026-11-12T00:00:00Z', read_at: null }], current_page: 1, last_page: 1 } });
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: NotificationsPage }] });
  await router.push('/dashboard/notifications');
  render(NotificationsPage, { global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
}

it('prevents repeated mark-read requests and shows progress until the inbox refreshes', async () => {
  let finish!: (value: unknown) => void;
  vi.mocked(api.patch).mockImplementation(() => new Promise(resolve => { finish = resolve; }));
  await show();
  const button = await screen.findByRole('button', { name: 'Mark as read' });
  await fireEvent.click(button);
  expect(await screen.findByRole('button', { name: 'Marking as read…' })).toBeDisabled();
  await fireEvent.click(button);
  expect(api.patch).toHaveBeenCalledTimes(1);
  vi.mocked(api.get).mockResolvedValue({ data: { data: [], current_page: 1, last_page: 1 } });
  finish({ data: {} });
  await screen.findByRole('heading', { name: 'You are all caught up' });
  expect(api.patch).toHaveBeenCalledWith('/notifications/notice-1/read');
});

it('restores the read action after a failed request so it can be retried', async () => {
  vi.mocked(api.patch).mockRejectedValueOnce(new Error('Read update interrupted'));
  await show();
  await fireEvent.click(await screen.findByRole('button', { name: 'Mark as read' }));
  await waitFor(() => expect(toast.error).toHaveBeenCalledWith('Read update interrupted'));
  expect(screen.getByRole('button', { name: 'Mark as read' })).toBeEnabled();
  expect(screen.getByRole('link', { name: 'Your repair is ready for pickup.' })).toHaveAttribute('href', '/dashboard/repairs/7');
});
