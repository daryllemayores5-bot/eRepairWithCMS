<script setup lang="ts">
import { computed, ref } from 'vue';
import { RouterLink as Link, useRouter } from 'vue-router';
import { useAuthStore as useAuth } from '@/stores/auth';
import { api, initializeCsrf } from '@/services/api';
import { toast } from 'vue-sonner';
import { errorMessage } from '@/lib/errors';
import type { User } from '@/types';
import { BadgeCheck, Check, Wrench } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import { createAuthSchema } from '@/lib/form-schemas';
import { useValidatedForm } from '@/composables/form';
const props = defineProps<{
  mode: 'login' | 'customer' | 'technician';
}>();
const mode = props.mode,
  login = mode === 'login',
  auth = useAuth(),
  router = useRouter(),
  failure = ref('');
const schema = createAuthSchema(mode);
const form = useValidatedForm(schema, {
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  city: '',
  province: ''
});
const fields = computed(() => (!login ? ['name', 'email', 'password', 'password_confirmation', ...(mode === 'technician' ? ['city', 'province'] : [])] : ['email', 'password']).map(key => ({
  key,
  name: key as keyof typeof form.values,
  label: key.replaceAll('_', ' ').replace(/^./, c => c.toUpperCase()),
  error: form.formState.errors[key]
})));
const submit = form.handleSubmit(async values => {
  failure.value = '';
  try {
    await initializeCsrf();
    const response = await api.post<{
      data: User;
    }>(login ? '/login' : '/register', login ? {
      email: values.email,
      password: values.password
    } : {
      ...values,
      role: mode === 'technician' ? 'technician' : 'customer'
    });
    await auth.refresh(response.data.data);
    toast.success(login ? 'Welcome back' : 'Account created');
    const from = router.options.history.state.from;
    await router.replace(typeof from === 'string' && from.startsWith('/dashboard') ? from : '/dashboard');
  } catch (error) {
    failure.value = errorMessage(error);
  }
});
</script>

<template>
<section class="mx-auto grid max-w-6xl items-start gap-10 px-5 py-10 sm:px-8 sm:py-16 lg:grid-cols-[1fr_1fr] lg:gap-20">
<aside class="auth-story hidden lg:block">
<span class="flex size-14 items-center justify-center rounded-2xl bg-blue-50 text-primary">
<Wrench class="size-6" aria-hidden="true">
</Wrench>
</span>
<h2 class="mt-8 max-w-sm text-4xl font-bold leading-tight tracking-tight text-slate-950">Good repairs start with the right connection.</h2>
<p class="mt-5 max-w-sm text-base leading-8 text-slate-500">A simpler way to discover local repair expertise and manage your repair from start to finish.</p>
<ul class="mt-8 space-y-5">
<template v-for="text in ['Find specialists for your device','Choose from approved professionals','Keep requests and updates in one place']" :key="text">
<li class="flex items-center gap-3 text-sm text-slate-600">
<Check class="size-4 text-primary" aria-hidden="true">
</Check>{{ text }}</li>
</template>
</ul>
<p class="mt-12 flex items-center gap-2 border-t pt-6 text-xs text-slate-500">
<BadgeCheck class="size-4 text-primary" aria-hidden="true">
</BadgeCheck>Built around a clear, connected repair journey.</p>
</aside>
<div class="surface-card mx-auto w-full max-w-lg p-6 sm:p-9">
<p class="text-sm font-semibold text-primary">
<template v-if="login">{{ 'Welcome back' }}</template>
<template v-else>
<template v-if="mode === 'technician'">{{ 'Share your repair expertise' }}</template>
<template v-else>{{ 'Find trusted local expertise' }}</template>
</template>
</p>
<h1 class="mt-3 text-3xl font-bold tracking-tight text-navy">
<template v-if="login">{{ 'Sign in to eRepair' }}</template>
<template v-else>
<template v-if="mode === 'technician'">{{ 'Join as a technician' }}</template>
<template v-else>{{ 'Create your account' }}</template>
</template>
</h1>
<p class="mt-4 text-sm leading-7 text-muted-foreground">
<template v-if="mode === 'technician'">{{ 'Complete your professional profile after registration. Administrator approval is required before you appear in search.' }}</template>
<template v-else>{{ 'Connect with the right professional and keep your repair journey in one place.' }}</template>
</p>
<form class="mt-7 space-y-5" @submit="submit" noValidate>
<template v-for="({key,name,label,error}) in fields" :key="key">
<div class="text-sm font-medium"><label :for="`auth-${key}`">{{ label }}</label><Input :id="`auth-${key}`" class="mt-2" :type="key.includes('password') ? 'password' : key === 'email' ? 'email' : 'text'" :autoComplete="key === 'password' ? login ? 'current-password' : 'new-password' : key === 'password_confirmation' ? 'new-password' : key === 'email' ? 'email' : undefined" v-model="form.values[name]" :name="name" :aria-invalid="!!error" :aria-describedby="error ? `${key}-error` : undefined">
</Input>
<template v-if="error">
<span :id="`${key}-error`" class="mt-2 block text-xs text-red-700">{{ error.message }}</span>
</template>
</div>
</template>
<template v-if="failure">
<p role="alert" class="rounded-lg bg-red-50 p-3 text-sm leading-6 text-red-800">{{ failure }}</p>
</template>
<Button type="submit" class="w-full" :disabled="form.formState.isSubmitting">
<template v-if="form.formState.isSubmitting">{{ 'Please wait…' }}</template>
<template v-else>
<template v-if="login">{{ 'Sign in' }}</template>
<template v-else>{{ 'Create account' }}</template>
</template>
</Button>
</form>
<template v-if="login">
<Link class="mt-5 block text-sm text-primary" to="/forgot-password">Forgot your password?</Link>
<p class="mt-6 text-sm text-muted-foreground">New to eRepair? <Link class="text-primary" to="/register">Create an account</Link>
</p>
</template>
<template v-else>
<p class="mt-6 text-sm text-muted-foreground">Already registered? <Link class="text-primary" to="/login">Sign in</Link>
</p>
</template>
<template v-if="mode === 'customer'">
<Link to="/register/technician" class="mt-5 block text-sm text-primary">Are you a repair professional? Join as a technician →</Link>
</template>
</div>
</section>
</template>
