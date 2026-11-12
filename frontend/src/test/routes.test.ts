import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query';
import { render, screen, fireEvent, within, cleanup, waitFor } from '@testing-library/vue';
import { createMemoryHistory } from 'vue-router';
import { createPinia, disposePinia, type Pinia } from 'pinia';
import { afterEach, describe, expect, it, vi } from 'vitest';
import App from '@/App.vue';
import { createAppRouter } from '@/router';
import { api } from '@/services/api';
const {
  identity
} = vi.hoisted(() => ({
  identity: {
    role: 'guest'
  }
}));
vi.mock('@/stores/auth', () => ({
  useAuthStore: () => ({
    user: identity.role === 'guest' ? null : {
      id: 1,
      name: 'Ana Reyes',
      email: 'ana@example.test',
      role: identity.role
    },
    pending: false,
    error: false,
    refresh: vi.fn(),
    logout: vi.fn()
  })
}));
vi.mock('vue-sonner', () => ({
  Toaster: {
    template: '<div />'
  },
  toast: {
    success: vi.fn(),
    error: vi.fn(),
    info: vi.fn()
  }
}));
vi.mock('@/services/api', () => {
  const profile = {
    id: 1,
    name: 'Luis Santos',
    biography: 'Experienced professional for device diagnosis and battery repairs.',
    city: 'Santa Cruz',
    province: 'Laguna',
    years_experience: 5,
    photo_url: null,
    latitude: null,
    longitude: null,
    availability: 'available',
    verified: true,
    verification_status: 'approved',
    average_rating: 0,
    reviews_count: 0,
    specializations: [{
      id: 1,
      name: 'Battery'
    }],
    device_types: [{
      id: 1,
      name: 'Laptop'
    }],
    service_areas: [],
    matching: {
      score: 10,
      reasons: {
        Available: 10
      }
    }
  };
  const repair = {
    id: 1,
    reference: 'ER-ROUTE-001',
    status: 'completed',
    model: 'Aspire',
    serial_number: null,
    problem_description: 'Battery no longer holds a charge.',
    device_type: 'Laptop',
    device_brand: 'Acer',
    category: 'Battery',
    created_at: '2026-11-02T00:00:00Z',
    completed_at: '2026-11-03T00:00:00Z',
    customer: {
      id: 1,
      name: 'Ana Reyes'
    },
    technician: {
      id: 1,
      name: 'Luis Santos'
    },
    history: [],
    estimates: [],
    appointments: [],
    attachments: [],
    review: null
  };
  const page = (data: unknown[]) => ({
    data,
    meta: {
      current_page: 1,
      last_page: 1,
      total: data.length
    }
  });
  return {
    initializeCsrf: vi.fn(),
    getHealth: vi.fn(async () => ({
      application: 'eRepair',
      status: 'ok',
      database: 'connected'
    })),
    api: {
      get: vi.fn(async (url: string) => {
        if (url === '/lookups') return {
          data: {
            data: {
              device_types: profile.device_types,
              device_brands: [{
                id: 1,
                name: 'Acer'
              }],
              specializations: profile.specializations,
              repair_categories: [{
                id: 1,
                name: 'Battery'
              }]
            }
          }
        };
        if (url === '/content') return {
          data: {
            data: {
              hero_title: null,
              hero_subtitle: null,
              faqs: [],
              announcements: [],
              services: [{ slug: 'laptop-diagnosis', title: 'Laptop diagnosis guide', summary: 'A published service introduction.', content: 'Discuss the diagnosis and estimate with your technician.' }],
              pages: ['about', 'privacy', 'terms'].map(slug => ({
                slug,
                title: {
                  about: 'About eRepair',
                  privacy: 'Privacy policy',
                  terms: 'Terms and conditions'
                }[slug],
                content: 'Published website content.'
              }))
            }
          }
        };
        if (url === '/subscription-plans') return {
          data: { data: [
            { id: 1, slug: 'free', name: 'Free', description: 'Free profile.', price: '0.00', duration_days: 30, features: ['Standard finder listing'], is_active: true },
            { id: 2, slug: 'pro', name: 'Pro Technician', description: 'Optional insights.', price: '199.00', duration_days: 30, features: ['Profile analytics'], is_active: true },
            { id: 3, slug: 'premium', name: 'Premium Technician', description: 'Enhanced insights.', price: '399.00', duration_days: 30, features: ['Date-range analytics'], is_active: true },
          ] },
        };
        if (url === '/dashboard') return {
          data: {
            data: {
              total: 0,
              counts: {},
              activity: [],
              recent: [],
              appointments: [],
              average_rating: null
            }
          }
        };
        if (url === '/technician/profile' || url === '/technicians/1') return {
          data: {
            data: profile
          }
        };
        if (url === '/repair-requests/1') return {
          data: {
            data: repair
          }
        };
        if (url.startsWith('/repair-requests?')) return {
          data: page([repair])
        };
        if (url.startsWith('/technicians') && !url.includes('/reviews')) return {
          data: page([profile])
        };
        return {
          data: page([])
        };
      })
    }
  };
});
const routes = [['/', 'Find the Right Repair Technician for Your Device', 'guest'], ['/how-it-works', 'Good repairs start with the right person.', 'guest'], ['/technicians', 'Find your repair technician', 'guest'], ['/technicians/1', 'Luis Santos', 'guest'], ['/compare?ids=1', 'Compare technicians', 'guest'], ['/pages/about', 'About eRepair', 'guest'], ['/pages/privacy', 'Privacy policy', 'guest'], ['/pages/terms', 'Terms and conditions', 'guest'], ['/pages/contact', 'Page not published', 'guest'], ['/login', 'Sign in to eRepair', 'guest'], ['/register', 'Create your account', 'guest'], ['/register/technician', 'Join as a technician', 'guest'], ['/forgot-password', 'Reset your password', 'guest'], ['/reset-password?token=fixture&email=ana@example.test', 'Choose a new password', 'guest'], ['/install', 'Find the Right Repair Technician for Your Device', 'guest'], ['/setup', 'Check your connection', 'guest'], ['/missing', 'Page not found', 'guest'], ['/dashboard', 'Hello, Ana', 'customer'], ['/dashboard/repairs', 'My repair requests', 'customer'], ['/dashboard/active-repairs', 'Active repairs', 'customer'], ['/dashboard/history', 'Repair history', 'customer'], ['/dashboard/repairs/new', 'Request a repair', 'customer'], ['/dashboard/repairs/1', 'ER-ROUTE-001', 'customer'], ['/dashboard/appointments', 'Appointments', 'customer'], ['/dashboard/notifications', 'Notifications', 'customer'], ['/dashboard/profile', 'Profile settings', 'customer'], ['/dashboard', 'Hello, Ana', 'technician'], ['/dashboard/repairs', 'Repair requests', 'technician'], ['/dashboard/active-repairs', 'Active repairs', 'technician'], ['/dashboard/history', 'Repair history', 'technician'], ['/dashboard/availability', 'Your availability', 'technician'], ['/dashboard/reviews', 'Customer reviews', 'technician'], ['/dashboard/profile', 'Profile settings', 'technician']] as const;
const clients: QueryClient[] = [],
  stores: Pinia[] = [];
