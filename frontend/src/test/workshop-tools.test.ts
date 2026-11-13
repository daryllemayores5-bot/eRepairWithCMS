import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/vue';
import { createRouter, createMemoryHistory } from 'vue-router';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import DocumentsTemplatesPage from '@/pages/DocumentsTemplatesPage.vue';
import PartsInventoryPage from '@/pages/PartsInventoryPage.vue';
import BusinessReportsPage from '@/pages/BusinessReportsPage.vue';
import RepairNoteEditor from '@/components/technician/RepairNoteEditor.vue';
import { api } from '@/services/api';
import { downloadWorkshopFile } from '@/services/workshop-download';
import type { Component } from 'vue';
import { formatWorkshopMoney } from '@/lib/workshop-money';

const identity = vi.hoisted(() => ({ user: { id: 7, role: 'technician' } }));
vi.mock('@/stores/auth', () => ({ useAuthStore: () => identity }));
vi.mock('@/services/api', () => ({ api: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), put: vi.fn(), delete: vi.fn() }, initializeCsrf: vi.fn() }));
vi.mock('@/services/workshop-download', () => ({ downloadWorkshopFile: vi.fn() }));
let client: QueryClient;
let tier: 'free' | 'pro' | 'premium';
let stock = 5;
it('preserves exact report centavos for large decimal totals', () => {
  expect(formatWorkshopMoney('9999999999990000.01')).toBe('₱9,999,999,999,990,000.01');
  expect(formatWorkshopMoney('0.30')).toBe('₱0.30');
});
const template = { id: 1, title: 'Pickup instructions', body: 'Bring your repair reference when collecting your device.', updated_at: '2026-11-13T00:00:00Z' };
beforeEach(() => {
  vi.clearAllMocks();
  identity.user.role = 'technician'; tier = 'free'; stock = 5;
  client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } });
  Element.prototype.scrollIntoView = vi.fn();
  vi.mocked(api.get).mockImplementation(async (url) => {
    if (url === '/technician/workshop') return { data: { data: { tier, documents: tier !== 'free', templates: tier !== 'free', inventory: tier === 'premium', business_reports: tier === 'premium' } } };
    if (url === '/technician/note-templates') return { data: { data: [template] } };
    if (url === '/technician/workshop/repairs') return { data: { data: { data: [{ id: 15, reference: 'ER-15', model: 'Phone', status: 'in_repair', estimates_count: 1, customer: { id: 3, name: 'Ana' } }], current_page: 1, last_page: 1 } } };
    if (url === '/technician/inventory') return { data: { data: { data: [{ id: 4, sku: 'BAT-01', name: 'Phone battery', quantity: stock, reorder_level: 2, unit_cost: '450.00', active: true }], current_page: 1, last_page: 1 }, low_stock_count: stock <= 2 ? 1 : 0 } };
    if (url === '/technician/inventory-movements') return { data: { data: { data: [], current_page: 1, last_page: 1 } } };
    if (url === '/technician/business-reports') return { data: { data: { from: '2026-11-01', through: '2026-11-13', requests_received: 3, completed_repairs: 2, completed_approved_estimates: '2100.50', parts_used: 3, parts_cost: '450.00', low_stock_items: 1, status_counts: { pending: 1, completed: 2 }, scope: 'Estimates are not collected revenue.' } } };
    throw new Error('Unexpected API request: ' + url);
  });
  vi.mocked(api.post).mockResolvedValue({ data: {} });
});
afterEach(() => { cleanup(); client.clear(); });
async function show(component: Component, props = {}) {
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:pathMatch(.*)*', component: { template: '<div />' } }] });
  await router.push('/dashboard');
  return render(component, { props, global: { plugins: [router, [VueQueryPlugin, { queryClient: client }]] } });
}

it('shows the Pro upgrade with no private template or document requests for a Free technician', async () => {
  await show(DocumentsTemplatesPage);
  expect(await screen.findByRole('heading', { name: 'Create documents and save time on updates' })).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Compare plans and upgrade' })).toHaveAttribute('href', '/dashboard/subscription');
  expect(api.get).toHaveBeenCalledTimes(1);
});

