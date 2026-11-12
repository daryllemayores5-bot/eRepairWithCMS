<script setup lang="ts">
import { computed, watchEffect } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const backendOrigin = (import.meta.env.VITE_BACKEND_PUBLIC_URL || (import.meta.env.DEV ? 'http://127.0.0.1:8000' : '')).replace(/\/$/, '');
const sessionError = new Error('Cannot verify your session. Check the server connection.');
const identityKey = computed(() => auth.user?.id);
watchEffect(() => {
  if (!auth.pending && !auth.error && !auth.user) {
    void router.replace({
      path: '/login',
      state: {
        from: route.fullPath
      }
    });
  }
});
</script>

<template>
<div v-if="auth.pending" class="mx-auto max-w-3xl p-6">
<Loading />
</div>
  <div v-else-if="auth.error" class="mx-auto max-w-3xl p-6">
<Failure :error="sessionError" :retry="() => auth.refresh()" />
</div>
  <div v-else-if="auth.user?.role === 'admin'" class="p-12 text-center">
    <h1 class="text-2xl font-bold">Administrator account</h1>
    <p class="mt-4">Administration is available in the Statamic Control Panel.</p>
    <a class="mt-5 inline-block text-primary underline" :href="`${backendOrigin}/cp`">Open Statamic</a>
  </div>
  <RouterView v-else-if="auth.user" :key="identityKey" />
</template>
