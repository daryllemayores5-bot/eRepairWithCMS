<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { useAuthStore as useAuth } from '@/stores/auth';
import { useLookups } from '@/composables/content';
import { api } from '@/services/api';
import { fieldClass } from '@/components/ui/fields';
import { errorMessage } from '@/lib/errors';
import { canRequestRepair, devicePhotoError, proposedAppointment } from '@/lib/repair-request';
import type { Technician, Repair, Page, Review, Appointment } from '@/types';
import { Search } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Empty from '@/components/common/Empty.vue';
import Pagination from '@/components/common/Pagination.vue';
import PageHeading from '@/components/common/PageHeading.vue';
import FormField from '@/components/ui/FormField.vue';
import { repairRequestSchema as schema, repairRequestSteps as steps } from '@/lib/form-schemas';
import { submitRepairRequest } from '@/services/repair-submission';
import { useValidatedForm } from '@/composables/form';
const route = useRoute(),
  router = useRouter(),
  cache = useQueryClient(),
  auth = useAuth(),
  lookups = useLookups();
const step = ref(0),
  files = ref<File[]>([]),
  failure = ref(''),
  search = ref(''),
  requestPage = ref(1);
const form = useValidatedForm(schema, {
  device_type_id: '',
  device_brand_id: '',
  model: '',
  serial_number: '',
  repair_category_id: '',
  problem_description: '',
  technician_profile_id: String(route.query.technician ?? ''),
  starts_at: '',
  service_location: ''
});
const values = form.values;
const repairForm = ref<HTMLFormElement>();
watch(step, async () => {
  await nextTick();
  const heading = repairForm.value?.querySelector('h2');
  if (heading) { heading.tabIndex = -1; heading.focus(); }
});
async function focusInvalidField() {
  await nextTick();
  repairForm.value?.querySelector<HTMLElement>('[aria-invalid="true"]')?.focus();
}
const category = computed(() => lookups.data?.repair_categories.find(c => String(c.id) === values.repair_category_id));
const directory = useApiQuery<Page<Technician>>(() => ({
  queryKey: ['request-technicians', values.device_type_id, values.repair_category_id, search.value, requestPage.value],
  enabled: step.value === 2,
  queryFn: async ({
    signal
  }) => (await api.get<Page<Technician>>('/technicians', {
    signal,
    params: {
      availability: 'available',
      device_type: values.device_type_id || undefined,
      repair_category: values.repair_category_id || undefined,
      search: search.value || undefined,
      page: requestPage.value
    }
  })).data
}));
const selected = useApiQuery<Technician>(() => ({
  queryKey: ['technician', values.technician_profile_id],
  enabled: !!values.technician_profile_id,
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Technician;
  }>('/technicians/' + values.technician_profile_id, {
    signal
  })).data.data
}));
const submit = form.handleSubmit(async data => {
  failure.value = '';
  if (!canRequestRepair(selected.data, data.device_type_id, category.value)) {
    form.setError('technician_profile_id', {
      message: 'Choose an approved, available technician who supports this device and repair problem.'
    });
    step.value = 2;
    return;
  }
  const appointment = proposedAppointment(data.starts_at);
  if (!appointment) {
    form.setError('starts_at', {
      message: 'Choose a future appointment in Philippine time.'
    });
    step.value = 3;
    return;
  }
  try {
    const repair = await submitRepairRequest(data, files.value, appointment, cache);
    await router.push('/dashboard/repairs/' + repair.id);
  } catch (error) {
    failure.value = errorMessage(error);
  }
});
async function advance() {
  if (!form.trigger(steps[step.value])) {
    await focusInvalidField();
    return;
  }
  if (step.value === 2 && !canRequestRepair(selected.data, values.device_type_id, category.value)) {
    form.setError('technician_profile_id', {
      message: 'Choose an approved, available technician who supports this device and repair problem.'
    });
    await focusInvalidField();
    return;
  }
  if (step.value === 1) requestPage.value = 1;
  step.value++;
}
</script>

