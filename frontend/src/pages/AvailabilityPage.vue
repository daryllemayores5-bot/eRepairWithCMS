<script setup lang="ts">
import { ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { api } from '@/services/api';
import { refreshTechnicianViews } from '@/services/technician-cache';
import { toast } from 'vue-sonner';
import { fieldClass } from '@/components/ui/fields';
import { errorMessage } from '@/lib/errors';
import type { Technician } from '@/types';
import Button from '@/components/ui/Button.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import { vInitialValue, vInitialChecked } from '@/lib/initial-fields';
const cache = useQueryClient(),
  busy = ref(false);
const profile = useApiQuery<Technician>(() => ({
  queryKey: ['my-technician-profile'],
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Technician;
  }>('/technician/profile', {
    signal
  })).data.data
}));
const FormData = globalThis.FormData;
const Promise = globalThis.Promise;
</script>

<template>
<Loading v-if="profile.isPending" />
<Failure v-else-if="profile.isError" :error="profile.error" :retry="() => profile.refetch()" />
<template v-else-if="profile.data">
<section class="max-w-2xl">
<PageHeading eyebrow="Your work preferences" title="Your availability" description="Choose whether you are ready to receive new repair requests.">
</PageHeading>
<p class="mt-4 text-sm leading-7 text-muted-foreground">Let customers know whether you are accepting new work. Existing repair appointments remain scheduled when you change this setting.</p>
<form :key="profile.data.availability" class="mt-7 space-y-6 surface-card p-7" @submit="async (event: Event) => {
  event.preventDefault();
  const availability = String(new FormData(event.currentTarget as HTMLFormElement).get('availability'));
  busy = true;
  try {
    await api.patch('/technician/availability', {
      availability
    });
    await refreshTechnicianViews(cache);
    toast.success('Availability updated');
  } catch (error) {
    toast.error(errorMessage(error));
  } finally {
    busy = false;
  }
}">
<div>
<span class="mr-3 text-sm text-muted-foreground">Current status</span>
<Status :value="profile.data.availability">
</Status>
</div>
<label class="block text-sm font-medium">Accepting new repair requests<select name="availability" :class="`${fieldClass} mt-2`" v-initial-value="profile.data.availability">
<option value="available">Available — accepting requests</option>
<option value="busy">Busy — not accepting new requests</option>
<option value="unavailable">Unavailable</option>
</select>
</label>
<p class="text-xs leading-6 text-muted-foreground">Administrator approval is still required for public search visibility.</p>
<Button type="submit" :disabled="busy">
<template v-if="busy">{{ 'Saving…' }}</template>
<template v-else>{{ 'Save availability' }}</template>
</Button>
</form>
</section>
</template>
</template>
