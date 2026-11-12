<script setup lang="ts">
import { computed, ref } from 'vue';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { useAuthStore as useAuth } from '@/stores/auth';
import { useLookups } from '@/composables/content';
import { api } from '@/services/api';
import { refreshTechnicianViews } from '@/services/technician-cache';
import { toast } from 'vue-sonner';
import { fieldClass } from '@/components/ui/fields';
import { errorMessage } from '@/lib/errors';
import type { Technician, Repair } from '@/types';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import BasicProfileForm from '@/components/common/BasicProfileForm.vue';
import { vInitialValue, vInitialChecked } from '@/lib/initial-fields';
const auth = useAuth(),
  cache = useQueryClient(),
  lookups = useLookups(),
  busy = ref(false);
const tech = computed(() => auth.user?.role === 'technician');
const profile = useApiQuery<Technician>(() => ({
  queryKey: ['my-technician-profile'],
  enabled: tech.value,
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Technician;
  }>('/technician/profile', {
    signal
  })).data.data
}));
const FormData = globalThis.FormData;
</script>

<template>
<div class="mb-7">
<PageHeading eyebrow="Your account" title="Profile settings" description="Keep your account details and professional information up to date.">
</PageHeading>
</div>
<BasicProfileForm>
</BasicProfileForm>
<template v-if="tech">
<div class="mt-8">
<template v-if="profile.isPending || lookups.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="profile.isError">
<Failure :error="profile.error" :retry="() => profile.refetch()">
</Failure>
</template>
<template v-else>
<template v-if="lookups.isError">
<Failure :error="lookups.error" :retry="() => lookups.refetch()">
</Failure>
</template>
<template v-else>
<div class="mb-6 rounded-xl border border-blue-200 bg-blue-50 p-5 text-sm">
<Status :value="profile.data.verification_status">
</Status>
<p class="mt-3">Your profile appears in the public directory after administrator approval.</p>
</div>
<form :key="profile.data.id" class="space-y-5 surface-card p-6" @submit="async (e: Event) => {
  e.preventDefault();
  busy = true;
  const d = new FormData(e.currentTarget as HTMLFormElement);
  const body = {
    biography: d.get('biography'),
    city: d.get('city'),
    province: d.get('province'),
    years_experience: Number(d.get('years_experience')),
    availability: d.get('availability'),
    specialization_ids: d.getAll('specializations').map(Number),
    device_type_ids: d.getAll('device_types').map(Number),
    service_areas: String(d.get('service_areas')).split('\n').map(l => l.trim()).filter(Boolean).map(line => {
      const [city, province] = line.split(',').map(s => s.trim());
      return {
        city,
        province: province || String(d.get('province'))
      };
    })
  };
  try {
    await api.patch('/technician/profile', body);
    await refreshTechnicianViews(cache);
    toast.success('Professional profile saved');
  } catch (e) {
    toast.error(errorMessage(e));
  } finally {
    busy = false;
  }
}">
<h2 class="section-title">Professional profile & availability</h2>
<label class="block text-sm">Biography<textarea :class="`${fieldClass} mt-2`" name="biography" :rows="5" v-initial-value="profile.data.biography ?? ''" :minLength="30" required>
</textarea>
</label>
<div class="grid gap-5 sm:grid-cols-2">
<label class="block text-sm">Years of experience<Input class="mt-2" name="years_experience" type="number" min="0" max="60" :default-value="profile.data.years_experience" required>
</Input>
</label>
<label class="block text-sm">Availability<select :class="`${fieldClass} mt-2`" name="availability" v-initial-value="profile.data.availability">
<option value="available">Accepting requests</option>
<option value="busy">Busy</option>
<option value="unavailable">Unavailable</option>
</select>
</label>
<label class="block text-sm">City / municipality<Input class="mt-2" name="city" :default-value="profile.data.city" required>
</Input>
</label>
<label class="block text-sm">Province<Input class="mt-2" name="province" :default-value="profile.data.province" required>
</Input>
</label>
</div>
<fieldset>
<legend class="text-sm font-medium">Supported devices</legend>
<div class="mt-3 flex flex-wrap gap-4">
<template v-for="(d) in lookups.data.device_types" :key="d.id">
<label class="flex items-center gap-2 text-sm">
<input type="checkbox" name="device_types" :value="d.id" v-initial-checked="profile.data.device_types.some(t => t.id === d.id)">
</input>{{ d.name }}</label>
</template>
</div>
</fieldset>
<fieldset>
<legend class="text-sm font-medium">Repair specializations</legend>
<div class="mt-3 grid gap-3 sm:grid-cols-2">
<template v-for="(s) in lookups.data.specializations" :key="s.id">
<label class="flex items-center gap-2 text-sm">
<input type="checkbox" name="specializations" :value="s.id" v-initial-checked="profile.data.specializations.some(t => t.id === s.id)">
</input>{{ s.name }}</label>
</template>
</div>
</fieldset>
<label class="block text-sm">Additional service areas (one city, province per line)<textarea :class="`${fieldClass} mt-2`" name="service_areas" :rows="3" v-initial-value="profile.data.service_areas.map(a => `${a.city}, ${a.province}`).join('\n')">
</textarea>
</label>
<p class="text-xs text-muted-foreground">Only city-level service locations are public. Do not put your residential address in your biography.</p>
<Button type="submit" :disabled="busy">
<template v-if="busy">{{ 'Saving…' }}</template>
<template v-else>{{ 'Save professional profile' }}</template>
</Button>
</form>
<form class="mt-6 space-y-4 surface-card p-6" @submit="async (e: Event) => {
  e.preventDefault();
  const body = new FormData(e.currentTarget as HTMLFormElement);
  busy = true;
  try {
    await api.post('/technician/photo', body);
    await refreshTechnicianViews(cache);
    toast.success('Photo uploaded');
  } catch (error) {
    toast.error(errorMessage(error));
  } finally {
    busy = false;
  }
}">
<template v-if="profile.data.photo_url">
<img :key="profile.dataUpdatedAt" :src="profile.data.photo_url" alt="Your current technician profile photo" :width="96" :height="96" class="h-24 w-24 rounded-xl object-cover">
</img>
</template>
<label class="block text-sm">Public profile photo (JPG, PNG or WebP; 2 MB maximum)<Input class="mt-2 h-auto py-3" name="photo" type="file" accept="image/jpeg,image/png,image/webp" required>
</Input>
</label>
<Button type="submit" :disabled="busy">Upload profile photo</Button>
</form>
</template>
</template>
</template>
</div>
</template>
</template>
