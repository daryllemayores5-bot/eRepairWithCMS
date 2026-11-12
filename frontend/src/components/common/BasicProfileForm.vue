<script setup lang="ts">
import { ref } from 'vue';
import { useAuthStore as useAuth } from '@/stores/auth';
import { api } from '@/services/api';
import { toast } from 'vue-sonner';
import { errorMessage } from '@/lib/errors';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
const auth = useAuth(),
  busy = ref(false);
const FormData = globalThis.FormData;
</script>

<template>
<form class="max-w-xl space-y-5 surface-card p-6" @submit="async (e: Event) => {
  e.preventDefault();
  busy = true;
  const name = String(new FormData(e.currentTarget as HTMLFormElement).get('name'));
  try {
    await api.patch('/profile', {
      name
    });
    await auth.refresh();
    toast.success('Profile saved');
  } catch (error) {
    toast.error(errorMessage(error));
  } finally {
    busy = false;
  }
}">
<label class="block text-sm">Full name<Input name="name" class="mt-2" :default-value="auth.user?.name" required>
</Input>
</label>
<label class="block text-sm">Email<Input class="mt-2" :value="auth.user?.email ?? ''" readonly>
</Input>
</label>
<Button :disabled="busy" type="submit">
<template v-if="busy">{{ 'Saving…' }}</template>
<template v-else>{{ 'Save profile' }}</template>
</Button>
</form>
</template>
