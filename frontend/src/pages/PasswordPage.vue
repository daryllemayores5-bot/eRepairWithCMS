<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink as Link, useRoute } from 'vue-router';
import { api, initializeCsrf } from '@/services/api';
import { errorMessage } from '@/lib/errors';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
const props = withDefaults(defineProps<{
  reset?: boolean;
}>(), {
  reset: false
});
const reset = computed(() => props.reset);
const route = useRoute();
const params = computed(() => new URLSearchParams(route.fullPath.split('?')[1]?.split('#')[0] ?? ''));
const pending = ref(false),
  message = ref(''),
  failure = ref('');
const FormData = globalThis.FormData;
</script>

<template>
<section class="mx-auto max-w-lg px-5 py-12 sm:py-16">
<div class="surface-card p-6 sm:p-9">
<p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-primary">Account recovery</p>
<h1 class="text-2xl font-bold tracking-tight text-navy">
<template v-if="reset">{{ 'Choose a new password' }}</template>
<template v-else>{{ 'Reset your password' }}</template>
</h1>
<form class="mt-8 space-y-5" @submit="async (e: Event) => {
  e.preventDefault();
  if (pending) return;
  const data = Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement));
  pending = true;
  failure = '';
  message = '';
  try {
    await initializeCsrf();
    const response = await api.post(reset ? '/reset-password' : '/forgot-password', {
      ...data,
      token: params.get('token')
    });
    message = response.data.message;
  } catch (error) {
    failure = errorMessage(error);
  } finally {
    pending = false;
  }
}">
<label class="block text-sm">Email<Input class="mt-2" name="email" type="email" :default-value="params.get('email') ?? ''" required>
</Input>
</label>
<template v-if="reset">
<label class="block text-sm">New password<Input class="mt-2" name="password" type="password" :minLength="10" required autoComplete="new-password">
</Input>
</label>
<label class="block text-sm">Confirm password<Input class="mt-2" name="password_confirmation" type="password" :minLength="10" required autoComplete="new-password">
</Input>
</label>
</template>
<template v-if="failure">
<p role="alert" class="text-sm text-red-700">{{ failure }}</p>
</template>
<template v-if="message">
<p role="status" class="text-sm text-green-800">{{ message }}</p>
</template>
<Button :disabled="pending" type="submit">
<template v-if="pending">{{ 'Please wait…' }}</template>
<template v-else>
<template v-if="reset">{{ 'Update password' }}</template>
<template v-else>{{ 'Send reset link' }}</template>
</template>
</Button>
<Link class="ml-5 text-sm text-primary" to="/login">Back to sign in</Link>
</form>
</div>
</section>
</template>
