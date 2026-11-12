<script setup lang="ts">
import { computed, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useQueryClient } from '@tanstack/vue-query';
import { useApiQuery } from '@/composables/query';
import { useAuthStore as useAuth } from '@/stores/auth';
import { api } from '@/services/api';
import { refreshTechnicianViews } from '@/services/technician-cache';
import { toast } from 'vue-sonner';
import { fieldClass } from '@/components/ui/fields';
import { errorMessage } from '@/lib/errors';
import { formatBusinessDate, formatPeso } from '@/lib/format';
import { statusLabel } from '@/lib/status';
import type { Technician, Repair } from '@/types';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Loading from '@/components/common/Loading.vue';
import Failure from '@/components/common/Failure.vue';
import Status from '@/components/common/Status.vue';
import PageHeading from '@/components/common/PageHeading.vue';
const route = useRoute(),
  auth = useAuth(),
  cache = useQueryClient();
const id = computed(() => String(route.params.id));
const busy = ref(false),
  failure = ref('');
const query = useApiQuery<Repair>(() => ({
  queryKey: ['repair', id.value],
  queryFn: async ({
    signal
  }) => (await api.get<{
    data: Repair;
  }>('/repair-requests/' + id.value, {
    signal
  })).data.data
}));
const r = computed(() => query.data!);
const tech = computed(() => auth.user?.role === 'technician');
const next = computed(() => ({
  accepted: 'scheduled',
  scheduled: 'diagnosing',
  in_repair: 'ready_for_pickup'
} as Record<string, string>)[r.value?.status]);
const base = computed(() => '/repair-requests/' + id.value);
function manilaInput(time: string) {
  return new Date(new Date(time).getTime() + 8 * 3600000).toISOString().slice(0, 16);
}
async function action(path: string, body: unknown = {}, method: 'post' | 'patch' = 'post') {
  busy.value = true;
  failure.value = '';
  try {
    await api[method](path, body);
    if (path === '/reviews') {
      await Promise.all([
        refreshTechnicianViews(cache),
        cache.invalidateQueries({ queryKey: ['reviews'] }),
        cache.invalidateQueries({ queryKey: ['my-reviews'] }),
      ]);
    }
    await Promise.all([['repair', id.value], ['repairs'], ['dashboard'], ['appointments'], ['notifications']].map(queryKey => cache.invalidateQueries({
      queryKey
    })));
    toast.success('Repair updated');
  } catch (error) {
    failure.value = errorMessage(error);
  } finally {
    busy.value = false;
  }
}
const apiBase = import.meta.env.VITE_API_URL || '/api/v1';
const FormData = globalThis.FormData;
</script>

