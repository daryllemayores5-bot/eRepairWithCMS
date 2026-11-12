import { render, screen, fireEvent, cleanup, waitFor } from '@testing-library/vue';
import { createRouter, createMemoryHistory } from 'vue-router';
import { afterEach, expect, it, vi } from 'vitest';
import PasswordPage from './PasswordPage.vue';
import { api, initializeCsrf } from '@/services/api';

vi.mock('@/services/api', () => ({ api: { post: vi.fn() }, initializeCsrf: vi.fn() }));
afterEach(() => { cleanup(); vi.clearAllMocks(); });

it('prevents overlapping reset requests and clears an old success when a new request fails', async () => {
  const router = createRouter({ history: createMemoryHistory(), routes: [
    { path: '/forgot-password', component: PasswordPage },
    { path: '/login', component: { template: '<div />' } },
  ] });
  await router.push('/forgot-password');
  let finish!: () => void;
  vi.mocked(initializeCsrf).mockImplementationOnce(() => new Promise<void>(resolve => { finish = resolve; }));
  vi.mocked(api.post).mockResolvedValueOnce({ data: { message: 'Reset link sent' } });
  render(PasswordPage, { global: { plugins: [router] } });
  await fireEvent.update(screen.getByRole('textbox', { name: 'Email' }), 'customer@example.test');
  const form = screen.getByRole('textbox', { name: 'Email' }).closest('form')!;
  await fireEvent.submit(form);
  await fireEvent.submit(form);
  expect(initializeCsrf).toHaveBeenCalledTimes(1);
  finish();
  await screen.findByRole('status');
  expect(api.post).toHaveBeenCalledTimes(1);
  vi.mocked(api.post).mockRejectedValueOnce(new Error('Connection interrupted'));
  await waitFor(() => expect(screen.getByRole('button', { name: 'Send reset link' })).toBeEnabled());
  await fireEvent.submit(form);
  expect(await screen.findByRole('alert')).toHaveTextContent('Connection interrupted');
  expect(screen.queryByText('Reset link sent')).not.toBeInTheDocument();
});
