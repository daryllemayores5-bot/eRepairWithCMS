<script setup lang="ts">
import { RouterLink as Link } from 'vue-router';
import { formatBusinessDate } from '@/lib/format';
import type { Technician, Repair } from '@/types';
import { ArrowUpRight } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import Empty from '@/components/common/Empty.vue';
import Status from '@/components/common/Status.vue';
withDefaults(defineProps<{
  repairs: Repair[];
  technicianView?: boolean;
}>(), {
  technicianView: false
});
</script>

<template>
<template v-if="repairs.length">
<ul class="space-y-4 p-4 md:hidden" role="list" aria-label="Repair request cards">
<li v-for="r in repairs" :key="r.id" class="rounded-xl border border-slate-200 p-4">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <Link :to="`/dashboard/repairs/${r.id}`" :aria-label="`Open repair ${r.reference}`" class="min-w-0 break-words font-semibold text-primary underline-offset-4 hover:underline">{{ r.reference }}</Link>
    <Status :value="r.status" />
  </div>
  <p class="mt-3 break-words text-sm font-medium text-navy">{{ r.device_brand }} {{ r.model }}</p>
  <dl class="mt-4 space-y-3 text-sm">
    <div><dt class="text-xs text-slate-500">{{ technicianView ? 'Customer' : 'Technician' }}</dt><dd class="mt-1 break-words">{{ technicianView ? r.customer.name : r.technician.name }}</dd></div>
    <div><dt class="text-xs text-slate-500">Submitted · Philippine time</dt><dd class="mt-1">{{ formatBusinessDate(r.created_at) }}</dd></div>
  </dl>
  <Button as-child variant="outline" size="sm" class="mt-4 w-full"><Link :to="`/dashboard/repairs/${r.id}`" :aria-label="`View details for ${r.reference}`">View repair<ArrowUpRight aria-hidden="true" /></Link></Button>
</li>
</ul>
<div class="data-scroll hidden min-w-0 md:block" tabindex="0" role="region" aria-label="Repair request table">
<table class="w-full min-w-[570px] text-left text-sm">
<caption class="sr-only">Repair requests with repair participants, submission dates and current status</caption>
<thead class="border-y bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500">
<tr>
<template v-for="(s) in ['Reference / device', technicianView ? 'Customer' : 'Technician', 'Request date', 'Status', 'Action']" :key="s">
<th class="px-5 py-3.5 font-semibold">{{ s }}</th>
</template>
</tr>
</thead>
<tbody>
<template v-for="(r) in repairs" :key="r.id">
<tr class="border-b border-slate-100 transition-colors last:border-0 hover:bg-slate-50/70">
<td class="px-5 py-4">
<p class="font-semibold text-slate-800">{{ r.reference }}</p>
<p class="mt-1 text-xs text-muted-foreground">{{ r.device_brand }} {{ r.model }}</p>
</td>
<td class="px-5 py-4 text-slate-600">
<template v-if="technicianView">{{ r.customer.name }}</template>
<template v-else>{{ r.technician.name }}</template>
</td>
<td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{{ formatBusinessDate(r.created_at) }}</td>
<td class="px-5 py-4">
<Status :value="r.status">
</Status>
</td>
<td class="px-5 py-4">
<Button as-child variant="outline" size="sm">
<Link :to="`/dashboard/repairs/${r.id}`">View details<ArrowUpRight aria-hidden="true">
</ArrowUpRight>
</Link>
</Button>
</td>
</tr>
</template>
</tbody>
</table>
</div>
</template>
<template v-else>
<div class="p-5 sm:p-6">
<Empty title="No repair requests yet" :description="technicianView ? 'Customer requests will appear here. Keep your profile and availability up to date.' : 'Your requests and repair updates will appear here. Start by finding the right technician for your device.'">
</Empty>
</div>
</template>
</template>