afterEach(() => {
  cleanup();
  clients.splice(0).forEach(c => c.clear());
  stores.splice(0).forEach(disposePinia);
});
async function open(path: string, role: string) {
  identity.role = role;
  const client = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
          gcTime: 0
        }
      }
    }),
    pinia = createPinia(),
    router = createAppRouter(createMemoryHistory());
  clients.push(client);
  stores.push(pinia);
  await router.push(path);
  await router.isReady();
  render(App, {
    global: {
      plugins: [pinia, [VueQueryPlugin, {
        queryClient: client
      }], router]
    }
  });
  return router;
}
describe('all existing routes in Vue', () => {
  it('closes the public mobile menu with Escape and returns focus to its toggle', async () => {
    await open('/', 'guest');
    const toggle = screen.getByRole('button', { name: 'Open menu' });
    await fireEvent.click(toggle);
    expect(toggle).toHaveAttribute('aria-expanded', 'true');
    const link = within(screen.getByRole('navigation', { name: 'Main navigation' })).getByRole('link', { name: 'Find technicians' });
    link.focus();
    await fireEvent.keyDown(link, { key: 'Escape' });
    await waitFor(() => expect(toggle).toHaveFocus());
    expect(toggle).toHaveAttribute('aria-expanded', 'false');
  }, 15000);
  it('closes workspace navigation with Escape and after programmatic route changes', async () => {
    const router = await open('/dashboard', 'technician');
    await screen.findByRole('heading', { name: 'Hello, Ana' });
    const toggle = screen.getByRole('button', { name: 'Open workspace menu' });
    await fireEvent.click(toggle);
    const link = within(screen.getByRole('navigation', { name: 'Dashboard navigation' })).getByRole('link', { name: 'Repair requests' });
    link.focus();
    await fireEvent.keyDown(link, { key: 'Escape' });
    await waitFor(() => expect(toggle).toHaveFocus());
    await fireEvent.click(toggle);
    await router.push('/dashboard/history');
    await screen.findByRole('heading', { name: 'Repair history' });
    expect(screen.getByRole('button', { name: 'Open workspace menu' })).toHaveAttribute('aria-expanded', 'false');
  });
  it('keeps sign-in labels stable and connects validation messages to the invalid field', async () => {
    await open('/login', 'guest');
    const email = await screen.findByRole('textbox', { name: 'Email' });
    await fireEvent.update(email, 'invalid-email');
    await fireEvent.submit(email.closest('form')!);
    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Email' })).toHaveFocus());
    expect(email).toHaveAttribute('aria-invalid', 'true');
    expect(document.getElementById(email.getAttribute('aria-describedby')!)).not.toBeEmptyDOMElement();
  });
  it('focuses invalid wizard controls and announces the next step through its heading', async () => {
    await open('/dashboard/repairs/new', 'customer');
    await screen.findByRole('heading', { name: 'Tell us about your device' });
    await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Device type' })).toHaveFocus());
    await fireEvent.update(screen.getByRole('combobox', { name: 'Device type' }), '1');
    await fireEvent.update(screen.getByRole('combobox', { name: 'Brand' }), '1');
    await fireEvent.update(screen.getByRole('textbox', { name: 'Model' }), 'Aspire 5');
    await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    const heading = await screen.findByRole('heading', { name: 'What needs fixing?' });
    await waitFor(() => expect(heading).toHaveFocus());
    await fireEvent.update(screen.getByRole('combobox', { name: 'Problem category' }), '1');
    await fireEvent.update(screen.getByRole('textbox', { name: 'Problem description' }), 'x');
    await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
    const description = screen.getByRole('textbox', { name: 'Problem description' });
    await waitFor(() => expect(description).toHaveFocus());
    expect(description).toHaveAttribute('aria-invalid', 'true');
    expect(document.getElementById(description.getAttribute('aria-describedby')!)).not.toBeEmptyDOMElement();
  });
  it('recovers matching-technician loading errors without losing the repair form', async () => {
    const original = vi.mocked(api.get).getMockImplementation()!;
    let failed = false;
    vi.mocked(api.get).mockImplementation(async (...args) => {
      if (args[0] === '/technicians' && !failed) { failed = true; throw new Error('Technician search interrupted'); }
      return original(...args);
    });
    try {
      await open('/dashboard/repairs/new', 'customer');
      await screen.findByRole('heading', { name: 'Tell us about your device' });
      await fireEvent.update(screen.getByRole('combobox', { name: 'Device type' }), '1');
      await fireEvent.update(screen.getByRole('combobox', { name: 'Brand' }), '1');
      await fireEvent.update(screen.getByRole('textbox', { name: 'Model' }), 'Aspire 5');
      await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
      await fireEvent.update(screen.getByRole('combobox', { name: 'Problem category' }), '1');
      await fireEvent.update(screen.getByRole('textbox', { name: 'Problem description' }), 'Battery no longer holds a charge after charging overnight.');
      await fireEvent.click(screen.getByRole('button', { name: 'Continue' }));
      expect(await screen.findByRole('alert')).toHaveTextContent('Technician search interrupted');
      await fireEvent.click(screen.getByRole('button', { name: 'Try again' }));
      const technician = await screen.findByRole('radio', { name: /Luis Santos/ });
      expect(technician.closest('label')).toHaveTextContent('No reviews yet');
      await fireEvent.click(screen.getByRole('button', { name: 'Back' }));
      expect(screen.getByRole('textbox', { name: 'Problem description' })).toHaveValue('Battery no longer holds a charge after charging overnight.');
    } finally {
      vi.mocked(api.get).mockImplementation(original);
    }
  });
  it('shows published CMS service content on the homepage without changing device finder links', async () => {
    await open('/', 'guest');
    expect(await screen.findByRole('heading', { name: 'Laptop diagnosis guide' })).toBeInTheDocument();
    expect(screen.getByText('A published service introduction.')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Find a technician for Laptop diagnosis guide' })).toHaveAttribute('href', '/technicians');
    expect(within(screen.getByRole('region', { name: 'Repair services' })).getByRole('link', { name: /Laptop repair/ })).toHaveAttribute('href', '/technicians?device_type=1');
  }, 15000);
  it.each(routes)('%s renders its intended page (%s, %s)', async (path, heading, role) => {
    await open(path, role);
    expect(await screen.findByRole('heading', {
      name: heading
    }, {
      timeout: 5000
    })).toBeInTheDocument();
  }, 15000);
  it('submits workspace search into the directory URL', async () => {
    const router = await open('/dashboard', 'technician');
    await screen.findByRole('heading', {
      name: 'Hello, Ana'
    });
    await fireEvent.update(screen.getByRole('searchbox', {
      name: 'Search technicians'
    }), 'Battery Laguna');
    await fireEvent.submit(screen.getByRole('search'));
    expect(await screen.findByRole('heading', {
      name: 'Find your repair technician'
    })).toBeInTheDocument();
    expect(router.currentRoute.value.query.search).toBe('Battery Laguna');
    expect(api.get).toHaveBeenCalledWith(expect.stringContaining('search=Battery+Laguna'), expect.anything());
  });
  it('retains URL lookup filters after loading and submitting the directory form', async () => {
    const router = await open('/technicians?device_type=1&repair_category=1&specialization=1', 'guest');
    await screen.findByRole('option', { name: 'Laptop' });
    const device = screen.getByRole('combobox', { name: 'Device type' });
    const category = screen.getByRole('combobox', { name: 'Repair problem' });
    const specialization = screen.getByRole('combobox', { name: 'Specialization' });
    await waitFor(() => {
      expect(device).toHaveValue('1');
      expect(category).toHaveValue('1');
      expect(specialization).toHaveValue('1');
    });
    await fireEvent.submit(device.closest('form')!);
    await waitFor(() => {
      expect(router.currentRoute.value.query).toMatchObject({
        device_type: '1', repair_category: '1', specialization: '1',
      });
    });
  });
  it('persists comparison choices in the URL across filters, reloads and clearing', async () => {
    const router = await open('/technicians?city=Santa+Cruz', 'guest');
    await fireEvent.click(await screen.findByRole('button', { name: /^Compare$/ }));
    await waitFor(() => expect(router.currentRoute.value.query.compare).toBe('1'));
    const city = screen.getByRole('textbox', { name: 'City / municipality' });
    await fireEvent.update(city, 'Pila');
    await fireEvent.submit(city.closest('form')!);
    await waitFor(() => expect(router.currentRoute.value.query).toMatchObject({ city: 'Pila', compare: '1' }));
    const savedUrl = router.currentRoute.value.fullPath;
    cleanup();
    const reloaded = await open(savedUrl, 'guest');
    expect(await screen.findByRole('button', { name: /^Selected$/ })).toHaveAttribute('aria-pressed', 'true');
    await fireEvent.click(screen.getByRole('button', { name: 'Clear' }));
    await waitFor(() => expect(reloaded.currentRoute.value.query.compare).toBeUndefined());
    expect(reloaded.currentRoute.value.query.city).toBe('Pila');
    expect(await screen.findByRole('button', { name: /^Compare$/ })).toHaveAttribute('aria-pressed', 'false');
  });
  it('shows public subscription comparison on the landing page while retaining technician search', async () => {
    await open('/', 'guest');
    const pricing = await screen.findByRole('region', { name: 'Technician subscription plans' });
    expect(await within(pricing).findByRole('heading', { name: 'Premium Technician' })).toBeInTheDocument();
    expect(within(pricing).getAllByRole('article')).toHaveLength(3);
    expect(screen.getByRole('link', { name: 'View technician plans' })).toHaveAttribute('href', '#technician-plans');
    expect(screen.getAllByRole('link', { name: /Find a technician/ }).length).toBeGreaterThan(0);
  });
  it('updates selected cards when navigating directory history without remounting', async () => {
    const router = await open('/technicians?compare=1', 'guest');
    await screen.findByRole('button', { name: /^Selected$/ });
    await router.push('/technicians?city=Pila');
    expect(await screen.findByRole('button', { name: /^Compare$/ })).toHaveAttribute('aria-pressed', 'false');
    router.back();
    await waitFor(() => expect(router.currentRoute.value.query.compare).toBe('1'));
    expect(await screen.findByRole('button', { name: /^Selected$/ })).toHaveAttribute('aria-pressed', 'true');
  });
  it('automatically refreshes technician results while map view is open', async () => {
    vi.useFakeTimers({ toFake: ['setInterval', 'clearInterval'] });
    try {
      await open('/technicians', 'guest');
      await screen.findByRole('button', { name: /^Compare$/ });
      const requests = () => vi.mocked(api.get).mock.calls.filter(([url]) => url.startsWith('/technicians?')).length;
      const initial = requests();
      await fireEvent.click(screen.getByRole('button', { name: 'Map view' }));
      await vi.advanceTimersByTimeAsync(30000);
      await waitFor(() => expect(requests()).toBe(initial + 1));
      await fireEvent.click(screen.getByRole('button', { name: 'List view' }));
      await vi.advanceTimersByTimeAsync(30000);
      expect(requests()).toBe(initial + 1);
    } finally {
      cleanup();
      vi.useRealTimers();
    }
  });
  it('restores search filters and selected profiles after comparison', async () => {
    const router = await open('/technicians?city=Santa+Cruz&device_type=1', 'guest');
    await fireEvent.click(await screen.findByRole('button', { name: /^Compare$/ }));
    await fireEvent.click(await screen.findByRole('link', { name: 'Compare profiles' }));
    await screen.findByRole('heading', { name: 'Compare technicians' });
    await fireEvent.click(screen.getByRole('link', { name: 'Back to search' }));
    await screen.findByRole('heading', { name: 'Find your repair technician' });
    expect(router.currentRoute.value.query).toMatchObject({ city: 'Santa Cruz', device_type: '1', compare: '1' });
    expect(await screen.findByRole('button', { name: /^Selected$/ })).toHaveAttribute('aria-pressed', 'true');
    expect(screen.getByRole('textbox', { name: 'City / municipality' })).toHaveValue('Santa Cruz');
    expect(vi.mocked(api.get).mock.calls.filter(([url]) => url.startsWith('/technicians?')).every(([url]) => !url.includes('compare='))).toBe(true);
  });
  it.each(['technician', 'customer'])('shows the other participant for a %s', async role => {
    await open('/dashboard/repairs', role);
    const table = await screen.findByRole('table');
    expect(within(table).getByRole('columnheader', {
      name: role === 'technician' ? 'Customer' : 'Technician'
    })).toBeInTheDocument();
    expect(within(table).getByText(role === 'technician' ? 'Ana Reyes' : 'Luis Santos')).toBeInTheDocument();
    const cards = screen.getByRole('list', { name: 'Repair request cards' });
    expect(within(cards).getByText(role === 'technician' ? 'Ana Reyes' : 'Luis Santos')).toBeInTheDocument();
    expect(within(cards).getByRole('link', { name: 'Open repair ER-ROUTE-001' })).toHaveAttribute('href', '/dashboard/repairs/1');
  });
});