<template>
<Empty v-if="auth.user?.role !== 'customer'" title="Customer account required" description="Technicians manage incoming requests in their dashboard." />
<Loading v-else-if="lookups.isPending" />
<Failure v-else-if="lookups.isError" :error="lookups.error" :retry="()=>lookups.refetch()" />
<template v-else-if="lookups.data">
<PageHeading eyebrow="Start a repair" title="Request a repair" description="Choose your technician and explain what your device needs. We will guide you through each step.">
</PageHeading>
<ol class="my-7 flex flex-wrap gap-2 text-xs">
<template v-for="(s, i) in ['Device', 'Problem', 'Technician', 'Appointment', 'Confirm']" :key="s">
<li :aria-current="i === step ? 'step' : undefined" :class="`rounded-xl border px-3.5 py-2.5 font-medium transition-colors ${i === step ? 'border-blue-600 bg-primary text-white shadow-sm' : i < step ? 'border-blue-100 bg-blue-50 text-blue-700' : 'border-slate-200 bg-white text-slate-500'}`">{{ i + 1 }}. {{ s }}</li>
</template>
</ol>
<form ref="repairForm" class="surface-card p-5 sm:p-8" @submit="submit">
<div class="space-y-6">
<template v-if="step === 0">
<h2 class="section-title">Tell us about your device</h2>
<FormField :form="form" :name="'device_type_id'" :label="'Device type'" :options="lookups.data.device_types" />
<FormField :form="form" :name="'device_brand_id'" :label="'Brand'" :options="lookups.data.device_brands" />
<FormField :form="form" :name="'model'" :label="'Model'" />
<FormField :form="form" :name="'serial_number'" :label="'Serial number (optional, private)'" />
</template>
<template v-if="step === 1">
<h2 class="section-title">What needs fixing?</h2>
<FormField :form="form" :name="'repair_category_id'" :label="'Problem category'" :options="lookups.data.repair_categories.filter(c => !c.device_type_id || String(c.device_type_id) === values.device_type_id)" />
<div class="text-sm font-medium"><label for="repair-problem-description">Problem description</label><textarea id="repair-problem-description" :rows="5" :class="`${fieldClass} mt-2`" v-model="form.values['problem_description']" :name="'problem_description'" :aria-invalid="!!form.formState.errors.problem_description" :aria-describedby="form.formState.errors.problem_description ? 'problem-description-error' : undefined">
</textarea>
<template v-if="form.formState.errors.problem_description">
<span id="problem-description-error" class="text-xs text-red-700">{{ form.formState.errors.problem_description.message }}</span>
</template>
</div>
<label class="block text-sm">Device photos (up to five, 5 MB each)<Input type="file" class="mt-2 h-auto py-3" multiple accept="image/jpeg,image/png,image/webp" @change="(e: Event) => {
  const next = Array.from((e.target as HTMLInputElement).files ?? []);
  const photoError = devicePhotoError(next);
  if (photoError) {
    failure = photoError;
    files = [];
    (e.target as HTMLInputElement).value = '';
    return;
  }
  failure = '';
  files = next;
}">
</Input>
</label>
<p class="text-xs text-muted-foreground">{{ files.length }} photo(s) selected. Attachments are private to your repair.</p>
</template>
<template v-if="step === 2">
<h2 class="section-title">Choose the right technician</h2>
<label class="block text-sm">Search matching professionals<Input class="mt-2" placeholder="Name, city or problem" :value="search" @input="(e: Event) => {
  search = (e.target as HTMLInputElement).value;
  requestPage = 1;
}">
</Input>
</label>
<template v-if="selected.data">
<p class="rounded-lg bg-blue-50 p-4 text-sm">Selected: <strong>{{ selected.data.name }}</strong> · {{ selected.data.city }} · {{ selected.data.reviews_count ? `${selected.data.average_rating}/5` : 'No reviews yet' }}</p>
</template>
<template v-if="selected.isError">
<Failure :error="selected.error" :retry="() => void selected.refetch()">
</Failure>
</template>
<template v-if="directory.isPending">
<Loading>
</Loading>
</template>
<template v-else>
<template v-if="directory.isError">
<Failure :error="directory.error" :retry="() => directory.refetch()">
</Failure>
</template>
<template v-else>
<template v-if="directory.data.data.length">
<div class="grid gap-4 sm:grid-cols-2">
<template v-for="(t) in directory.data.data" :key="t.id">
<label :class="`flex cursor-pointer items-start gap-3 rounded-xl border p-4 ${values.technician_profile_id === String(t.id) ? 'border-primary bg-blue-50' : ''}`">
<input class="mt-1" type="radio" :value="String(t.id)" v-model="form.values['technician_profile_id']" :name="'technician_profile_id'" :aria-invalid="!!form.formState.errors.technician_profile_id" :aria-describedby="form.formState.errors.technician_profile_id ? 'technician-selection-error' : undefined">
</input>
<span class="text-sm">
<strong>{{ t.name }}</strong>
<span class="mt-2 block text-muted-foreground">{{ t.city }}, {{ t.province }} · {{ t.reviews_count ? `${t.average_rating}/5` : 'No reviews yet' }}</span>
<span class="mt-2 block text-xs">{{ t.specializations.map(s => s.name).join(', ') }}</span>
</span>
</label>
</template>
</div>
<Pagination :page="directory.data" :onChange="(value) => requestPage = value">
</Pagination>
</template>
<template v-else>
<Empty title="No matching available technicians" description="Try another service or return to the directory.">
</Empty>
</template>
</template>
</template>
<template v-if="form.formState.errors.technician_profile_id">
<p id="technician-selection-error" role="alert" class="text-sm text-red-700">{{ form.formState.errors.technician_profile_id.message }}</p>
</template>
</template>
<template v-if="step === 3">
<h2 class="section-title">Propose an appointment</h2>
<FormField :form="form" :name="'starts_at'" :label="'Preferred date and time (Philippine time)'" :type="'datetime-local'" />
<p class="text-xs text-muted-foreground">A one-hour appointment will be proposed. Your technician confirms or coordinates a new time.</p>
<FormField :form="form" :name="'service_location'" :label="'Preferred service location / meeting point'" />
</template>
<template v-if="step === 4">
<h2 class="section-title">Review your repair request</h2>
<dl class="grid gap-5 text-sm sm:grid-cols-2">
<template v-for="([key, value]) in [['Device', `${lookups.data.device_brands.find(b => String(b.id) === values.device_brand_id)?.name} ${values.model}`], ['Repair problem', category?.name ?? ''], ['Technician', selected.data?.name ?? 'Selected technician'], ['Appointment (Philippine time)', values.starts_at.replace('T', ' ')], ['Service location', values.service_location], ['Photos', `${files.length} private attachments`]]" :key="key">
<div>
<dt class="text-muted-foreground">{{ key }}</dt>
<dd class="mt-2 font-medium">{{ value }}</dd>
</div>
</template>
</dl>
<p class="whitespace-pre-wrap text-sm leading-7">{{ values.problem_description }}</p>
<p class="rounded-lg bg-blue-50 p-4 text-sm leading-6">This submits a request to your selected technician. Work begins after acceptance, diagnosis and your estimate approval.</p>
</template>
<template v-if="failure">
<p role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-800">{{ failure }}</p>
</template>
</div>
<div class="mt-8 grid grid-cols-[auto_minmax(0,1fr)] gap-3 sm:flex sm:justify-between">
<Button type="button" variant="outline" :disabled="step === 0 || form.formState.isSubmitting" @click="() => step = step - 1">Back</Button>
<template v-if="step < 4">
<Button type="button" :disabled="step === 2 && selected.isFetching" @click="() => void advance()">Continue</Button>
</template>
<template v-else>
<Button type="submit" :disabled="form.formState.isSubmitting">
<template v-if="form.formState.isSubmitting">{{ 'Submitting…' }}</template>
<template v-else>{{ 'Submit repair request' }}</template>
</Button>
</template>
</div>
</form>
</template>
</template>