<template>
<Loading v-if="query.isPending" />
<Failure v-else-if="query.isError" :error="query.error" :retry="() => query.refetch()" />
<template v-else-if="query.data">
<PageHeading eyebrow="Repair request" :title="r.reference" :description="`Submitted ${formatBusinessDate(r.created_at)}`">
<template #action>
<Status :value="r.status">
</Status>
</template>
</PageHeading>
<template v-if="failure">
<Failure :error="new Error(failure)">
</Failure>
</template>
<div class="mt-7 grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
<div class="space-y-6">
<section class="surface-card p-6">
<h2 class="section-title">{{ r.device_brand }} {{ r.model }}</h2>
<p class="mt-2 text-sm text-muted-foreground">{{ r.device_type }} · {{ r.category }}</p>
<p class="mt-5 whitespace-pre-line text-sm leading-7">{{ r.problem_description }}</p>
<template v-if="r.serial_number">
<p class="mt-4 text-xs text-muted-foreground">Serial number: {{ r.serial_number }}</p>
</template>
<div class="mt-5 border-t pt-4 text-sm">
<p>Customer: {{ r.customer.name }}</p>
<p class="mt-2">Technician: {{ r.technician.name }}</p>
</div>
</section>
<section class="surface-card p-6">
<h2 class="section-title">Estimates</h2>
<template v-if="r.estimates?.length">
<template v-for="(e) in r.estimates" :key="e.id">
<div class="mt-5 border-t pt-5">
<div class="flex flex-wrap items-center justify-between gap-3">
<p class="text-xl font-bold">{{ formatPeso(e.amount) }}</p>
<Status :value="e.status">
</Status>
</div>
<p class="mt-3 text-sm leading-7">{{ e.description }}</p>
<template v-if="!tech && r.status === 'awaiting_customer_approval' && e.status === 'pending'">
<div class="mt-4 flex flex-wrap gap-3">
<Button :disabled="busy" @click="() => void action(`${base}/approve-estimate`)">Approve estimate</Button>
<Button variant="destructive" :disabled="busy" @click="() => void action(`${base}/cancel`)">Decline and cancel repair</Button>
</div>
</template>
</div>
</template>
</template>
<template v-else>
<p class="mt-4 text-sm text-muted-foreground">Your technician will submit an estimate after diagnosis.</p>
</template>
<template v-if="tech && r.status === 'diagnosing'">
<form class="mt-5 space-y-4 border-t pt-5" @submit="(e: Event) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement));
  void action(`${base}/estimates`, d);
}">
<label class="block text-sm">Estimate amount (PHP)<Input name="amount" type="number" min="0.01" step="0.01" class="mt-2" required>
</Input>
</label>
<label class="block text-sm">Scope and parts<textarea :class="`${fieldClass} mt-2`" name="description" :minLength="10" required>
</textarea>
</label>
<Button :disabled="busy" type="submit">Send estimate for approval</Button>
</form>
</template>
</section>
<section class="surface-card p-6">
<h2 class="section-title">Appointments</h2>
<template v-for="(a) in r.appointments" :key="a.id">
<div class="mt-5 border-t pt-4">
<p class="text-sm font-medium">{{ formatBusinessDate(a.starts_at) }}</p>
<p class="mt-2 text-sm text-muted-foreground">{{ a.service_location }}</p>
<div class="mt-3">
<Status :value="a.status">
</Status>
</div>
<template v-if="['pending', 'accepted', 'scheduled'].includes(r.status) && a.status !== 'canceled'">
<details class="mt-4 text-sm">
<summary class="cursor-pointer text-primary">
<template v-if="tech">{{ 'Confirm or reschedule appointment' }}</template>
<template v-else>{{ 'Update proposed appointment' }}</template>
</summary>
<form class="mt-4 space-y-4" @submit="(e: Event) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement));
  const start = new Date(`${String(d.starts_at)}:00+08:00`);
  void action(`/appointments/${a.id}`, {
    starts_at: start.toISOString(),
    ends_at: new Date(start.getTime() + 3600000).toISOString(),
    service_location: d.service_location
  }, 'patch');
}">
<label class="block">Start (Philippine time)<Input class="mt-2" name="starts_at" type="datetime-local" :default-value="manilaInput(a.starts_at)" required>
</Input>
</label>
<label class="block">Service location<Input class="mt-2" name="service_location" :default-value="a.service_location" required>
</Input>
</label>
<Button :disabled="busy" type="submit">
<template v-if="tech">{{ 'Confirm appointment' }}</template>
<template v-else>{{ 'Propose update' }}</template>
</Button>
</form>
</details>
</template>
</div>
</template>
</section>
<section class="surface-card p-6">
<h2 class="section-title">Private attachments</h2>
<ul class="mt-4 space-y-3">
<template v-for="(a) in r.attachments" :key="a.id">
<li>
<a class="break-all text-sm text-primary underline" :href="`${apiBase}/attachments/${a.id}`" target="_blank" rel="noreferrer">{{ a.original_name }}</a>
</li>
</template>
</ul>
<form class="mt-5 flex flex-wrap items-end gap-3" @submit="(e: Event) => {
  e.preventDefault();
  const body = new FormData(e.currentTarget as HTMLFormElement);
  void action(`${base}/attachments`, body);
}">
<label class="min-w-0 flex-1 text-sm">Add a photo or PDF (5 MB maximum)<Input name="file" class="mt-2 h-auto py-3" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" required>
</Input>
</label>
<Button :disabled="busy" type="submit">Upload</Button>
</form>
</section>
<template v-if="!tech && r.status === 'completed'">
<section class="surface-card p-6">
<h2 class="section-title">Your review</h2>
<template v-if="r.review">
<p class="mt-4 text-sm">{{ r.review.rating }}/5 · {{ r.review.body }}</p>
</template>
<template v-else>
<form class="mt-5 space-y-4" @submit="(e: Event) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement));
  void action('/reviews', {
    ...d,
    repair_request_id: r.id,
    rating: Number(d.rating)
  });
}">
<label class="block text-sm">Rating<select :class="`${fieldClass} mt-2`" name="rating">
<template v-for="(n) in [5, 4, 3, 2, 1]" :key="n">
<option :value="n">{{ n }} stars</option>
</template>
</select>
</label>
<label class="block text-sm">Your experience<textarea :class="`${fieldClass} mt-2`" :minLength="10" name="body" required>
</textarea>
</label>
<Button :disabled="busy" type="submit">Submit review</Button>
</form>
</template>
</section>
</template>
</div>
<div class="space-y-6">
<section class="surface-card p-6">
<h2 class="section-title">Repair actions</h2>
<div class="mt-5 flex flex-wrap gap-3">
<template v-if="tech && r.status === 'pending'">
<Button :disabled="busy" @click="() => void action(`/technician/repair-requests/${r.id}/accept`)">Accept request</Button>
</template>
<template v-if="tech && next">
<Button :disabled="busy" @click="() => void action(`${base}/status`, {
  status: next
}, 'patch')">Mark {{ statusLabel(next).toLowerCase() }}</Button>
</template>
<template v-if="!tech && ['pending', 'accepted', 'scheduled'].includes(r.status)">
<Button :disabled="busy" variant="outline" @click="() => void action(`${base}/cancel`)">Cancel request</Button>
</template>
<template v-if="!tech && r.status === 'ready_for_pickup'">
<Button :disabled="busy" @click="() => void action(`${base}/status`, {
  status: 'completed'
}, 'patch')">Confirm pickup & completion</Button>
</template>
</div>
<template v-if="tech && r.status === 'pending'">
<form class="mt-5 space-y-3" @submit="(e: Event) => {
  e.preventDefault();
  void action(`/technician/repair-requests/${r.id}/decline`, Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement)));
}">
<label class="block text-sm">Reason for declining<Input class="mt-2" name="remarks" required>
</Input>
</label>
<Button type="submit" variant="outline" :disabled="busy">Decline request</Button>
</form>
</template>
<template v-if="['completed', 'rejected', 'canceled'].includes(r.status)">
<p class="mt-4 text-sm text-muted-foreground">This repair is closed.</p>
</template>
<template v-else>
<p class="mt-4 text-xs leading-6 text-muted-foreground">Confirm the appointment and review the estimate to keep your repair moving. Updates appear in the timeline below.</p>
</template>
</section>
<section class="surface-card p-6">
<h2 class="section-title">Progress timeline</h2>
<ol class="mt-5 space-y-6 border-l-2 border-blue-100 pl-5">
<template v-for="(h) in r.history" :key="h.id">
<li>
<p class="text-sm font-semibold">{{ statusLabel(h.new_status) }}</p>
<p class="mt-1 text-xs text-muted-foreground">{{ formatBusinessDate(h.created_at) }} · {{ h.actor?.name }}</p>
<template v-if="h.remarks">
<p class="mt-2 text-sm leading-6">{{ h.remarks }}</p>
</template>
</li>
</template>
</ol>
<template v-if="tech && !['completed', 'canceled', 'rejected'].includes(r.status)">
<form class="mt-6 space-y-3 border-t pt-5" @submit="(e: Event) => {
  e.preventDefault();
  void action(`${base}/notes`, Object.fromEntries(new FormData(e.currentTarget as HTMLFormElement)));
}">
<label class="block text-sm">Add repair note<textarea :class="`${fieldClass} mt-2`" name="remarks" required>
</textarea>
</label>
<Button type="submit" :disabled="busy">Add note</Button>
</form>
</template>
</section>
</div>
</div>
</template>
</template>
