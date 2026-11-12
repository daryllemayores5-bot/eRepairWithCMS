import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query';
import { render, screen, fireEvent, waitFor, cleanup } from '@testing-library/vue';
import { defineComponent } from 'vue';
import { createPinia, disposePinia, type Pinia } from 'pinia';
import { createRouter, createMemoryHistory, RouterView } from 'vue-router';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import axios, { AxiosError, AxiosHeaders, type AxiosResponse, type InternalAxiosRequestConfig } from 'axios';
import { useAuthStore } from './auth';
import type { GlobalMountOptions } from '@vue/test-utils';
import { api } from '@/services/api';
import type { User } from '@/types';
import SiteLayout from '@/layouts/SiteLayout.vue';
import ProtectedRoute from '@/routes/ProtectedRoute.vue';
const ana: User = {
  id: 11,
  name: 'Ana',
  email: 'ana@example.test',
  role: 'customer'
};
const ben: User = {
  id: 12,
  name: 'Ben',
  email: 'ben@example.test',
  role: 'customer'
};
let serverUser: User | null;
let client: QueryClient;
const originalAdapter = api.defaults.adapter;
function reply(data: unknown, config: InternalAxiosRequestConfig = {
  headers: new AxiosHeaders()
}): AxiosResponse {
  return {
    data,
    config,
    status: 200,
    statusText: 'OK',
    headers: {}
  };
}
function unauthorized(config?: InternalAxiosRequestConfig) {
  return new AxiosError('Unauthenticated', 'ERR_BAD_REQUEST', config, undefined, {
    ...reply({
      message: 'Unauthenticated'
    }, config),
    status: 401,
    statusText: 'Unauthorized'
  });
}
const Probe = defineComponent({
  setup() {
    return {
      auth: useAuthStore(),
      ben
    };
  },
  template: `<p>{{auth.pending?'Checking session':auth.user?.name??'Guest'}}</p><button @click="auth.refresh()">Refresh session</button><button @click="auth.refresh(ben)">Accept Ben login</button><button @click="auth.logout()">Sign out</button>`
});
const stores: Pinia[] = [];
function plugins(): NonNullable<GlobalMountOptions['plugins']> {
  const pinia = createPinia();
  stores.push(pinia);
  return [pinia, [VueQueryPlugin, {
    queryClient: client
  }]];
}
beforeEach(() => {
  serverUser = ana;
  client = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        staleTime: Infinity
      }
    }
  });
  vi.spyOn(api, 'get').mockImplementation(async () => {
    if (!serverUser) throw unauthorized();
    return reply({
      data: serverUser
    });
  });
});
afterEach(() => {
  cleanup();
  stores.splice(0).forEach(disposePinia);
  client.clear();
  vi.restoreAllMocks();
  api.defaults.adapter = originalAdapter;
});
async function mount() {
  render(Probe, {
    global: {
      plugins: plugins()
    }
  });
  await screen.findByText('Ana');
  client.setQueryData(['dashboard'], {
    privateRepair: 'Ana private repair'
  });
  client.setQueryData(['website-content'], {
    publicTitle: 'eRepair'
  });
}
it('removes the previous account private cache when the authenticated identity changes', async () => {
  await mount();
  serverUser = ben;
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await screen.findByText('Ben');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
  expect(client.getQueryData(['website-content'])).toEqual({
    publicTitle: 'eRepair'
  });
});
it('clears private queries when a previously valid server session expires', async () => {
  await mount();
  serverUser = null;
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await screen.findByText('Guest');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('retains current account data on an ordinary same-user profile refresh', async () => {
  await mount();
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await waitFor(() => expect(api.get).toHaveBeenCalledTimes(2));
  expect(client.getQueryData(['dashboard'])).toEqual({
    privateRepair: 'Ana private repair'
  });
});
it('cancels previous-account requests so their late responses cannot refill private cache', async () => {
  await mount();
  let finish!: (data: string) => void;
  const pending = client.fetchQuery({
    queryKey: ['repair', 99],
    queryFn: () => new Promise<string>(resolve => {
      finish = resolve;
    })
  }).catch(() => undefined);
  serverUser = ben;
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await screen.findByText('Ben');
  finish('Ana private repair documentation');
  await pending;
  expect(client.getQueryData(['repair', 99])).toBeUndefined();
});
it('ends the local session immediately when a protected API request is unauthenticated', async () => {
  vi.restoreAllMocks();
  api.defaults.adapter = async config => {
    if (config.url === '/user') return reply({
      data: serverUser
    }, config);
    throw unauthorized(config);
  };
  await mount();
  await api.get('/repair-requests').catch(() => undefined);
  await screen.findByText('Guest');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('ignores an old account unauthorized response that arrives after switching identity', async () => {
  vi.restoreAllMocks();
  let rejectOld!: (error: AxiosError) => void;
  let oldConfig!: InternalAxiosRequestConfig;
  api.defaults.adapter = async config => {
    if (config.url === '/user') return reply({
      data: serverUser
    }, config);
    oldConfig = config;
    return new Promise((_resolve, reject) => {
      rejectOld = reject;
    });
  };
  await mount();
  const old = api.get('/repair-requests').catch(() => undefined);
  await waitFor(() => expect(rejectOld).toBeDefined());
  serverUser = ben;
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await screen.findByText('Ben');
  rejectOld(unauthorized(oldConfig));
  await old;
  expect(screen.getByText('Ben')).toBeInTheDocument();
});
it('allows an already expired session to finish signing out and discard private data', async () => {
  await mount();
  vi.spyOn(axios, 'get').mockResolvedValue(reply(null));
  vi.spyOn(api, 'post').mockRejectedValue(unauthorized());
  fireEvent.click(screen.getByRole('button', {
    name: 'Sign out'
  }));
  await screen.findByText('Guest');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('accepts the successful login identity after canceling an older auth request', async () => {
  await mount();
  let finish!: (value: AxiosResponse) => void;
  vi.mocked(api.get).mockImplementationOnce(() => new Promise(resolve => {
    finish = resolve;
  }));
  fireEvent.click(screen.getByRole('button', {
    name: 'Refresh session'
  }));
  await waitFor(() => expect(finish).toBeDefined());
  fireEvent.click(screen.getByRole('button', {
    name: 'Accept Ben login'
  }));
  await screen.findByText('Ben');
  finish(reply({
    data: ana
  }));
  await waitFor(() => expect(client.getQueryData<User>(['auth-user'])?.id).toBe(ben.id));
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('keeps the session on an ordinary forbidden record but clears it for an inactive account', async () => {
  vi.restoreAllMocks();
  let inactive = false;
  api.defaults.adapter = async config => {
    if (config.url === '/user') return reply({
      data: serverUser
    }, config);
    throw new AxiosError('Forbidden', 'ERR_BAD_REQUEST', config, undefined, {
      ...reply({
        message: 'Forbidden',
        ...(inactive ? {
          code: 'account_inactive'
        } : {})
      }, config),
      status: 403
    });
  };
  await mount();
  await api.get('/repair-requests/99').catch(() => undefined);
  expect(screen.getByText('Ana')).toBeInTheDocument();
  inactive = true;
  await api.get('/repair-requests').catch(() => undefined);
  await screen.findByText('Guest');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('rechecks identity after a CSRF failure instead of retaining an expired private workspace', async () => {
  vi.restoreAllMocks();
  api.defaults.adapter = async config => {
    if (config.url === '/user') {
      if (!serverUser) throw unauthorized(config);
      return reply({
        data: serverUser
      }, config);
    }
    serverUser = null;
    throw new AxiosError('Page expired', 'ERR_BAD_REQUEST', config, undefined, {
      ...reply({
        message: 'CSRF token mismatch'
      }, config),
      status: 419
    });
  };
  await mount();
  await api.post('/repair-requests').catch(() => undefined);
  await screen.findByText('Guest');
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
it('finishes explicit sign-out on the public home instead of a competing guest redirect', async () => {
  vi.spyOn(axios, 'get').mockResolvedValue(reply(null));
  vi.spyOn(api, 'post').mockImplementation(async () => {
    serverUser = null;
    return reply(null);
  });
  const router = createRouter({
    history: createMemoryHistory(),
    routes: [{
      path: '/',
      component: SiteLayout,
      children: [{
        path: '',
        component: {
          template: '<h1>Public repair finder</h1>'
        }
      }, {
        path: 'login',
        component: {
          template: '<h1>Login page</h1>'
        }
      }, {
        path: 'dashboard',
        component: ProtectedRoute,
        children: [{
          path: '',
          component: {
            template: '<h1>Private workspace</h1>'
          }
        }]
      }]
    }]
  });
  await router.push('/dashboard');
  await router.isReady();
  render(defineComponent({
    components: {
      RouterView
    },
    template: '<RouterView />'
  }), {
    global: {
      plugins: [...plugins(), router]
    }
  });
  await screen.findByRole('heading', {
    name: 'Private workspace'
  });
  client.setQueryData(['dashboard'], {
    privateRepair: 'Ana repair'
  });
  await fireEvent.click(screen.getByRole('button', {
    name: 'Sign out'
  }));
  await screen.findByRole('heading', {
    name: 'Public repair finder'
  });
  expect(screen.queryByRole('heading', {
    name: 'Login page'
  })).not.toBeInTheDocument();
  expect(client.getQueryData(['dashboard'])).toBeUndefined();
});
