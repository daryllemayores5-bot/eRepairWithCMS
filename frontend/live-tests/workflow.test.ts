import { readFileSync } from 'node:fs';
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { cleanup, configure, fireEvent, render, screen, waitFor, within } from '@testing-library/vue';
import { createMemoryHistory } from 'vue-router';
import { createPinia, disposePinia, type Pinia } from 'pinia';
import { expect, it } from 'vitest';
import App from '@/App.vue';
import { createAppRouter } from '@/router';
import { api, initializeCsrf } from '@/services/api';
import type { Appointment, Lookups, Page, Repair, Technician } from '@/types';
// Explicitly opt-in, real Vue -> XHR/cookies/CSRF -> Laravel -> demo PostgreSQL.
// No API, authentication or query mocks. This is a DOM test, not a browser/PWA test.
configure({
  getElementError: message => new Error(message?.split('\n')[0] ?? 'Live UI interaction failed.')
});
const localOrigin = window.location.origin;
const clients: QueryClient[] = [];
const stores: Pinia[] = [];
async function open(path: string) {
  cleanup();
  stores.splice(0).forEach(disposePinia);
  for (const client of clients) client.clear();
  const client = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false
        },
        mutations: {
          retry: false
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
}
async function change(element: Element, options: {
  target: {
    value?: string;
    files?: File[];
  };
}) {
  if (options.target.files) {
    Object.defineProperty(element, 'files', {
      value: options.target.files,
      configurable: true
    });
    await fireEvent.change(element);
  } else await fireEvent.update(element, options.target.value ?? '');
}
async function signIn(password: string) {
  await screen.findByRole('heading', {
    name: 'Sign in to eRepair'
  }, {
    timeout: 15000
  });
  await change(screen.getByLabelText('Password'), {
    target: {
      value: password
    }
  });
  await fireEvent.click(screen.getByRole('button', {
    name: 'Sign in'
  }));
  await screen.findByRole('button', {
    name: 'Sign out'
  }, {
    timeout: 15000
  });
}
async function signOut() {
  await fireEvent.click(screen.getByRole('button', {
    name: 'Sign out'
  }));
  try {
    await screen.findByRole('heading', {
      name: 'Find the Right Repair Technician for Your Device'
    }, {
      timeout: 15000
    });
  } catch {
    throw new Error(`Sign-out navigation failed; signed-in UI=${!!screen.queryByRole('button', {
      name: 'Sign out'
    })}; login page=${!!screen.queryByRole('heading', {
      name: 'Sign in to eRepair'
    })}`);
  }
}
async function clickAction(name: string) {
  const button = await screen.findByRole('button', {
    name
  }, {
    timeout: 15000
  });
  await waitFor(() => expect(button).toBeEnabled(), {
    timeout: 15000
  });
  await fireEvent.click(button);
}
it('completes the real discovery, photo request, technician, estimate approval, repair and review journey', async () => {
  expect(new URL(api.defaults.baseURL ?? '/api/v1', window.location.href).origin).toBe(localOrigin);
  expect(['127.0.0.1', 'localhost']).toContain(new URL(localOrigin).hostname);
  // Verify the expected Laravel/PostgreSQL service before reading demo credentials.
  const health = await api.get('/health');
  expect(health.data.data).toMatchObject({ application: 'eRepair', status: 'ok', database: 'connected' });
  const password = readFileSync('../.local/demo-password', 'utf8').trim();
  const marker = `DEMO live Vue test ${Date.now()}`;
  let repairId: number | undefined;
  let completed = false;
  try {
    // Retrieve the actual seeded technician profile without inventing UI records.
    await initializeCsrf();
    try {
      await api.post('/login', {
        email: 'technician1@erepair.test',
        password
      });
    } catch {
      throw new Error('Local demonstration technician sign-in failed.');
    }
    const technician = (await api.get<{
      data: Technician;
    }>('/technician/profile')).data.data;
    console.info('[live workflow] Technician authentication and profile loaded');
    const lookups = (await api.get<{
      data: Lookups;
    }>('/lookups')).data.data;
    // Choose a slot beyond existing actual bookings, so a failed/retained demo
    // run never creates a false conflict during the next acceptance exercise.
    let appointmentStart = Date.now() + 65 * 86400000;
    for (let page = 1, lastPage = 1; page <= lastPage; page++) {
      const appointments = (await api.get<Page<Appointment>>(`/appointments?page=${page}`)).data;
      lastPage = appointments.meta?.last_page ?? appointments.last_page ?? 1;
      for (const appointment of appointments.data) {
        const end = Date.parse(appointment.ends_at);
        if (Number.isFinite(end)) appointmentStart = Math.max(appointmentStart, end + 86400000);
      }
    }
    await api.post('/logout');
    const device = technician.device_types[0];
    const category = lookups.repair_categories.find(category => (!category.device_type_id || category.device_type_id === device.id) && (!category.specialization_id || technician.specializations.some(s => s.id === category.specialization_id)));
    expect(category).toBeDefined();
    await open('/technicians');
    await screen.findByRole('option', {
      name: device.name
    }, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Name or repair problem'), {
      target: {
        value: technician.name
      }
    });
    await change(screen.getByLabelText('City / municipality'), {
      target: {
        value: technician.city
      }
    });
    await change(screen.getByLabelText('Device type'), {
      target: {
        value: String(device.id)
      }
    });
    await fireEvent.click(screen.getByRole('button', {
      name: 'Apply filters'
    }));
    const heading = await screen.findByRole('heading', {
      name: new RegExp(technician.name)
    }, {
      timeout: 15000
    });
    await fireEvent.click(within(heading.closest('article')!).getByRole('link', {
      name: 'View profile'
    }));
    await fireEvent.click(await screen.findByRole('link', {
      name: 'Request repair'
    }, {
      timeout: 15000
    }));
    await screen.findByLabelText('Email', {}, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Email'), {
      target: {
        value: 'customer1@erepair.test'
      }
    });
    await signIn(password);
    await change(await screen.findByLabelText('Device type', {}, {
      timeout: 15000
    }), {
      target: {
        value: String(device.id)
      }
    });
    await change(screen.getByLabelText('Brand'), {
      target: {
        value: String(lookups.device_brands[0].id)
      }
    });
    await change(screen.getByLabelText('Model'), {
      target: {
        value: marker
      }
    });
    await fireEvent.click(screen.getByRole('button', {
      name: 'Continue'
    }));
    await change(await screen.findByLabelText('Problem category'), {
      target: {
        value: String(category!.id)
      }
    });
    await change(screen.getByLabelText('Problem description'), {
      target: {
        value: 'DEMO live UI test: please diagnose this device and provide an estimate before repairs.'
      }
    });
    const png = Uint8Array.from(Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jG9cAAAAASUVORK5CYII=', 'base64'));
    await change(screen.getByLabelText('Device photos (up to five, 5 MB each)'), {
      target: {
        files: [new File([png], 'demo-live-ui.png', {
          type: 'image/png'
        })]
      }
    });
    await fireEvent.click(screen.getByRole('button', {
      name: 'Continue'
    }));
    await screen.findByRole('heading', {
      name: 'Choose the right technician'
    });
    await screen.findByRole('radio', {
      name: new RegExp(technician.name)
    }, {
      timeout: 15000
    });
    await fireEvent.click(screen.getByRole('button', {
      name: 'Continue'
    }));
    const start = new Date(appointmentStart);
    const localTime = new Date(start.getTime() + 8 * 3600000).toISOString().slice(0, 16);
    await change(await screen.findByLabelText('Preferred date and time (Philippine time)'), {
      target: {
        value: localTime
      }
    });
    await change(screen.getByLabelText('Preferred service location / meeting point'), {
      target: {
        value: 'DEMO live UI meeting point, Santa Cruz town center'
      }
    });
    await fireEvent.click(screen.getByRole('button', {
      name: 'Continue'
    }));
    await fireEvent.click(await screen.findByRole('button', {
      name: 'Submit repair request'
    }));
    await screen.findByRole('heading', {
      name: new RegExp(marker)
    }, {
      timeout: 15000
    });
    const list = (await api.get<{
      data: Repair[];
    }>('/repair-requests')).data.data;
    const created = list.find(repair => repair.model === marker)!;
    expect(created).toBeDefined();
    repairId = created.id;
    const detail = (await api.get<{
      data: Repair;
    }>(`/repair-requests/${repairId}`)).data.data;
    console.info('[live workflow] Customer repair request and photo persisted');
    expect(detail.attachments).toHaveLength(1);
    expect(detail.technician.id).toBe(technician.id);
    await signOut();
    await open(`/dashboard/repairs/${repairId}`);
    await screen.findByLabelText('Email', {}, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Email'), {
      target: {
        value: 'technician1@erepair.test'
      }
    });
    await signIn(password);
    await clickAction('Accept request');
    await screen.findByRole('button', {
      name: 'Mark scheduled'
    }, {
      timeout: 15000
    });
    await fireEvent.click(screen.getByText('Confirm or reschedule appointment'));
    await clickAction('Confirm appointment');
    await screen.findByText('Confirmed', {
      selector: 'span'
    }, {
      timeout: 15000
    });
    await clickAction('Mark scheduled');
    await clickAction('Mark diagnosing');
    await change(await screen.findByLabelText('Estimate amount (PHP)', {}, {
      timeout: 15000
    }), {
      target: {
        value: '1500.50'
      }
    });
    await change(screen.getByLabelText('Scope and parts'), {
      target: {
        value: 'DEMO live UI estimate: diagnosis, replacement parts and labor.'
      }
    });
    await clickAction('Send estimate for approval');
    await screen.findByText('Awaiting customer approval', {
      selector: 'span'
    }, {
      timeout: 15000
    });
    console.info('[live workflow] Technician acceptance, appointment and estimate persisted');
    await signOut();
    await open(`/dashboard/repairs/${repairId}`);
    await screen.findByLabelText('Email', {}, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Email'), {
      target: {
        value: 'customer1@erepair.test'
      }
    });
    await signIn(password);
    await clickAction('Approve estimate');
    await screen.findByText('In repair', {
      selector: 'span'
    }, {
      timeout: 15000
    });
    await signOut();
    await open(`/dashboard/repairs/${repairId}`);
    await screen.findByLabelText('Email', {}, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Email'), {
      target: {
        value: 'technician1@erepair.test'
      }
    });
    await signIn(password);
    await clickAction('Mark ready for pickup');
    await screen.findByText('Ready for pickup', {
      selector: 'span'
    }, {
      timeout: 15000
    });
    console.info('[live workflow] Approved repair ready for pickup');
    await signOut();
    await open(`/dashboard/repairs/${repairId}`);
    await screen.findByLabelText('Email', {}, {
      timeout: 15000
    });
    await change(screen.getByLabelText('Email'), {
      target: {
        value: 'customer1@erepair.test'
      }
    });
    await signIn(password);
    await clickAction('Confirm pickup & completion');
    await screen.findByRole('button', {
      name: 'Submit review'
    }, {
      timeout: 15000
    });
    completed = true;
    const review = 'DEMO live UI review: clear progress and estimate approval.';
    await change(screen.getByLabelText('Your experience'), {
      target: {
        value: review
      }
    });
    await clickAction('Submit review');
    await screen.findByText(new RegExp(review), {}, {
      timeout: 15000
    });
    const finished = (await api.get<{
      data: Repair;
    }>(`/repair-requests/${repairId}`)).data.data;
    expect(finished.status).toBe('completed');
    expect(finished.review?.rating).toBe(5);
    expect(finished.history?.map(history => history.new_status)).toEqual(['pending', 'accepted', 'scheduled', 'diagnosing', 'awaiting_customer_approval', 'in_repair', 'ready_for_pickup', 'completed']);
    expect(finished.appointments?.[0].status).toBe('completed');
    console.info('[live workflow] Completion, review, history and appointment verified');
    await signOut();
    console.info('[live workflow] Final logout verified');
  } catch (error) {
    // Do not serialize Axios request configuration, cookies or demo credentials.
    throw new Error(error instanceof Error ? error.message.split('\n')[0] : 'Live UI test failed.');
  } finally {
    cleanup();
    stores.splice(0).forEach(disposePinia);
    for (const client of clients) client.clear();
    // Cleanup runs outside the unmounted UI session, explicitly authenticates,
    // and only cancels this run's own uniquely labeled, cancellable demo repair.
    if (!completed) {
      try {
        await initializeCsrf();
        await api.post('/login', {
          email: 'customer1@erepair.test',
          password
        });
        if (!repairId) repairId = (await api.get<{
          data: Repair[];
        }>('/repair-requests')).data.data.find(repair => repair.model === marker)?.id;
        if (repairId) await api.post(`/repair-requests/${repairId}/cancel`).catch(() => {});
      } catch {/* Preserve labeled history when the server is unavailable. */}
    }
    await api.post('/logout').catch(() => {});
  }
});