it('lets a Pro technician save a template and download a job sheet', async () => {
  tier = 'pro';
  await show(DocumentsTemplatesPage);
  await screen.findByRole('heading', { name: 'My saved templates' });
  await fireEvent.update(screen.getByRole('textbox', { name: 'Template title' }), 'Diagnosis');
  await fireEvent.update(screen.getByRole('textbox', { name: 'Reusable note' }), 'Testing the charging circuit before quoting.');
  await fireEvent.click(screen.getByRole('button', { name: 'Save template' }));
  await waitFor(() => expect(api.post).toHaveBeenCalledWith('/technician/note-templates', { title: 'Diagnosis', body: 'Testing the charging circuit before quoting.' }));
  await screen.findByText(/Template saved/);
  await fireEvent.click(screen.getByRole('button', { name: 'Download job sheet' }));
  await waitFor(() => expect(downloadWorkshopFile).toHaveBeenCalledWith('/technician/workshop/repairs/15/documents/job-sheet', 'ER-15-job-sheet.pdf'));
});

it('inserts a saved template into an editable repair note before explicitly posting', async () => {
  tier = 'pro';
  const view = await show(RepairNoteEditor, { busy: false });
  await screen.findByRole('option', { name: template.title });
  await fireEvent.update(screen.getByRole('combobox', { name: 'Use a saved note template' }), '1');
  const note = screen.getByRole('textbox', { name: 'Add repair note' });
  expect(note).toHaveValue(template.body);
  expect(view.emitted('submit')).toBeUndefined();
  await fireEvent.update(note, 'Device is ready. Please bring your reference.');
  await fireEvent.click(screen.getByRole('button', { name: 'Add note' }));
  expect(view.emitted('submit')?.[0]).toEqual(['Device is ready. Please bring your reference.']);
});

it('locks inventory for Pro and does not fetch stock', async () => {
  tier = 'pro';
  await show(PartsInventoryPage);
  expect(await screen.findByRole('heading', { name: 'Manage the parts behind every repair' })).toBeInTheDocument();
  expect(vi.mocked(api.get).mock.calls.some(([path]) => path === '/technician/inventory')).toBe(false);
});

it('records repair-linked parts usage once while a request is pending and refreshes stock alerts', async () => {
  tier = 'premium';
  let finish!: (value: unknown) => void;
  vi.mocked(api.post).mockImplementationOnce(() => new Promise(resolve => { finish = resolve; }));
  await show(PartsInventoryPage);
  await fireEvent.click(await screen.findByRole('button', { name: 'Use on repair' }));
  await fireEvent.update(screen.getByRole('spinbutton', { name: 'Quantity' }), '3');
  await fireEvent.update(screen.getByRole('combobox', { name: 'Your in-progress repair' }), '15');
  const confirm = screen.getByRole('button', { name: 'Confirm parts used' });
  await fireEvent.click(confirm);
  await fireEvent.click(confirm);
  await waitFor(() => expect(api.post).toHaveBeenCalledTimes(1));
  expect(api.post).toHaveBeenCalledWith('/technician/inventory/4/movements', { kind: 'usage', quantity: 3, note: '', repair_request_id: 15, idempotency_key: expect.any(String) });
  expect(confirm).toBeDisabled();
  stock = 2; finish({ data: {} });
  await screen.findByText(/Stock movement recorded/);
  expect(await screen.findByText(/2 in stock/)).toHaveTextContent('Low stock');
  expect(screen.getByText('1 low-stock items.')).toBeInTheDocument();
});

it('lets Premium filter business reports and export the applied period', async () => {
  tier = 'premium';
  await show(BusinessReportsPage);
  await screen.findByRole('heading', { name: 'Request statuses' });
  await fireEvent.update(screen.getByLabelText('From (Philippine date)'), '2026-11-01');
  await fireEvent.update(screen.getByLabelText('Through (Philippine date)'), '2026-11-13');
  await fireEvent.click(screen.getByRole('button', { name: 'Apply dates' }));
  await waitFor(() => expect(api.get).toHaveBeenCalledWith('/technician/business-reports', expect.objectContaining({ params: { from: '2026-11-01', through: '2026-11-13' } })));
  await fireEvent.click(screen.getByRole('button', { name: 'Download CSV report' }));
  await waitFor(() => expect(downloadWorkshopFile).toHaveBeenCalledWith('/technician/business-reports/export', 'eRepair-business-2026-11-01-2026-11-13.csv', { from: '2026-11-01', through: '2026-11-13', format: 'csv' }));
  expect(screen.getByText('Estimates are not collected revenue.')).toBeInTheDocument();
});
