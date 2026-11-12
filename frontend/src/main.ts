import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { VueQueryPlugin, QueryClient } from '@tanstack/vue-query';
import 'vue-sonner/style.css';
import App from './App.vue';
import './index.css';
import './ui.css';
import './redesign.css';
import { createAppRouter } from './router';
import { retireLegacyWorker } from './lib/retire-legacy-worker';
const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      retry: 1,
      staleTime: 30000,
      refetchOnWindowFocus: false
    }
  }
});
if (import.meta.env.PROD) void retireLegacyWorker();
const app = createApp(App);
app.use(createPinia());
app.use(VueQueryPlugin, {
  queryClient
});
app.use(createAppRouter());
app.mount('#root');
